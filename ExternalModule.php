<?php

namespace MDOE\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;

class ExternalModule extends AbstractExternalModule {

    function redcap_every_page_top($project_id) {
        // __DIR__ . '/migratedataphp'; does not work due to VM symlink(?)

        if (PAGE != 'DataEntry/record_home.php' || !$_REQUEST['id']) return;
        $ajax_page = json_encode($this->framework->getUrl("migratedata.php"));

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

    // Event is deleted via call to core JS function, deleteEventInstance which wraps \Controller\DataEntryController
    // requires POST and GET data from the record_home page

    }
}
