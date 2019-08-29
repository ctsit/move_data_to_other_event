<?php

namespace MDOE\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;

class ExternalModule extends AbstractExternalModule {

    function redcap_every_page_top($project_id) {

        if (PAGE != 'DataEntry/record_home.php' || !$_REQUEST['id']) return;
        // __DIR__ . '/migratedataphp'; does not work due to VM symlink(?)
        $ajax_page = json_encode($this->framework->getUrl("migratedata.php"));

        //$this->moveForm(125, 131, 1, 22, 'participant_info_survey', true);

        echo ("<script> var ajaxpage = {$ajax_page}; </script>");
        include('div.html');
        $this->includeJs('js/mdoe.js');
    }

    function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '">;</script>';
    }

    function moveEvent($source_event_id, $target_event_id, $record_id = NULL, $project_id = NULL) {
        $record_id = $record_id ?: ( ($this->framework->getRecordId()) ?: NULL ); // return in place of NULL causes errors
        $project_id = $project_id ?: ( ($this->framework->getProjectId()) ?: NULL );

        $get_data = [
            'project_id' => $project_id,
            //'return_format' => 'json',
            'return_format' => 'array',
            'records' => $record_id,
            'fields' => NULL,
            'events' => $source_event_id
        ];

        // get record for selected event, swap source_event_id for target_event_id
        $data = REDCap::getData($get_data);
        $data[$record_id][$target_event_id] = $data[$record_id][$source_event_id];
        unset($data[$record_id][$source_event_id]);

        $response = REDCap::saveData($project_id, 'array', $data, 'normal');
        // TODO: parse response, use as flag for deletion

        return json_encode($response);

        // Event data is deleted via call to core JS function, deleteEventInstance which wraps \Controller\DataEntryController
        // requires POST and GET data from the record_home page

        //TODO: log event

    }

    function moveForm($source_event_id, $target_event_id, $record_id = NULL, $project_id = NULL, $form_name = NULL, $debug = false) {
        $record_id = $record_id ?: ( ($this->framework->getRecordId()) ?: NULL ); // return in place of NULL causes errors
        $project_id = $project_id ?: ( ($this->framework->getProjectId()) ?: NULL );


        //TODO: sanitize without mysqli_real_escape_string
        $sql = "SELECT a.field_name FROM redcap_metadata as a
            INNER JOIN (SELECT form_name FROM redcap_events_forms WHERE event_id = " . ($source_event_id) .  ")
            as b ON a.form_name = b.form_name
            WHERE a.project_id = " . ($project_id) . "
            AND a.form_name = 'prescreening_survey';";

        $fields = [];
        $result= $this->framework->query($sql);

        while ($row = $result->fetch_assoc()) {
            $fields[] = $row["field_name"];
        }

        $get_data = [
            'project_id' => $project_id,
            'return_format' => 'array',
            //'return_format' => 'json',
            'records' => $record_id,
            'fields' => $fields,
            'events' => $source_event_id
        ];

        $data = REDCap::getData($get_data);

        // get record for selected event, swap source_event_id for target_event_id
        $data[$record_id][$target_event_id] = $data[$record_id][$source_event_id];
        unset($data[$record_id][$source_event_id]);

        if ($debug) {
            print_r("<pre>");
            print_r($source_event_id);
            print_r("\n");
            print_r($sql);
            print_r("\nResults:\n ");
            var_dump($result);
            print_r("\nResults all:\n ");
            var_dump($result->fetch_all());
            print_r("\nFields:\n ");
            var_dump($fields);
            print_r("\nData:\n ");
            var_dump($data);
            print_r("</pre>");
            return;
        }

        $response = REDCap::saveData($project_id, 'array', $data, 'normal');
        // TODO: parse response, use as flag for deletion

        return json_encode($response);

        //TODO: log event

    }
}
