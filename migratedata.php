<?php

namespace MDOE\ExternalModule;
require_once(__DIR__ . '/ExternalModule.php');

$migrating = $_REQUEST["migrating"];
$form_name = $_REQUEST["form_name"];
$source_event_id = $_REQUEST["sourceEventId"];
$target_event_id = $_REQUEST["targetEventId"];
$record_id = $_REQUEST["recordId"];
$project_id = $_REQUEST["projectId"];

$EM = new ExternalModule();

switch ($migrating) {
    case 'event':
        $response = $EM->moveEvent($source_event_id, $target_event_id, $record_id, $project_id);
        break;
    case 'form':
        echo "not implemented";
        $response = $EM->moveForm($source_event_id, $target_event_id, $record_id, $project_id, $form_name, $debug = true);
        break;
    case 'field':
        echo "not implemented";
        break;
}

echo $response;

?>
