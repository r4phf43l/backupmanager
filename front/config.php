<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

if (!Session::haveRight('config', READ)) {
    Html::displayRightError();
    exit;
}

// Processar POST antes de qualquer output
if (isset($_POST['update_webhook_config'])) {
    // DEBUG TEMPORARIO
    // file_put_contents('/tmp/glpi_csrf_debug.txt', 
    //     "POST token: " . ($_POST['_glpi_csrf_token'] ?? 'AUSENTE') . "\n" .
    //     "SESSION tokens: " . json_encode(array_keys($_SESSION['glpicsrftokens'] ?? [])) . "\n" .
    //     "update_webhook_config: " . ($_POST['update_webhook_config'] ?? 'AUSENTE') . "\n"
    // );
    // Session::checkCSRF($_POST);

    PluginBackupmanagerConfig::setConfig(
        'webhook_enabled',
        empty($_POST['webhook_enabled']) ? '0' : '1'
    );
    PluginBackupmanagerConfig::setConfig(
        'webhook_ip_allow',
        trim(strip_tags($_POST['webhook_ip_allow'] ?? ''))
    );

    if (!empty($_POST['regenerate_token'])) {
        PluginBackupmanagerConfig::setConfig(
            'webhook_token',
            PluginBackupmanagerConfig::generateToken()
        );
    } elseif (!empty($_POST['webhook_token'])) {
        $t = trim($_POST['webhook_token']);
        if (preg_match('/^[a-zA-Z0-9+\/=_\-]{20,128}$/', $t)) {
            PluginBackupmanagerConfig::setConfig('webhook_token', $t);
        }
    }

    Session::addMessageAfterRedirect(
        __('Configuration saved.', 'backupmanager'), true, INFO
    );
    Html::back();
    exit;
}

Html::header(
    __('BackupManager Config', 'backupmanager'),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginBackupmanagerDashboard',
    'config'
);

PluginBackupmanagerConfig::showWebhookConfig();

Html::footer();
