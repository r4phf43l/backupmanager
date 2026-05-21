<?php
/**
 * PluginBackupmanagerRoutine
 * Rotinas de Backup com RTO, RPO, Técnico Responsável e Conformidade
 * References: ISO/IEC 27001:2022 A.8.13, CIS Control 11, NIS2 Art.21, OWASP Top 10
 */
if (!defined('GLPI_ROOT')) { die("Sorry. You can't access this file directly"); }

class PluginBackupmanagerRoutine extends CommonDBTM {

    static $rightname = 'plugin_backupmanager_routine';

    const SOURCE_DB    = 'db';
    const SOURCE_VM    = 'vm';
    const SOURCE_FILES = 'files';
    const SOURCE_APP   = 'app';
    const SOURCE_MIXED = 'mixed';
    const SOURCE_LXC = 'lxc';

    const PRIORITY_CRITICAL = 1;
    const PRIORITY_HIGH     = 2;
    const PRIORITY_MEDIUM   = 3;
    const PRIORITY_LOW      = 4;

    const TYPE_FULL         = 'full';
    const TYPE_INCREMENTAL  = 'incremental';
    const TYPE_DIFFERENTIAL = 'differential';
    const TYPE_SNAPSHOT     = 'snapshot';

    static function getTypeName($nb = 0) {
        return _n('Backup Routine', 'Backup Routines', $nb, 'backupmanager');
    }

    static function getSourceItemtypes() {
        return [
            'Computer'         => Computer::getTypeName(1),
            'NetworkEquipment' => NetworkEquipment::getTypeName(1),
            'Printer'          => Printer::getTypeName(1),
        ];
    }

    static function canCreate() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, CREATE); }
    static function canView()   { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, READ); }
    static function canUpdate() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, UPDATE); }
    static function canDelete() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, DELETE); }
    static function canPurge()  { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, PURGE); }

    function getEmpty() {
        parent::getEmpty();
        $this->fields += [
            'name'                                 => '',
            'backup_type'                          => self::TYPE_FULL,
            'source_type'                          => self::SOURCE_FILES,
            'source_itemtype'                      => 'Computer',
            'source_items_id'                      => 0,
            'priority'                             => self::PRIORITY_MEDIUM,
            'rto_minutes'                          => 60,
            'rpo_hours'                            => 24,
            'retention_copies'                     => 7,
            'retention_days'                       => 30,
            'schedule_cron'                        => '',
            'schedule_description'                 => '',
            'compression_enabled'                  => 1,
            'compression_algorithm'                => 'gzip',
            'verification_enabled'                 => 1,
            'notification_on_failure'              => 1,
            'compliance_iso27001'                  => 0,
            'compliance_nis2'                      => 0,
            'compliance_cis'                       => 0,
            'users_id_tech'                        => 0,
            'groups_id_tech'                       => 0,
            'plugin_backupmanager_servers_id'      => 0,
            'plugin_backupmanager_destinations_id' => 0,
            'is_active'                            => 1,
            'comment'                              => '',
        ];
    }

    static function getPriorityLabel($priority) {
        $map = [
            self::PRIORITY_CRITICAL => ['label' => 'Critical', 'class' => 'badge bg-danger'],
            self::PRIORITY_HIGH     => ['label' => 'High',     'class' => 'badge bg-warning text-dark'],
            self::PRIORITY_MEDIUM   => ['label' => 'Medium',   'class' => 'badge bg-info text-dark'],
            self::PRIORITY_LOW      => ['label' => 'Low',      'class' => 'badge bg-secondary'],
        ];
        $p = $map[$priority] ?? $map[self::PRIORITY_MEDIUM];
        return "<span class='{$p['class']}'>{$p['label']}</span>";
    }

    static function getSourceTypeLabel($source_type) {
        $map = [
            self::SOURCE_DB    => ['label' => 'Database',    'class' => 'badge bg-primary'],
            self::SOURCE_VM    => ['label' => 'VM',          'class' => 'badge bg-success'],
            self::SOURCE_FILES => ['label' => 'Files',       'class' => 'badge bg-secondary'],
            self::SOURCE_APP   => ['label' => 'Application', 'class' => 'badge bg-info text-dark'],
            self::SOURCE_MIXED => ['label' => 'Mixed',       'class' => 'badge bg-warning text-dark'],
            self::SOURCE_LXC   => ['label' => 'LXC',         'class' => 'badge bg-warning text-dark'],
        ];
        $s = $map[$source_type] ?? ['label' => $source_type ?: '---', 'class' => 'badge bg-secondary'];
        return "<span class='{$s['class']}'>{$s['label']}</span>";
    }

    function defineTabs($options = []) {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('PluginBackupmanagerChecklist', $ong, $options);
        $this->addStandardTab('PluginBackupmanagerLog',       $ong, $options);
        $this->addStandardTab('Log',                          $ong, $options);
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
        echo "<td>".__('Name')."</td><td>";
        echo Html::input('name', ['value' => ($this->fields['name'] ?? '')]);
        echo "</td><td>".__('Active')."</td><td>";
        Dropdown::showYesNo('is_active', ($this->fields['is_active'] ?? 1));
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Priority','backupmanager')."</td><td>";
        Dropdown::showFromArray('priority', [
            self::PRIORITY_CRITICAL => __('Critical','backupmanager'),
            self::PRIORITY_HIGH     => __('High','backupmanager'),
            self::PRIORITY_MEDIUM   => __('Medium','backupmanager'),
            self::PRIORITY_LOW      => __('Low','backupmanager'),
        ], ['value' => ($this->fields['priority'] ?? self::PRIORITY_MEDIUM)]);
        echo "</td><td>".__('Backup Type','backupmanager')."</td><td>";
        Dropdown::showFromArray('backup_type', [
            self::TYPE_FULL         => __('Full','backupmanager'),
            self::TYPE_INCREMENTAL  => __('Incremental','backupmanager'),
            self::TYPE_DIFFERENTIAL => __('Differential','backupmanager'),
            self::TYPE_SNAPSHOT     => __('Snapshot','backupmanager'),
        ], ['value' => ($this->fields['backup_type'] ?? self::TYPE_FULL)]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4'><strong><i class='ti ti-database-import me-1'></i>"
            . __('Backup Source', 'backupmanager') . "</strong></td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Source Type','backupmanager')."</td><td>";
        Dropdown::showFromArray('source_type', [
            self::SOURCE_DB    => __('Database (DB)','backupmanager'),
            self::SOURCE_VM    => __('Virtual Machine (VM)','backupmanager'),
            self::SOURCE_FILES => __('Files / Directories','backupmanager'),
            self::SOURCE_APP   => __('Application','backupmanager'),
            self::SOURCE_MIXED => __('Mixed','backupmanager'),
            self::SOURCE_LXC   => __('LXC','backupmanager'),
        ], ['value' => ($this->fields['source_type'] ?? self::SOURCE_FILES)]);
        echo "</td><td>".__('Source Asset Type','backupmanager')."</td><td>";

        $source_itemtype = $this->fields['source_itemtype'] ?? 'Computer';
        $source_items_id = (int)($this->fields['source_items_id'] ?? 0);
        $allowed_itemtypes = array_keys(self::getSourceItemtypes());

        if (!in_array($source_itemtype, $allowed_itemtypes, true)) {
            $source_itemtype = 'Computer';
        }

        Dropdown::showFromArray(
            'source_itemtype',
            self::getSourceItemtypes(),
            [
                'value'     => $source_itemtype,
                'on_change' => "pluginBackupmanagerUpdateSourceItem(this.value)",
            ]
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Source Asset (Equipment)','backupmanager')."</td><td colspan='3'>";
        echo "<span id='plugin_bm_source_item_container'>";

        Dropdown::show($source_itemtype, [
            'name'   => 'source_items_id',
            'value'  => $source_items_id,
            'entity' => $this->fields['entities_id'] ?? 0,
        ]);

        echo "</span>";
        echo "</td></tr>";

        $entity = (int)($this->fields['entities_id'] ?? -1);
        $root   = $CFG_GLPI['root_doc'];

        echo Html::scriptBlock("
            function pluginBackupmanagerUpdateSourceItem(itemtype) {
                if (!itemtype) return;
                $.ajax({
                    url: '{$root}/ajax/dropdownAllItems.php',
                    type: 'POST',
                    data: {
                        itemtype: itemtype,
                        myname: 'source_items_id',
                        entity_restrict: {$entity},
                        checkright: 1
                    },
                    success: function(data) {
                        $('#plugin_bm_source_item_container').html(data);
                    }
                });
            }
        ");

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4'><strong><i class='ti ti-server me-1'></i>"
            . __('Backup Infrastructure', 'backupmanager') . "</strong></td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Backup Server','backupmanager')."</td><td>";
        Dropdown::show('PluginBackupmanagerServer', [
            'name'      => 'plugin_backupmanager_servers_id',
            'value'     => ($this->fields['plugin_backupmanager_servers_id'] ?? 0),
            'condition' => ['is_active' => 1],
        ]);
        echo "</td><td>".__('Storage Destination','backupmanager')."</td><td>";
        Dropdown::show('PluginBackupmanagerDestination', [
            'name'      => 'plugin_backupmanager_destinations_id',
            'value'     => ($this->fields['plugin_backupmanager_destinations_id'] ?? 0),
            'condition' => ['is_active' => 1],
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4'><strong><i class='ti ti-clock me-1'></i>"
            . __('Recovery Objectives', 'backupmanager')
            . " <small class='text-muted'>ISO 27001 A.5.30 / NIS2 Art.21</small></strong></td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><span title='".__('Recovery Time Objective: tempo máximo para restaurar o serviço após incidente.','backupmanager')."'>"
            .__('RTO (minutes)','backupmanager')." ⓘ</span></td><td>";
        echo Html::input('rto_minutes', ['type'=>'number','value'=>($this->fields['rto_minutes'] ?? 60),'min'=>0]);
        echo "</td><td><span title='".__('Recovery Point Objective: perda máxima de dados tolerada.','backupmanager')."'>"
            .__('RPO (hours)','backupmanager')." ⓘ</span></td><td>";
        echo Html::input('rpo_hours', ['type'=>'number','value'=>($this->fields['rpo_hours'] ?? 24),'min'=>0]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Retention (copies)','backupmanager')."</td><td>";
        echo Html::input('retention_copies', ['type'=>'number','value'=>($this->fields['retention_copies'] ?? 7),'min'=>1]);
        echo "</td><td>".__('Retention (days)','backupmanager')."</td><td>";
        echo Html::input('retention_days', ['type'=>'number','value'=>($this->fields['retention_days'] ?? 30),'min'=>1]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4'><strong><i class='ti ti-calendar me-1'></i>"
            . __('Schedule', 'backupmanager') . "</strong></td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Schedule (Cron)','backupmanager')."</td><td>";
        echo Html::input('schedule_cron', ['value' => ($this->fields['schedule_cron'] ?? ''), 'size'=>20, 'placeholder'=>'0 2 * * *']);
        echo "</td><td>".__('Schedule Description','backupmanager')."</td><td>";
        echo Html::input('schedule_description', ['value' => ($this->fields['schedule_description'] ?? ''), 'placeholder'=>__('e.g. Daily at 2am','backupmanager')]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4'><strong><i class='ti ti-settings me-1'></i>"
            . __('Options', 'backupmanager') . "</strong></td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Compression','backupmanager')."</td><td>";
        Dropdown::showYesNo('compression_enabled', ($this->fields['compression_enabled'] ?? 1));
        echo "</td><td>".__('Algorithm','backupmanager')."</td><td>";
        Dropdown::showFromArray('compression_algorithm', [
            'gzip'=>'gzip','bzip2'=>'bzip2','lz4'=>'lz4','zstd'=>'zstd','none'=>__('None'),
        ], ['value'=>($this->fields['compression_algorithm'] ?? 'gzip')]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Verify backup integrity','backupmanager')."</td><td>";
        Dropdown::showYesNo('verification_enabled', ($this->fields['verification_enabled'] ?? 1));
        echo "</td><td>".__('Notify on failure','backupmanager')."</td><td>";
        Dropdown::showYesNo('notification_on_failure', ($this->fields['notification_on_failure'] ?? 1));
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Technician in charge','backupmanager')."</td><td>";
        User::dropdown(['name'=>'users_id_tech','value'=>($this->fields['users_id_tech'] ?? 0),'right'=>'interface']);
        echo "</td><td>".__('Tech Group','backupmanager')."</td><td>";
        Dropdown::show('Group', ['name'=>'groups_id_tech','value'=>($this->fields['groups_id_tech'] ?? 0),'condition'=>['is_assign'=>1]]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4'><strong><i class='ti ti-shield-check me-1'></i>"
            . __('Compliance Flags','backupmanager') . "</strong></td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>ISO 27001</td><td>";
        Dropdown::showYesNo('compliance_iso27001', ($this->fields['compliance_iso27001'] ?? 0));
        echo "</td><td>NIS2</td><td>";
        Dropdown::showYesNo('compliance_nis2', ($this->fields['compliance_nis2'] ?? 0));
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>CIS Controls</td><td>";
        Dropdown::showYesNo('compliance_cis', ($this->fields['compliance_cis'] ?? 0));
        echo "</td><td></td><td></td></tr>";

        echo "<tr class='tab_bg_1'><td>".__('Comments')."</td><td colspan='3'>";
        echo Html::textarea(['name'=>'comment','value'=>($this->fields['comment'] ?? ''),'cols'=>80,'rows'=>3]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

static function getSpecificValueToDisplay($field, $values, array $options = []) {
    if ($field === 'source_items_id') {
        $itemtype = $values['source_itemtype'] ?? '';
        $items_id = (int)($values[$field] ?? 0);
        if ($items_id > 0 && !empty($itemtype)) {
            $allowed = array_keys(self::getSourceItemtypes());
            if (in_array($itemtype, $allowed, true) && class_exists($itemtype)) {
                $item = new $itemtype();
                if ($item->getFromDB($items_id)) {
                    return $item->getLink();
                }
            }
        }
        return '—';
    }
    return parent::getSpecificValueToDisplay($field, $values, $options);
}

    function rawSearchOptions() {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'       => 2,
            'table'    => $this->getTable(),
            'field'    => 'backup_type',
            'name'     => __('Backup Type','backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'source_type',
            'name'     => __('Source Type','backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'retention_copies',
            'name'     => __('Retention Copies','backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'       => 5,
            'table'    => $this->getTable(),
            'field'    => 'rto_minutes',
            'name'     => __('RTO (min)','backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'       => 6,
            'table'    => $this->getTable(),
            'field'    => 'rpo_hours',
            'name'     => __('RPO (h)','backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'       => 7,
            'table'    => $this->getTable(),
            'field'    => 'priority',
            'name'     => __('Priority','backupmanager'),
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
        $tab[] = [
            'id'       => 9,
            'table'    => $this->getTable(),
            'field'    => 'schedule_description',
            'name'     => __('Schedule','backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 10,
            'table'    => $this->getTable(),
            'field'    => 'compliance_iso27001',
            'name'     => 'ISO 27001',
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'       => 11,
            'table'    => $this->getTable(),
            'field'    => 'compliance_nis2',
            'name'     => 'NIS2',
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'       => 12,
            'table'    => $this->getTable(),
            'field'    => 'compliance_cis',
            'name'     => 'CIS',
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'       => 13,
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'        => 14,
            'table'     => 'glpi_plugin_backupmanager_servers',
            'field'     => 'name',
            'name'      => __('Backup Server','backupmanager'),
            'datatype'  => 'dropdown',
            'linkfield' => 'plugin_backupmanager_servers_id'
        ];
        $tab[] = [
            'id'        => 15,
            'table'     => 'glpi_plugin_backupmanager_destinations',
            'field'     => 'name',
            'name'      => __('Storage Destination','backupmanager'),
            'datatype'  => 'dropdown',
            'linkfield' => 'plugin_backupmanager_destinations_id'
        ];
        $tab[] = [
            'id'       => 16,
            'table'    => $this->getTable(),
            'field'    => 'source_items_id',
            'name'     => __('Source Asset ID','backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'       => 17,
            'table'    => $this->getTable(),
            'field'    => 'schedule_cron',
            'name'     => __('Cron','backupmanager'),
            'datatype' => 'string'
        ];
        $tab[] = [
            'id'       => 18,
            'table'    => $this->getTable(),
            'field'    => 'retention_days',
            'name'     => __('Retention Days','backupmanager'),
            'datatype' => 'number'
        ];
        $tab[] = [
            'id'        => 19,
            'table'     => 'glpi_groups',
            'field'     => 'name',
            'name'      => __('Tech Group','backupmanager'),
            'datatype'  => 'dropdown',
            'linkfield' => 'groups_id_tech'
        ];
        $tab[] = [
            'id'               => 20,
            'table'            => $this->getTable(),
            'field'            => 'source_items_id',
            'name'             => __('Source Asset','backupmanager'),
            'datatype'         => 'specific',
            'additionalfields' => ['source_itemtype'],  // injeta source_itemtype em $values
            'nosearch'         => false,
            'massiveaction'    => false,
        ];

        return $tab;
    }

    function prepareInputForAdd($input) {
        return $this->prepareInput($input);
    }

    function prepareInputForUpdate($input) {
        return $this->prepareInput($input);
    }

    private function prepareInput($input) {
        foreach (['rto_minutes','rpo_hours','retention_copies','retention_days'] as $f) {
            if (isset($input[$f])) {
                $input[$f] = max(0, (int)$input[$f]);
            }
        }

        if (!empty($input['schedule_cron'])) {
            if (!preg_match('/^(\\S+\\s){4}\\S+$/', trim($input['schedule_cron']))) {
                Session::addMessageAfterRedirect(
                    __('Invalid cron expression. Use 5-field format: * * * * *','backupmanager'),
                    false,
                    WARNING
                );
            }
        }

        if (isset($input['source_itemtype'])) {
            $allowed = array_keys(self::getSourceItemtypes());
            if (!in_array($input['source_itemtype'], $allowed, true)) {
                $input['source_itemtype'] = 'Computer';
                $input['source_items_id'] = 0;
            }
        }

        if (isset($input['source_items_id'])) {
            $input['source_items_id'] = (int)$input['source_items_id'];
        }

        return $input;
    }
}