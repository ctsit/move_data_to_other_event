<?php

namespace MDOE\ExternalModule;
require_once(__DIR__ . '/ExternalModule.php');

$from_event_id = $_REQUEST["fromEventId"];
$target_event_id = $_REQUEST["targetEventId"];
$record_id = $_REQUEST["recordId"];

$EM = new ExternalModule();
$EM->moveEvent($from_event_id, $target_event_id, $record_id);

?>
