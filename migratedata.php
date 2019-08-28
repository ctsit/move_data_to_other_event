<?php

namespace MDOE\ExternalModule;
require_once(__DIR__ . '/ExternalModule.php');

$source_event_id = $_REQUEST["sourceEventId"];
$target_event_id = $_REQUEST["targetEventId"];
$record_id = $_REQUEST["recordId"];
$project_id = $_REQUEST["projectId"];

$EM = new ExternalModule();
$response = $EM->moveEvent($source_event_id, $target_event_id, $record_id, $project_id);

echo $response;

?>
