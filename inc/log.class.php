<?php
/**
 * PluginBackupmanagerLog
 * Log de execuções de backup com rastreabilidade completa
 * ISO 27001 A.8.15 - Logging / NIS2 Art.21 / OWASP A09
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginBackupmanagerLog extends CommonDBTM {

    static $rightname = 'plugin_backupmanager_log';

    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';
    const STATUS_PARTIAL = 'partial';

    function getEmpty() {
        parent::getEmpty();
        $this->fields += [
            'name'                             => '',
            'plugin_backupmanager_routines_id' => 0,
            'users_id'                         => 0,
            'status'                           => 'running',
            'execution_date'                   => null,
            'execution_end'                    => null,
            'size_mb'                          => null,
            'checksum'                         => '',
            'remote_path'                      => '',
            'verified'                         => 0,
            'restore_tested'                   => 0,
            'error_message'                    => '',
        ];
    }

    static function getTypeName($nb = 0) {
        return _n('Backup Log', 'Backup Logs', $nb, 'backupmanager');
    }

    static function canCreate() {
        return Session::haveRight("config", UPDATE)
            || Session::haveRight(static::$rightname, CREATE);
    }

    static function canView() {
        return Session::haveRight("config", UPDATE)
            || Session::haveRight(static::$rightname, READ);
    }

    static function canUpdate() {
        return Session::haveRight("config", UPDATE)
            || Session::haveRight(static::$rightname, UPDATE);
    }

    function getName($with_comment = 0) {
        return $this->fields['name'] ?? '';
    }

    static function getStatusBadge($status) {
        $map = [
            self::STATUS_SUCCESS => ['label' => __('Success', 'backupmanager'), 'class' => 'bg-success'],
            self::STATUS_FAILED  => ['label' => __('Failed',  'backupmanager'), 'class' => 'bg-danger'],
            self::STATUS_RUNNING => ['label' => __('Running', 'backupmanager'), 'class' => 'bg-info text-dark'],
            self::STATUS_PARTIAL => ['label' => __('Partial', 'backupmanager'), 'class' => 'bg-warning text-dark'],
        ];

        $s = $map[$status] ?? ['label' => $status, 'class' => 'bg-secondary'];
        return "<span class='badge {$s['class']}'>{$s['label']}</span>";
    }

    private function isReadonlyField(string $field): bool {
        return in_array($field, [
            'name',
            'error_message',
            'execution_date',
            'execution_end',
            'status',
            'checksum',
            'remote_path',
        ], true);
    }

    function defineTabs($options = []) {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

    function showForm($ID, $options = []) {
        if (!static::canView()) {
            return false;
        }

        $isNew    = ($ID <= 0);
        $readonly = !$isNew;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Nome
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Name') . "</td><td colspan='3'>";
        if ($readonly && $this->isReadonlyField('name')) {
            echo "<strong>" . htmlspecialchars($this->fields['name'] ?? '') . "</strong>";
            echo Html::hidden('name', ['value' => ($this->fields['name'] ?? '')]);
        } else {
            echo Html::input('name', ['value' => ($this->fields['name'] ?? ''), 'size' => 80]);
        }
        echo "</td></tr>";

        // Routine + Status
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Routine', 'backupmanager') . "</td><td>";
        Dropdown::show('PluginBackupmanagerRoutine', [
            'name'  => 'plugin_backupmanager_routines_id',
            'value' => ($this->fields['plugin_backupmanager_routines_id'] ?? 0),
        ]);
        echo "</td><td>" . __('Status') . "</td><td>";
        if ($readonly && $this->isReadonlyField('status')) {
            echo self::getStatusBadge($this->fields['status'] ?? '');
            echo Html::hidden('status', ['value' => ($this->fields['status'] ?? '')]);
        } else {
            Dropdown::showFromArray('status', [
                self::STATUS_RUNNING => __('Running', 'backupmanager'),
                self::STATUS_SUCCESS => __('Success', 'backupmanager'),
                self::STATUS_FAILED  => __('Failed',  'backupmanager'),
                self::STATUS_PARTIAL => __('Partial', 'backupmanager'),
            ], ['value' => ($this->fields['status'] ?? '')]);
        }
        echo "</td></tr>";

        // Início + Fim
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Execution Start', 'backupmanager') . "</td><td>";
        if ($readonly && $this->isReadonlyField('execution_date')) {
            echo htmlspecialchars(Html::convDateTime($this->fields['execution_date'] ?? ''));
            echo Html::hidden('execution_date', ['value' => ($this->fields['execution_date'] ?? '')]);
        } else {
            Html::showDateTimeField('execution_date', ['value' => ($this->fields['execution_date'] ?? '')]);
        }
        echo "</td><td>" . __('Execution End', 'backupmanager') . "</td><td>";
        if ($readonly && $this->isReadonlyField('execution_end')) {
            echo htmlspecialchars(Html::convDateTime($this->fields['execution_end'] ?? ''));
            echo Html::hidden('execution_end', ['value' => ($this->fields['execution_end'] ?? '')]);
        } else {
            Html::showDateTimeField('execution_end', ['value' => ($this->fields['execution_end'] ?? '')]);
        }
        echo "</td></tr>";

        // Tamanho + Checksum
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Size (MB)', 'backupmanager') . "</td><td>";
        echo Html::input('size_mb', [
            'type'  => 'number',
            'step'  => '0.01',
            'value' => ($this->fields['size_mb'] ?? 0)
        ]);
        echo "</td><td>" . __('Checksum (SHA256)', 'backupmanager') . "</td><td>";
        if ($readonly && $this->isReadonlyField('checksum')) {
            $chk = htmlspecialchars($this->fields['checksum'] ?? '');
            echo $chk !== '' ? "<code style='word-break:break-all;'>{$chk}</code>" : '<em>' . __('None') . '</em>';
            echo Html::hidden('checksum', ['value' => ($this->fields['checksum'] ?? '')]);
        } else {
            echo Html::input('checksum', ['value' => ($this->fields['checksum'] ?? ''), 'size' => 50]);
        }
        echo "</td></tr>";

        // Remote Path
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Remote Path', 'backupmanager') . "</td><td colspan='3'>";
        if ($readonly && $this->isReadonlyField('remote_path')) {
            $rp = htmlspecialchars($this->fields['remote_path'] ?? '');
            echo $rp !== '' ? "<code>{$rp}</code>" : '<em>' . __('None') . '</em>';
            echo Html::hidden('remote_path', ['value' => ($this->fields['remote_path'] ?? '')]);
        } else {
            echo Html::input('remote_path', ['value' => ($this->fields['remote_path'] ?? ''), 'size' => 80]);
        }
        echo "</td></tr>";

        // Verificado + Restore
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Integrity Verified', 'backupmanager') . "</td><td>";
        Dropdown::showYesNo('verified', ($this->fields['verified'] ?? 0));
        echo "</td><td>" . __('Restore Tested', 'backupmanager') . "</td><td>";
        Dropdown::showYesNo('restore_tested', ($this->fields['restore_tested'] ?? 0));
        echo "</td></tr>";

        // Error Message
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Error Message', 'backupmanager') . "</td><td colspan='3'>";
        if ($readonly && $this->isReadonlyField('error_message')) {
            $em = htmlspecialchars($this->fields['error_message'] ?? '');
            echo $em !== ''
                ? "<pre style='white-space:pre-wrap;word-break:break-word;margin:0;'>{$em}</pre>"
                : '<em>' . __('None') . '</em>';
            echo Html::hidden('error_message', ['value' => ($this->fields['error_message'] ?? '')]);
        } else {
            echo Html::textarea([
                'name'  => 'error_message',
                'value' => ($this->fields['error_message'] ?? ''),
                'cols'  => 80,
                'rows'  => 3
            ]);
        }
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    function rawSearchOptions() {
        $tab = parent::rawSearchOptions();

        // 1 - Nome (link)
        $tab[] = [
            'id'            => 1,
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        // 2 - Status (badge via specific)
        $tab[] = [
            'id'            => 2,
            'table'         => $this->getTable(),
            'field'         => 'status',
            'name'          => __('Status'),
            'datatype'      => 'specific',
            'searchtype'    => ['equals', 'notequals'],
            'massiveaction' => false,
        ];

        // 3 - Início
        $tab[] = [
            'id'            => 3,
            'table'         => $this->getTable(),
            'field'         => 'execution_date',
            'name'          => __('Start', 'backupmanager'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        // 4 - Fim
        $tab[] = [
            'id'            => 4,
            'table'         => $this->getTable(),
            'field'         => 'execution_end',
            'name'          => __('End', 'backupmanager'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        // 5 - Remote Path
        $tab[] = [
            'id'            => 5,
            'table'         => $this->getTable(),
            'field'         => 'remote_path',
            'name'          => __('Remote Path', 'backupmanager'),
            'datatype'      => 'string',
            'massiveaction' => false,
        ];

        // 6 - Tamanho (MB)
        $tab[] = [
            'id'            => 6,
            'table'         => $this->getTable(),
            'field'         => 'size_mb',
            'name'          => __('Size (MB)', 'backupmanager'),
            'datatype'      => 'decimal',
            'massiveaction' => false,
        ];

        // 7 - Verificado
        $tab[] = [
            'id'            => 7,
            'table'         => $this->getTable(),
            'field'         => 'verified',
            'name'          => __('Verified', 'backupmanager'),
            'datatype'      => 'bool',
            'massiveaction' => false,
        ];

        // 8 - Restore Tested
        $tab[] = [
            'id'            => 8,
            'table'         => $this->getTable(),
            'field'         => 'restore_tested',
            'name'          => __('Restore Tested', 'backupmanager'),
            'datatype'      => 'bool',
            'massiveaction' => false,
        ];

        // 9 - Routine (join direto pela FK)
        $tab[] = [
            'id'            => 9,
            'table'         => 'glpi_plugin_backupmanager_routines',
            'field'         => 'name',
            'name'          => __('Routine', 'backupmanager'),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
            'joinparams'    => [
                'jointype'  => '',
                'linkfield' => 'plugin_backupmanager_routines_id',
            ],
            'forcegroupby'  => true,
            'usehaving'     => true,
        ];

        // 10 - Host (routine → server)
        $tab[] = [
            'id'            => 10,
            'table'         => 'glpi_plugin_backupmanager_servers',
            'field'         => 'name',
            'name'          => __('Host', 'backupmanager'),
            'datatype'      => 'string',
            'massiveaction' => false,
            'joinparams'    => [
                'jointype'  => '',
                'linkfield' => 'plugin_backupmanager_servers_id',
                'beforejoin' => [
                    'table'      => 'glpi_plugin_backupmanager_routines',
                    'joinparams' => [
                        'jointype'  => '',
                        'linkfield' => 'plugin_backupmanager_routines_id',
                    ],
                ],
            ],
            'forcegroupby'  => true,
            'usehaving'     => true,
        ];

        // 11 - Checksum
        $tab[] = [
            'id'            => 11,
            'table'         => $this->getTable(),
            'field'         => 'checksum',
            'name'          => __('Checksum', 'backupmanager'),
            'datatype'      => 'string',
            'massiveaction' => false,
        ];

        // 12 - Error Message
        $tab[] = [
            'id'            => 12,
            'table'         => $this->getTable(),
            'field'         => 'error_message',
            'name'          => __('Message', 'backupmanager'),
            'datatype'      => 'text',
            'massiveaction' => false,
        ];

        return $tab;
    }

    static function getSpecificValueToDisplay($field, $values, array $options = []) {
        if ($field === 'status') {
            $val = is_array($values) ? ($values[$field] ?? '') : $values;
            return self::getStatusBadge($val);
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {
        if ($field === 'status') {
            $opts = [
                self::STATUS_RUNNING => __('Running', 'backupmanager'),
                self::STATUS_SUCCESS => __('Success', 'backupmanager'),
                self::STATUS_FAILED  => __('Failed',  'backupmanager'),
                self::STATUS_PARTIAL => __('Partial', 'backupmanager'),
            ];
            return Dropdown::showFromArray($name, $opts, [
                'value'               => $values,
                'display_emptychoice' => true,
                'display'             => false,
            ]);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    function prepareInputForAdd($input) {
        $input['users_id']      = $input['users_id'] ?? Session::getLoginUserID();
        $input['date_creation'] = $_SESSION['glpi_currenttime'];

        if (empty($input['name'])) {
            $input['name'] = $this->buildDefaultName($input);
        }

        return $this->prepareInput($input);
    }

    function prepareInputForUpdate($input) {
        $readonly = [
            'name',
            'error_message',
            'execution_date',
            'execution_end',
            'status',
            'checksum',
            'remote_path',
        ];

        foreach ($readonly as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = $this->fields[$field] ?? $input[$field];
            }
        }

        if (isset($input['name']) && trim((string)$input['name']) === '') {
            $input['name'] = $this->buildDefaultName(array_merge($this->fields, $input));
        }

        return $this->prepareInput($input);
    }

    private function prepareInput($input) {
        if (isset($input['name'])) {
            $input['name'] = Html::cleanInputText($input['name']);
            $input['name'] = mb_substr(trim((string)$input['name']), 0, 255);
        }

        if (isset($input['checksum']) && !empty($input['checksum'])) {
            if (!preg_match('/^[a-f0-9]{0,128}$/i', $input['checksum'])) {
                $input['checksum'] = '';
            }
        }

        return $input;
    }

    private function buildDefaultName(array $input): string {
        $parts = [];

        if (!empty($input['plugin_backupmanager_routines_id'])) {
            $routine = new PluginBackupmanagerRoutine();
            if ($routine->getFromDB((int)$input['plugin_backupmanager_routines_id'])) {
                $parts[] = $routine->fields['name'];
            }
        }

        if (!empty($input['status'])) {
            $parts[] = ucfirst((string)$input['status']);
        }

        if (!empty($input['execution_date'])) {
            $parts[] = Html::convDateTime($input['execution_date']);
        }

        $name = trim(implode(' - ', array_filter($parts)));

        if ($name === '') {
            $name = __('Backup Log', 'backupmanager');
        }

        return mb_substr($name, 0, 255);
    }
}