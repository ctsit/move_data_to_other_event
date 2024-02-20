<?php

$migrating = $_REQUEST["migrating"];
$form_names = $_REQUEST["formNames"];
$source_event_id = $_REQUEST["sourceEventId"];
$target_event_id = $_REQUEST["targetEventId"];
$record_id = $_REQUEST["recordId"];
$project_id = $_REQUEST["projectId"];
$delete_source_data = $_REQUEST["deleteSourceData"];

//TODO: consider elimination of this switch if ajaxMoveEvent will never deliver values other than "event"
switch ($migrating) {
    case 'event':
        $response = $module->moveEvent($source_event_id, $target_event_id, $record_id, $project_id, $form_names, $delete_source_data == "true");
        break;
    case 'field':
        echo "not implemented";
        break;
}

echo $response;

?>
