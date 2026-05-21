<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

if (!Session::haveRight("config", UPDATE) && !Session::haveRight('plugin_backupmanager_log', READ)) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginBackupmanagerLog::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard',
    'log'
);

Search::show('PluginBackupmanagerLog');

Html::footer();