<?php
/** @var  STPH\exportDataDictionaryChanges\exportDataDictionaryChanges $module */

//  This file serves as a handler for all Front-end Ajax Requests

if ($_REQUEST['action'] == 'toggleExportActive') {

    $checked = htmlspecialchars($_POST["checked"]);
    $module->handleToggleExportActive($checked);

}
elseif($_REQUEST['action'] == 'getExportActive') {
    $module->handleGetExportActive();
}
elseif($_REQUEST['action'] == 'downloadCSV') {
    $module->handleDownloadCSV();
}

else {
    header("HTTP/1.1 400 Bad Request");
    header('Content-Type: application/json; charset=UTF-8');    
    die("The action does not exist.");
}