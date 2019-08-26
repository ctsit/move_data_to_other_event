<?php

namespace MDOE\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;

class ExternalModule extends AbstractExternalModule {

    function redcap_every_page_top($project_id) {
        echo ('<script> var ajaxpage = "' . __DIR__ . '/migratedata.php" </script>');
        include('div.html');
        $this->includeJs('js/mdoe.js');
    }

    function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '">';
    }

    function moveEvent() {
    $new_id = 127;
    $sql = "UPDATE redcap_data SET event_id = " . $new_id . " WHERE AND project_id = '" . PROJECT_ID .
    "AND event_id = 126";
    print_r($sql);
    print_r("attempt to moveForm");
    }
}
