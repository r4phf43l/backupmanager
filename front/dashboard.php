<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

// Super-admin tem acesso irrestrito; demais verificam o direito
if (!Session::haveRight("config", UPDATE)
    && !Session::haveRight('plugin_backupmanager_dashboard', READ)) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginBackupmanagerDashboard::getMenuName(),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard'
);

PluginBackupmanagerDashboard::showDashboard();
Html::footer();
