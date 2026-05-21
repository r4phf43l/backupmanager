<?php
/**
 * PluginBackupmanagerDestination
 * Destinos de Armazenamento de Backup
 */
if (!defined('GLPI_ROOT')) { die("Sorry. You can't access this file directly"); }

class PluginBackupmanagerDestination extends CommonDBTM {

    static $rightname = 'plugin_backupmanager_destination';

    const CONTENT_VM          = 'vm_backup';
    const CONTENT_FILES       = 'file_backup';
    const CONTENT_RSYNC       = 'rsync';
    const CONTENT_SERVER_SYNC = 'server_sync';

    static function getTypeName($nb = 0) {
        return _n('Storage Destination', 'Storage Destinations', $nb, 'backupmanager');
    }

    static function canCreate() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, CREATE); }
    static function canView()   { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, READ); }
    static function canUpdate() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, UPDATE); }
    static function canDelete() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, DELETE); }
    static function canPurge()  { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, PURGE); }

    static function getExpectedContentTypes() {
        return [
            self::CONTENT_VM          => __('Backup VM', 'backupmanager'),
            self::CONTENT_FILES       => __('Backup de Arquivos', 'backupmanager'),
            self::CONTENT_RSYNC       => __('Rsync', 'backupmanager'),
            self::CONTENT_SERVER_SYNC => __('Sync de servidores', 'backupmanager'),
        ];
    }

    function getEmpty() {
        parent::getEmpty();
        $this->fields += [
            'name'                  => '',
            'itemtype'              => '',
            'items_id'              => 0,
            'destination_type'      => 'local',
            'expected_content_type' => self::CONTENT_FILES,
            'host'                  => '',
            'port'                  => 0,
            'path'                  => '',
            'username'              => '',
            'location'              => '',
            'protocol'              => '',
            'capacity_gb'           => 0,
            'used_gb'               => 0,
            'redundancy_type'       => 'none',
            'users_id_tech'         => 0,
            'encryption_enabled'    => 0,
            'encryption_algorithm'  => 'aes256',
            'is_offsite'            => 0,
            'is_active'             => 1,
            'comment'               => '',
        ];
    }

    function defineTabs($options = []) {
        $ong = [];
        $this->addDefaultFormTab($ong);
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

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td><td>";
        echo Html::input('name', ['value' => ($this->fields['name'] ?? '')]);
        echo "</td>";
        echo "<td>" . __('Active') . "</td><td>";
        Dropdown::showYesNo('is_active', ($this->fields['is_active'] ?? 1));
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Destination Type', 'backupmanager') . "</td><td>";
        $types = [
            'local' => __('Local Disk', 'backupmanager'),
            'nfs'   => 'NFS Share',
            'smb'   => 'SMB/CIFS Share',
            's3'    => 'Amazon S3 / Compatible',
            'sftp'  => 'SFTP',
            'tape'  => __('Tape Library', 'backupmanager'),
            'cloud' => __('Other Cloud', 'backupmanager')
        ];
        Dropdown::showFromArray('destination_type', $types, [
            'value' => $this->fields['destination_type'] ?? 'local'
        ]);
        echo "</td>";

        echo "<td>" . __('Conteúdo esperado', 'backupmanager') . "</td><td>";
        Dropdown::showFromArray('expected_content_type', self::getExpectedContentTypes(), [
            'value' => $this->fields['expected_content_type'] ?? self::CONTENT_FILES
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Linked Asset', 'backupmanager') . "</td><td colspan='3'>";
        $asset_types = [
            'Computer'         => Computer::getTypeName(1),
            'NetworkEquipment' => NetworkEquipment::getTypeName(1)
        ];
        $current_itemtype = $this->fields['itemtype'] ?: 'Computer';
        $current_items_id = (int)($this->fields['items_id'] ?? 0);

        Dropdown::showFromArray('itemtype', $asset_types, [
            'value'     => $current_itemtype,
            'on_change' => 'bmDestinationReloadItems(this.value)',
        ]);
        echo "&nbsp;";
        echo "<span id='bm_destination_items_id_container'>";
        Dropdown::show($current_itemtype, [
            'name'  => 'items_id',
            'value' => $current_items_id,
        ]);
        echo "</span>";

        $root = $CFG_GLPI['root_doc'];
        echo Html::scriptBlock(
            "function bmDestinationReloadItems(itemtype) {" .
            "  if (!itemtype) return;" .
            "  $.ajax({" .
            "    url: '{$root}/ajax/dropdownAllItems.php'," .
            "    type: 'POST'," .
            "    data: { itemtype: itemtype, myname: 'items_id', entity_restrict: -1, checkright: 1 }," .
            "    success: function(data) {" .
            "      $('#bm_destination_items_id_container').html(data);" .
            "    }" .
            "  });" .
            "}"
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Host / Endpoint', 'backupmanager') . "</td><td>";
        echo Html::input('host', ['value' => ($this->fields['host'] ?? '')]);
        echo "</td>";
        echo "<td>" . __('Port', 'backupmanager') . "</td><td>";
        echo Html::input('port', ['type' => 'number', 'value' => ($this->fields['port'] ?? 0), 'min' => 0, 'max' => 65535]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Path / Bucket', 'backupmanager') . "</td><td>";
        echo Html::input('path', ['value' => ($this->fields['path'] ?? ''), 'size' => 60]);
        echo "</td>";
        echo "<td>" . __('Username', 'backupmanager') . "</td><td>";
        echo Html::input('username', ['value' => ($this->fields['username'] ?? ''), 'size' => 30]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Physical Location / Datacenter', 'backupmanager') . "</td><td>";
        echo Html::input('location', ['value' => ($this->fields['location'] ?? '')]);
        echo "</td>";
        echo "<td>" . __('Redundancy', 'backupmanager') . "</td><td>";
        $red = [
            'none'           => __('None'),
            'raid'           => 'RAID/Local redundancy',
            'geo-replicated' => __('Geo-replicated', 'backupmanager')
        ];
        Dropdown::showFromArray('redundancy_type', $red, [
            'value' => $this->fields['redundancy_type'] ?? 'none'
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Total Capacity (GB)', 'backupmanager') . "</td><td>";
        echo Html::input('capacity_gb', ['type' => 'number', 'step' => '0.01', 'value' => ($this->fields['capacity_gb'] ?? 0)]);
        echo "</td>";
        echo "<td>" . __('Used Space (GB)', 'backupmanager') . "</td><td>";
        echo Html::input('used_gb', ['type' => 'number', 'step' => '0.01', 'value' => ($this->fields['used_gb'] ?? 0)]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Encryption at rest', 'backupmanager') . "</td><td>";
        Dropdown::showYesNo('encryption_enabled', ($this->fields['encryption_enabled'] ?? 0));
        echo "</td>";
        echo "<td>" . __('Technician', 'backupmanager') . "</td><td>";
        User::dropdown([
            'name'  => 'users_id_tech',
            'value' => $this->fields['users_id_tech'] ?? 0,
            'right' => 'interface'
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('Comments') . "</td><td colspan='3'>";
        echo Html::textarea([
            'name'  => 'comment',
            'value' => ($this->fields['comment'] ?? ''),
            'cols'  => 80,
            'rows'  => 3
        ]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    static function getSpecificValueToDisplay($field, $values, array $options = []) {
        if ($field === 'items_id') {
            $itemtype = $values['itemtype'] ?? '';
            $items_id = (int)($values[$field] ?? 0);
            if ($items_id > 0 && !empty($itemtype) && class_exists($itemtype)) {
                $item = new $itemtype();
                if ($item->getFromDB($items_id)) {
                    return $item->getLink();
                }
            }
            return '—';
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    function rawSearchOptions() {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'       => 100,
            'table'    => $this->getTable(),
            'field'    => 'destination_type',
            'name'     => __('Type', 'backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 101,
            'table'    => $this->getTable(),
            'field'    => 'expected_content_type',
            'name'     => __('Conteúdo esperado', 'backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 102,
            'table'    => $this->getTable(),
            'field'    => 'host',
            'name'     => __('Host', 'backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 103,
            'table'    => $this->getTable(),
            'field'    => 'capacity_gb',
            'name'     => __('Capacity (GB)', 'backupmanager'),
            'datatype' => 'decimal'
        ];
        $tab[] = [
            'id'       => 104,
            'table'    => $this->getTable(),
            'field'    => 'encryption_enabled',
            'name'     => __('Encrypted', 'backupmanager'),
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'        => 105,
            'table'     => 'glpi_users',
            'field'     => 'name',
            'name'      => __('Technician', 'backupmanager'),
            'datatype'  => 'dropdown',
            'linkfield' => 'users_id_tech'
        ];
        $tab[] = [
            'id'       => 106,
            'table'    => $this->getTable(),
            'field'    => 'path',
            'name'     => __('Path / Bucket', 'backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 107,
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active', 'backupmanager'),
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'       => 108,
            'table'    => $this->getTable(),
            'field'    => 'itemtype',
            'name'     => __('Linked Asset Type', 'backupmanager'),
            'datatype' => 'string'
        ];

        $tab[] = [
            'id'       => 109,
            'table'    => $this->getTable(),
            'field'    => 'items_id',
            'name'     => __('Linked Asset ID', 'backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'            => 110,
            'table'         => $this->getTable(),
            'field'         => 'items_id',
            'name'          => __('Linked Asset', 'backupmanager'),
            'datatype'      => 'specific',
            'additionalfields' => ['itemtype'],  // injeta itemtype em $values
        ];
        return $tab;
    }

    function prepareInputForAdd($input)    { return $this->prepareInput($input); }
    function prepareInputForUpdate($input) { return $this->prepareInput($input); }

    private function prepareInput($input) {
        if (isset($input['port'])) {
            $input['port'] = max(0, min(65535, (int)$input['port']));
        }

        if (isset($input['host'])) {
            $input['host'] = Toolbox::addslashes_deep(trim($input['host']));
        }

        if (isset($input['capacity_gb'])) {
            $input['capacity_gb'] = (float)$input['capacity_gb'];
            if ($input['capacity_gb'] < 0) {
                $input['capacity_gb'] = 0;
            }
        }

        if (isset($input['used_gb'])) {
            $input['used_gb'] = (float)$input['used_gb'];
            if ($input['used_gb'] < 0) {
                $input['used_gb'] = 0;
            }
        }

        if (isset($input['expected_content_type'])
            && !array_key_exists($input['expected_content_type'], self::getExpectedContentTypes())) {
            $input['expected_content_type'] = self::CONTENT_FILES;
        }

        return $input;
    }
}