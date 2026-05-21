<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

if (!Session::haveRight("config", UPDATE) && !Session::haveRight('plugin_backupmanager_server', READ)) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginBackupmanagerServer::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard',
    'server'
);
Search::show('PluginBackupmanagerServer');
Html::footer();
