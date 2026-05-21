<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

if (!Session::haveRight("config", UPDATE) && !Session::haveRight('plugin_backupmanager_destination', READ)) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginBackupmanagerDestination::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard',
    'destination'
);

Search::show('PluginBackupmanagerDestination');
Html::footer();