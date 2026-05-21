<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

$obj         = new PluginBackupmanagerChecklist();
$id          = isset($_GET['id']) ? (int)$_GET['id'] : -1;
$routines_id = (int)($_GET['plugin_backupmanager_routines_id']
               ?? $_POST['plugin_backupmanager_routines_id'] ?? 0);

if (isset($_POST['add'])) {
    if (!Session::haveRight("config", UPDATE)) $obj->check(-1, CREATE, $_POST);
    $obj->add($_POST);
    Html::redirect(Plugin::getWebDir('backupmanager')
        . '/front/routine.form.php?id=' . $routines_id);

} elseif (isset($_POST['update'])) {
    if (!Session::haveRight("config", UPDATE)) $obj->check((int)$_POST['id'], UPDATE, $_POST);
    $obj->update($_POST);
    Html::back();

} elseif (isset($_POST['delete'])) {
    if (!Session::haveRight("config", UPDATE)) $obj->check((int)$_POST['id'], DELETE, $_POST);
    $obj->delete($_POST);
    Html::redirect(Plugin::getWebDir('backupmanager')
        . '/front/routine.form.php?id=' . $routines_id);

} else {
    if (!Session::haveRight("config", UPDATE)) $obj->check($id, READ);
}

Html::header(
    PluginBackupmanagerChecklist::getTypeName(1),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard',
    'routine'
);
$obj->display([
    'id'                               => $id,
    'plugin_backupmanager_routines_id' => $routines_id,
]);
Html::footer();
