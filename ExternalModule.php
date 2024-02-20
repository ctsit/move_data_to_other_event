<?php

namespace MDOE\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;
use Files;

class ExternalModule extends AbstractExternalModule {

    function redcap_every_page_top($project_id) {

        $project_settings = $this->framework->getProjectSettings();

        if (!$project_settings['active']) return;

        // needed to bypass uncatchable exception in framework->getUser()
        if ( !defined('USERID') ) return;

        if ( !$this->framework->getUser()->hasDesignRights() &&
                ( $this->getSystemSetting('restrict_to_designers_global') ||
                  !$project_settings['allow_non_designers']) )
        {
            return;
        }

        if (PAGE != 'DataEntry/record_home.php' || !$_REQUEST['id']) return;
        // __DIR__ . '/migratedata.php'; does not work due to VM symlink(?)
        $ajax_page = json_encode($this->framework->getUrl("migratedata.php"));

        $this->initializeJavascriptModuleObject();
        $this->tt_addToJavascriptModuleObject('appPathImages', APP_PATH_IMAGES);

        echo ("<script> var ajaxpage = {$ajax_page}; </script>");
        include('div.html');
        $this->includeJs('js/mdoe.js');
    }

    function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '">;</script>';
    }

    // Equivalent to fetch_all($mysqli_result, MYSQLI_ASSOC)
    // REDCap EM framework 15's query result objects do not support fetch_all
    private function unspoolMysqliResult($mysqli_result) {
        $data = [];
        for ($i = 0; $i < $mysqli_result->num_rows; ++$i) {
            $data[$i] = $mysqli_result->fetch_assoc();
        }

        return $data;
    }

    function moveEvent($source_event_id, $target_event_id, $record_id = NULL, $project_id = NULL, $form_names = NULL, $delete_source_data = true) {
        $record_id = $record_id ?: ( ($this->framework->getRecordId()) ?: NULL ); // return in place of NULL causes errors
        $project_id = $project_id ?: ( ($this->framework->getProjectId()) ?: NULL );
        $record_pk = REDCap::getRecordIdField();
        $redcap_data_table = REDCap::getDataTable($project_id);
        // HACK: whitelist potential table names since they cannot be parameterized
        if (!preg_match("/^redcap_data\d*$/", $redcap_data_table)) { return; }

        $sql = "SELECT a.field_name FROM redcap_metadata as a
            INNER JOIN (SELECT form_name FROM redcap_events_forms WHERE event_id = ?)
            as b ON a.form_name = b.form_name
            WHERE a.project_id = ?";

        $sql_parameters = [$source_event_id, $project_id];

        $query = $this->framework->createQuery();
        $query->add($sql, $sql_parameters);
        $query->add('and')->addInClause('a.form_name', $form_names);
        $query->add("ORDER BY field_order ASC");

        $result = $query->execute();

        // Needed for logging
        $form_names = implode("', '", $form_names);

        $fields = [];
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

        // NOTE: prepared statements can not parameterize table names
        $edocs_sql = "SELECT d.field_name, em.doc_id, em.stored_name, em.doc_name
            FROM $redcap_data_table d
        INNER JOIN redcap_metadata m
            ON
            m.project_id = d.project_id
            AND m.field_name = d.field_name
            AND m.element_type = 'file'
        INNER JOIN redcap_edocs_metadata em
            ON em.doc_id = d.value
        WHERE
            d.project_id = ?
            AND d.record = ?
            AND d.event_id = ?";

        // TODO: consider: em.element_validation_type == 'signature'
        $parameters = [$project_id, $record_id, $source_event_id];

        $query = $this->framework->createQuery();
        $query->add($edocs_sql, $parameters);
        $query->add('and')->addInClause('d.field_name', $fields);
        $edocs_fields = $query->execute();

        $edocs_present = ($edocs_fields->num_rows > 0);
        if ($edocs_present) {
            $edocs_results = $this->unspoolMysqliResult($edocs_fields);
        }

        $new_data = [];

        // get record for selected event, swap source_event_id for target_event_id
        $old_data = REDCap::getData($get_data);
        $new_data[$record_id][$target_event_id] = $old_data[$record_id][$source_event_id];


        if ($delete_source_data) {
            $response = REDCap::saveData($project_id, 'array', $new_data, 'normal'); // initial write to target
            $log_message = "Migrated form(s) " . $form_names . " from event " . $source_event_id . " to " . $target_event_id;

            // soft delete all data for each field
            array_walk_recursive($old_data[$record_id][$source_event_id], function(&$value, $key) use ($record_pk) {
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

        $redcap_data_table = REDCap::getDataTable($project_id);
        // HACK: whitelist potential table names since they cannot be parameterized
        if (!preg_match("/^redcap_data\d*$/", $redcap_data_table)) return;

        // check for fields which did not transfer
        $revisit_fields = [];
        foreach ($check_old as $field => $value) {
            if ($value !== '' && $value !== '0' && $value !== NULL &&
                $field != REDCap::getRecordIdField()) {
                    array_push($revisit_fields, $field);
            }
        }

         if ($revisit_fields !== [])  {
             // Raw SQL to transfer docs which do not transfer or delete with saveData
             // explicitly excluding the record's primary key

             $docs_xfer_sql = "UPDATE $redcap_data_table SET event_id = ?
                 WHERE project_id = ?
                 AND event_id = ?
                 AND record = ?
                 AND field_name NOT IN (?)";

             $record_id_field = "'" . REDCap::getRecordIdField() . "'";
             $docs_xfer_parameters = [$target_event_id, $project_id, $source_event_id, $record_id, $record_id_field];

             $query = $this->framework->createQuery();
             $query->add($docs_xfer_sql, $docs_xfer_parameters);
             $query->add('and')->addInClause('field_name', $revisit_fields);

             $query->execute();

             $revisit_fields = implode("', '", $revisit_fields);
             $log_message .= ". Forced transfer of additional field(s): " . $revisit_fields;
        }
        return $log_message;
    }
}
