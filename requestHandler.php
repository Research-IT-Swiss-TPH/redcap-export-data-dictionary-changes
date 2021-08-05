<?php
/** @var  STPH\exportDataDictionaryChanges\exportDataDictionaryChanges $module */

//  This file serves as a handler for all Front-end Ajax Requests

if ($_REQUEST['action'] == 'toggleActive') {

    $checked = htmlspecialchars($_POST["checked"]);
    $module->handleToggleExportActive($checked);

}
elseif($_REQUEST['action'] == 'getActiveState') {
    $module->handleGetExportActive();
}
elseif($_REQUEST['action'] == 'download') {
    $module->handleDownload();
}

else {
    header("HTTP/1.1 400 Bad Request");
    header('Content-Type: application/json; charset=UTF-8');    
    die("The action does not exist.");
}