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
        echo '<script src="' . $this->framework->getUrl($path) . '">';
    }

    function moveEvent($from_event_id, $target_event_id, $record_id = NULL) {
    $record_id = $record_id ?: ( ($this->framework->getRecordId()) ?: "failed" ); // return in place of "failed" causes error

    $sql = "UPDATE redcap_data SET event_id = {$target_event_id} WHERE project_id = " . $this->framework->getProjectId() . " " .
    "AND event_id = {$from_event_id} " .
    "AND record = {$record_id};";
    $response = $this->framework->query($sql);
    // TODO: parse response for errors
    }
}
