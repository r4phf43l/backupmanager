<?php
/**
 * PluginBackupmanagerServer
 * Servidores de Backup - vinculados a ativos GLPI (Computer, VM, etc.)
 */
if (!defined('GLPI_ROOT')) { die("Sorry. You can't access this file directly"); }

class PluginBackupmanagerServer extends CommonDBTM {

    static $rightname = 'plugin_backupmanager_server';

    function getEmpty() {
        parent::getEmpty();
        $this->fields += [
            'itemtype'               => '',
            'items_id'               => 0,
            'ip_address'             => '',
            'hostname'               => '',
            'os_type'                => '',
            'backup_software'        => '',
            'backup_software_version'=> '',
            'retention_days'         => 30,
            'last_backup_date'       => null,
            'last_backup_status'     => '',
            'encryption_enabled'     => 0,
            'encryption_algorithm'   => 'aes256',
            'users_id_tech'          => 0,
            'groups_id_tech'         => 0,
            'comment'                => '',
            'is_active'              => 1,
        ];
    }

    const BACKUP_STATUS_SUCCESS = 'success';
    const BACKUP_STATUS_FAILED  = 'failed';
    const BACKUP_STATUS_UNKNOWN = 'unknown';
    const BACKUP_STATUS_RUNNING = 'running';

    static function getTypeName($nb = 0) {
        return _n('Backup Server', 'Backup Servers', $nb, 'backupmanager');
    }

    static function canCreate()  { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, CREATE); }
    static function canView()    { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, READ); }
    static function canUpdate()  { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, UPDATE); }
    static function canDelete()  { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, DELETE); }
    static function canPurge()   { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, PURGE); }

    function defineTabs($options = []) {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('PluginBackupmanagerRoutine', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    function showForm($ID, $options = []) {
        global $CFG_GLPI;

        if (!static::canView()) {
            return false;
        }

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Nome + Ativo
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name', 'backupmanager') . "</td>";
        echo "<td>";
        echo Html::input('name', ['value' => $this->fields['name']]);
        echo "</td>";
        echo "<td>" . __('Active') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active'] ?? 1);
        echo "</td></tr>";

        // Linked Asset — dois dropdowns separados com reload via JS
        $current_itemtype = $this->fields['itemtype'] ?: 'Computer';
        $current_items_id = (int)($this->fields['items_id'] ?? 0);
        $types = [
            'Computer'         => Computer::getTypeName(1),
            'NetworkEquipment' => NetworkEquipment::getTypeName(1),
        ];

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Linked Asset (Computer/VM)', 'backupmanager') . "</td>";
        echo "<td colspan='3'>";

        // Dropdown tipo
        Dropdown::showFromArray('itemtype', $types, [
            'value'     => $current_itemtype,
            'on_change' => 'bm_reloadItemDropdown(this.value)',
        ]);

        echo "&nbsp;";

        // Container para o dropdown de item
        echo "<span id='bm_items_id_container'>";
        Dropdown::show($current_itemtype, [
            'name'  => 'items_id',
            'value' => $current_items_id,
        ]);
        echo "</span>";

        // JS para recarregar o dropdown de item ao mudar o tipo
        $root = $CFG_GLPI['root_doc'];
        echo Html::scriptBlock(
            "function bm_reloadItemDropdown(itemtype) {" .
            "  if (!itemtype) return;" .
            "  $.ajax({" .
            "    url: '{$root}/ajax/dropdownAllItems.php'," .
            "    type: 'POST'," .
            "    data: { itemtype: itemtype, myname: 'items_id', entity_restrict: -1, checkright: 1 }," .
            "    success: function(data) {" .
            "      $('#bm_items_id_container').html(data);" .
            "    }" .
            "  });" .
            "}"
        );

        echo "</td></tr>";

        // IP + OS
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('IP Address', 'backupmanager') . "</td>";
        echo "<td>";
        echo Html::input('ip_address', ['value' => $this->fields['ip_address'] ?? '', 'size' => 20]);
        echo "</td>";
        echo "<td>" . __('OS Type', 'backupmanager') . "</td>";
        echo "<td>";
        $os_types = ['linux' => 'Linux', 'windows' => 'Windows',
                     'unix' => 'Unix/BSD', 'other' => __('Other')];
        Dropdown::showFromArray('os_type', $os_types, ['value' => $this->fields['os_type'] ?? '']);
        echo "</td></tr>";

        // Software + Versão
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Backup Software', 'backupmanager') . "</td>";
        echo "<td>";
        echo Html::input('backup_software', ['value' => $this->fields['backup_software'] ?? '']);
        echo "</td>";
        echo "<td>" . __('Version', 'backupmanager') . "</td>";
        echo "<td>";
        echo Html::input('backup_software_version', ['value' => $this->fields['backup_software_version'] ?? '', 'size' => 15]);
        echo "</td></tr>";

        // Técnico + Grupo
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Technician in charge', 'backupmanager') . "</td>";
        echo "<td>";
        User::dropdown(['name' => 'users_id_tech', 'value' => $this->fields['users_id_tech'] ?? 0, 'right' => 'interface']);
        echo "</td>";
        echo "<td>" . __('Tech Group', 'backupmanager') . "</td>";
        echo "<td>";
        Dropdown::show('Group', ['name' => 'groups_id_tech', 'value' => $this->fields['groups_id_tech'] ?? 0, 'condition' => ['is_assign' => 1]]);
        echo "</td></tr>";

        // Retenção + Status
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Retention (days)', 'backupmanager') . "</td>";
        echo "<td>";
        echo Html::input('retention_days', ['type' => 'number', 'value' => $this->fields['retention_days'] ?? 30, 'min' => 1, 'max' => 3650]);
        echo "</td>";
        echo "<td>" . __('Last Backup Status', 'backupmanager') . "</td>";
        echo "<td>";
        $statuses = [
            self::BACKUP_STATUS_SUCCESS => __('Success', 'backupmanager'),
            self::BACKUP_STATUS_FAILED  => __('Failed', 'backupmanager'),
            self::BACKUP_STATUS_UNKNOWN => __('Unknown', 'backupmanager'),
            self::BACKUP_STATUS_RUNNING => __('Running', 'backupmanager'),
        ];
        Dropdown::showFromArray('last_backup_status', $statuses, ['value' => $this->fields['last_backup_status'] ?? '']);
        echo "</td></tr>";

        // Criptografia
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Encryption enabled', 'backupmanager') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('encryption_enabled', $this->fields['encryption_enabled'] ?? 0);
        echo "</td>";
        echo "<td>" . __('Encryption Algorithm', 'backupmanager') . "</td>";
        echo "<td>";
        $algos = ['' => '---', 'AES-256' => 'AES-256', 'AES-128' => 'AES-128', 'ChaCha20' => 'ChaCha20', 'other' => __('Other')];
        Dropdown::showFromArray('encryption_algorithm', $algos, ['value' => $this->fields['encryption_algorithm'] ?? '']);
        echo "</td></tr>";

        // Comentários
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Comments') . "</td>";
        echo "<td colspan='3'>";
        echo Html::textarea(['name' => 'comment', 'value' => $this->fields['comment'] ?? '', 'cols' => 80, 'rows' => 4]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    function rawSearchOptions() {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'       => 2,
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active','backupmanager'),
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'backup_software',
            'name'     => __('Software','backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'last_backup_status',
            'name'     => __('Last Backup Status','backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 5,
            'table'    => $this->getTable(),
            'field'    => 'retention_days',
            'name'     => __('Retention (days)','backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'       => 6,
            'table'    => $this->getTable(),
            'field'    => 'encryption_enabled',
            'name'     => __('Encryption','backupmanager'),
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'       => 7,
            'table'    => $this->getTable(),
            'field'    => 'encryption_algorithm',
            'name'     => __('Algotithm','backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'        => 8,
            'table'     => 'glpi_users',
            'field'     => 'name',
            'name'      => __('Technician','backupmanager'),
            'datatype'  => 'dropdown',
            'linkfield' => 'users_id_tech'
        ];

        return $tab;
    }    

    function getSearchOptions() {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
        $tab[] = ['id' => 1, 'table' => $this->getTable(), 'field' => 'name', 'name' => __('Name'), 'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => $this->getTable(), 'field' => 'ip_address', 'name' => __('IP Address', 'backupmanager'), 'datatype' => 'string'];
        $tab[] = ['id' => 3, 'table' => $this->getTable(), 'field' => 'last_backup_status', 'name' => __('Last Status', 'backupmanager'), 'datatype' => 'string'];
        $tab[] = ['id' => 4, 'table' => $this->getTable(), 'field' => 'last_backup_date', 'name' => __('Last Backup Date', 'backupmanager'), 'datatype' => 'datetime'];
        $tab[] = ['id' => 5, 'table' => 'glpi_users', 'field' => 'name', 'name' => __('Technician', 'backupmanager'), 'datatype' => 'dropdown', 'linkfield' => 'users_id_tech'];
        $tab[] = ['id' => 6, 'table' => $this->getTable(), 'field' => 'encryption_enabled', 'name' => __('Encrypted', 'backupmanager'), 'datatype' => 'bool'];
        $tab[] = ['id' => 7, 'table' => $this->getTable(), 'field' => 'is_active', 'name' => __('Active'), 'datatype' => 'bool'];
        return $tab;
    }

    function prepareInputForAdd($input) {
        return $this->prepareInput($input);
    }

    function prepareInputForUpdate($input) {
        return $this->prepareInput($input);
    }

    private function prepareInput($input) {
        if (isset($input['ip_address'])) {
            $ip = trim($input['ip_address']);
            if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)
                && !preg_match('/^[a-zA-Z0-9\-\.]+$/', $ip)) {
                Session::addMessageAfterRedirect(
                    __('Invalid IP address or hostname.', 'backupmanager'), false, ERROR);
                return false;
            }
            $input['ip_address'] = Toolbox::addslashes_deep($ip);
        }

        if (isset($input['retention_days'])) {
            $input['retention_days'] = (int)$input['retention_days'];
            if ($input['retention_days'] < 1) $input['retention_days'] = 1;
        }

        return $input;
    }
}