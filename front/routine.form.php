<?php
/**
 * front/routine.form.php
 * BackupManager Plugin — CRUD de Rotinas
 */
include('../../../inc/includes.php');
Session::checkLoginUser();

$obj = new PluginBackupmanagerRoutine();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : -1;

if (isset($_POST['add'])) {
    if (!Session::haveRight("config", UPDATE)) {
        $obj->check(-1, CREATE, $_POST);
    }
    $newID = $obj->add($_POST);
    Html::redirect($obj->getFormURL() . '?id=' . $newID);

} elseif (isset($_POST['update'])) {
    if (!Session::haveRight("config", UPDATE)) {
        $obj->check((int)$_POST['id'], UPDATE, $_POST);
    }
    $obj->update($_POST);
    Html::back();

} elseif (isset($_POST['delete'])) {
    if (!Session::haveRight("config", UPDATE)) {
        $obj->check((int)$_POST['id'], DELETE, $_POST);
    }
    $obj->delete($_POST);
    $obj->redirectToList();

} elseif (isset($_POST['purge'])) {
    if (!Session::haveRight("config", UPDATE)) {
        $obj->check((int)$_POST['id'], PURGE, $_POST);
    }
    $obj->delete($_POST, 1);
    $obj->redirectToList();

} else {
    if (!Session::haveRight("config", UPDATE)) {
        $obj->check($id, READ);
    }
}

Html::header(
    PluginBackupmanagerRoutine::getTypeName(1),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard',
    'routine'
);

$obj->display(['id' => $id]);

Html::footer();