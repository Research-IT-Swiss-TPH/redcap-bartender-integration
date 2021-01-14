<?php
/** @var \STPH\Bartender\Bartender $module */

if ($_REQUEST['type'] == 'testAsync') {
    $module->testAsync();
}

elseif ($_REQUEST['type'] == 'triggerFileIntegration') {
    $module->triggerFileIntegration(
        $_POST["requestData"]
    );
}

elseif ($_REQUEST['type'] == 'triggerWebIntegration') {
    $module->triggerWebIntegration(
        $_POST["requestData"]
    );
}

elseif ($_REQUEST['type'] == 'downloadCSV') {
    $module->getPrintJobData(
        $_POST["project_id"],
        $_POST["record_id"],
        $_POST["job_id"],
        $_POST["printer_id"],
        $_POST["copies"]
    );
}

