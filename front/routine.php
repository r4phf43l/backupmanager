<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

if (!Session::haveRight("config", UPDATE) && !Session::haveRight('plugin_backupmanager_routine', READ)) {
    Html::displayRightError();
    exit;
}

Html::header(
    PluginBackupmanagerRoutine::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard',
    'routine'
);

$scheduleUrl = Plugin::getWebDir('backupmanager') . '/front/schedule.php';
echo "<div style='display:flex;justify-content:flex-end;margin-bottom:10px;padding:0 10px'>";
echo "<a href='" . htmlspecialchars($scheduleUrl) . "' class='btn btn-secondary'>";
echo "<i class='ti ti-calendar-stats me-1'></i>";
echo __('Schedule Board', 'backupmanager');
echo "</a>";
echo "</div>";

Search::show('PluginBackupmanagerRoutine');
Html::footer();
