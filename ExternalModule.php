<?php

namespace MDOE\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;
use Files;

class ExternalModule extends AbstractExternalModule {

    function redcap_every_page_top($project_id) {

        $project_settings = $this->framework->getProjectSettings();

        if (!$project_settings['active']['value']) {
            return;
        }

        if ( !$this->framework->getUser()->hasDesignRights() &&
                ( $this->getSystemSetting('restrict_to_designers_global') ||
                  !$project_settings['allow_non_designers']['value']) )
        {
            return;
        }

        if (PAGE != 'DataEntry/record_home.php' || !$_REQUEST['id']) return;
        // __DIR__ . '/migratedata.php'; does not work due to VM symlink(?)
        $ajax_page = json_encode($this->framework->getUrl("migratedata.php"));

        echo ("<script> var ajaxpage = {$ajax_page}; </script>");
        include('div.html');
        $this->includeJs('js/mdoe.js');
    }

    function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '">;</script>';
    }

    function moveEvent($source_event_id, $target_event_id, $record_id = NULL, $project_id = NULL, $form_names = NULL, $delete_source_data = true) {
        $record_id = $record_id ?: ( ($this->framework->getRecordId()) ?: NULL ); // return in place of NULL causes errors
        $project_id = $project_id ?: ( ($this->framework->getProjectId()) ?: NULL );
        $record_pk = REDCap::getRecordIdField();
        $form_names = implode("', '", $form_names);

        //TODO: sanitize without mysqli_real_escape_string
        $sql = "SELECT a.field_name FROM redcap_metadata as a
            INNER JOIN (SELECT form_name FROM redcap_events_forms WHERE event_id = " . ($source_event_id) .  ")
            as b ON a.form_name = b.form_name
            WHERE a.project_id = " . ($project_id) . "
            AND a.form_name IN ('" . $form_names . "')
            ORDER BY field_order ASC;";

        $fields = [];
        $result= $this->framework->query($sql);

        while ($row = $result->fetch_assoc()) {
            $fields[] = $row["field_name"];
        }

        $get_data = [
            'project_id' => $project_id,
            'return_format' => 'array',
            'records' => $record_id,
            'fields' => $fields,
            'events' => $source_event_id
        ];

        $field_list = ($fields) ? " AND d.field_name IN ('" . implode('\',\'', $fields) . "');" : ";";
        $edocs_sql = "SELECT d.field_name, em.doc_id, em.stored_name, em.doc_name
            FROM redcap_data d
        INNER JOIN redcap_metadata m
            ON
            m.project_id = d.project_id
            AND m.field_name = d.field_name
            AND m.element_type = 'file'
        INNER JOIN redcap_edocs_metadata em
            ON em.doc_id = d.value
        WHERE
            d.project_id = " . $project_id . "
            AND d.record = " . $record_id . "
            AND d.event_id = " . $source_event_id .
            $field_list;

        // TODO: consider: em.element_validation_type == 'signature'

        $edocs_fields = $this->framework->query($edocs_sql);

        $edocs_present = ($edocs_fields->num_rows > 0);
        if ($edocs_present) {
            $edocs_results = $edocs_fields->fetch_all(MYSQLI_ASSOC);
        }

        $new_data = [];

        // get record for selected event, swap source_event_id for target_event_id
        $old_data = REDCap::getData($get_data);
        $new_data[$record_id][$target_event_id] = $old_data[$record_id][$source_event_id];


        if ($delete_source_data) {
            $response = REDCap::saveData($project_id, 'array', $new_data, 'normal'); // initial write to target
            $log_message = "Migrated form(s) " . $form_names . " from event " . $source_event_id . " to " . $target_event_id;

            // soft delete all data for each field
            array_walk_recursive($old_data[$record_id][$source_event_id], function(&$value, $key) {
                    if ($key !== $record_pk) {
                        $value = NULL;
                    }
                }
            );
            $delete_response = REDCap::saveData($project_id, 'array', $old_data, 'overwrite');

            // previous step did not delete documents, force their migration
            $log_message = $this->forceMigrateSourceFields($get_data, $project_id, $record_id, $source_event_id, $target_event_id, $log_message);
        } else {
            // Create copies of edocs for cloning
            // necessary as if a cloned form containing an edoc is deleted, the file which all clones reference is also purged
            if ($edocs_present) {
                // Clone files and assign them to their respective fields in the cloned forms
                foreach($edocs_results as $edocs_result) {
                    if (isset($new_data[$record_id][$target_event_id][$edocs_result['field_name']])) {
                        $path = Files::copyEdocToTemp($edocs_result['doc_id']); // clone the existing file to temp dir
                        $file = [];
                        $file['name'] = basename($edocs_result['doc_name']);
                        $file['tmp_name'] = $path;
                        $file['size'] = filesize($path);

                        $new_data[$record_id][$target_event_id][$edocs_result['field_name']] =
                            Files::uploadFile($file, $project_id);
                    }
                }
            }
            $response = REDCap::saveData($project_id, 'array', $new_data, 'normal',
                    'YMD', 'flat', null, true, true, true, false, true, array(), false,
                    false); // do not skip file upload fields, see the REDCap core code Classes/Records.php
            $log_message = "Cloned form(s) " . $form_names . " from event " . $source_event_id . " to " . $target_event_id;
        }

        REDCap::logEvent("Moved data from an event to a different event", $log_message);

        // TODO: parse response, use as flag for deletion
        return json_encode($delete_response);

        // if moving an entire event, event data is deleted via call to core JS function, deleteEventInstance which wraps \Controller\DataEntryController
        // requires POST and GET data from the record_home page
    }

    function forceMigrateSourceFields($get_data, $project_id, $record_id, $source_event_id, $target_event_id, $log_message) {
        $check_old = REDCap::getData($get_data)[$record_id][$source_event_id];

        // check for fields which did not transfer
        $revisit_fields = [];
        foreach ($check_old as $field => $value) {
            if ($value !== '' && $value !== '0' && $value !== NULL &&
                $field != REDCap::getRecordIdField()) {
                    array_push($revisit_fields, "'$field'");
            }
        }

         if ($revisit_fields !== [])  {
             // Raw SQL to transfer docs which do not transfer or delete with saveData
             // explicitly excluding the record's primary key
             $revisit_fields = implode(',', $revisit_fields);
             $log_message .= ". Forced transfer of additional field(s): " . $revisit_fields;
             $docs_xfer_sql = "UPDATE redcap_data SET event_id = " . $target_event_id . "
                 WHERE project_id = " . $project_id . "
                 AND event_id = " . $source_event_id . "
                 AND record = " . $record_id . "
                 AND field_name NOT IN ('" . REDCap::getRecordIdField() . "')
                 AND field_name IN (" . $revisit_fields . ");";
             $this->framework->query($docs_xfer_sql);
        }
        return $log_message;
    }
}
