<?php
/**
 * BackupManager Plugin — setup.php
 * GLPI 10.x
 * ATENÇÃO: NÃO usar die() ou exit() aqui — o GLPI inclui este arquivo
 *          dentro de uma closure; qualquer die() retorna false e o plugin
 *          não é reconhecido.
 */
define('BACKUPMANAGER_VERSION',  '1.0.3');
define('BACKUPMANAGER_MIN_GLPI', '10.0.0');
define('BACKUPMANAGER_MAX_GLPI', '10.0.99');

function plugin_version_backupmanager() {
    return [
        'name'         => 'Backup Manager',
        'version'      => BACKUPMANAGER_VERSION,
        'author'       => 'Rafael Antonio',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => BACKUPMANAGER_MIN_GLPI,
                'max' => BACKUPMANAGER_MAX_GLPI,
            ],
            'php' => ['min' => '8.0.0'],
        ],
    ];
}

function plugin_backupmanager_check_prerequisites() {
    if (version_compare(GLPI_VERSION, BACKUPMANAGER_MIN_GLPI, 'lt')) {
        if (method_exists('Plugin', 'messageIncompatible')) {
            Plugin::messageIncompatible('core', BACKUPMANAGER_MIN_GLPI);
        }
        return false;
    }
    return true;
}

function plugin_backupmanager_check_config($verbose = false) {
    return true;
}

function plugin_init_backupmanager() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['backupmanager'] = true;

    $PLUGIN_HOOKS['menu_toadd']['backupmanager'] = [
        'management' => 'PluginBackupmanagerDashboard',
    ];

    Plugin::registerClass('PluginBackupmanagerProfile', [
        'addtabon' => ['Profile'],
    ]);
    Plugin::registerClass('PluginBackupmanagerServer', [
        'addtabon' => ['Computer', 'NetworkEquipment'],
    ]);
    Plugin::registerClass('PluginBackupmanagerDestination', [
        'addtabon' => ['Computer', 'NetworkEquipment'],
    ]);
    Plugin::registerClass('PluginBackupmanagerRoutine');
    Plugin::registerClass('PluginBackupmanagerChecklist');
    Plugin::registerClass('PluginBackupmanagerLog');
    Plugin::registerClass('PluginBackupmanagerDashboard');

    $PLUGIN_HOOKS['pre_item_update']['backupmanager'] = [
        'Profile' => ['PluginBackupmanagerProfile', 'preItemUpdate'],
    ];

    if (isset($_SESSION['glpiactiveentities'])) {
        $PLUGIN_HOOKS['add_css']['backupmanager']        = ['css/backupmanager.css'];
        $PLUGIN_HOOKS['add_javascript']['backupmanager'] = ['js/backupmanager.js'];
    }

    $PLUGIN_HOOKS['config_page']['backupmanager'] = 'front/dashboard.php';
}
