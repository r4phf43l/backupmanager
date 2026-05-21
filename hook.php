<?php
/**
 * BackupManager Plugin — hook.php
 * Install / Uninstall / Upgrade
 * ISO 27001 A.8.13 | CIS Control 11 | NIS2 Art.21
 */

function plugin_backupmanager_install() {
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    // ── Servidores de Backup ─────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_backupmanager_servers')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_backupmanager_servers` (
                `id`                      INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `name`                    VARCHAR(255) NOT NULL DEFAULT '',
                `entities_id`             INT {$default_key_sign} NOT NULL DEFAULT '0',
                `is_recursive`            TINYINT(1)  NOT NULL DEFAULT '0',
                `is_active`               TINYINT(1)  NOT NULL DEFAULT '1',
                `itemtype`                VARCHAR(100) DEFAULT NULL,
                `items_id`                INT {$default_key_sign} NOT NULL DEFAULT '0',
                `locations_id`            INT {$default_key_sign} NOT NULL DEFAULT '0',
                `ip_address`              VARCHAR(255) DEFAULT NULL,
                `hostname`                VARCHAR(255) DEFAULT NULL,
                `os_type`                 VARCHAR(50)  DEFAULT NULL,
                `backup_software`         VARCHAR(100) DEFAULT NULL,
                `backup_software_version` VARCHAR(50)  DEFAULT NULL,
                `retention_days`          INT NOT NULL DEFAULT '30',
                `last_backup_date`        TIMESTAMP NULL DEFAULT NULL,
                `last_backup_status`      VARCHAR(50)  DEFAULT NULL,
                `encryption_enabled`      TINYINT(1)  NOT NULL DEFAULT '0',
                `encryption_algorithm`    VARCHAR(50)  DEFAULT 'aes256',
                `users_id_tech`           INT {$default_key_sign} NOT NULL DEFAULT '0',
                `groups_id_tech`          INT {$default_key_sign} NOT NULL DEFAULT '0',
                `comment`                 TEXT,
                `date_creation`           TIMESTAMP NULL DEFAULT NULL,
                `date_mod`                TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name`                (`name`),
                KEY `entities_id`         (`entities_id`),
                KEY `is_active`           (`is_active`),
                KEY `itemtype`            (`itemtype`),
                KEY `items_id`            (`items_id`),
                KEY `locations_id`        (`locations_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation}
        ", 'BackupManager — criar tabela servers');
    } else {
        plugin_backupmanager_upgrade_servers($DB, $default_key_sign);
    }

    // ── Destinos de Armazenamento ────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_backupmanager_destinations')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_backupmanager_destinations` (
                `id`                    INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `name`                  VARCHAR(255) NOT NULL DEFAULT '',
                `entities_id`           INT {$default_key_sign} NOT NULL DEFAULT '0',
                `is_recursive`          TINYINT(1)  NOT NULL DEFAULT '0',
                `is_active`             TINYINT(1)  NOT NULL DEFAULT '1',
                `itemtype`              VARCHAR(100) DEFAULT NULL,
                `items_id`              INT {$default_key_sign} NOT NULL DEFAULT '0',
                `locations_id`          INT {$default_key_sign} NOT NULL DEFAULT '0',
                `storage_type`          VARCHAR(50)  NOT NULL DEFAULT 'local',
                `destination_type`      VARCHAR(50)  DEFAULT 'local',
                `expected_content_type` VARCHAR(50)  NOT NULL DEFAULT 'file',
                `path`                  VARCHAR(500) DEFAULT NULL,
                `protocol`              VARCHAR(50)  DEFAULT NULL,
                `host`                  VARCHAR(255) DEFAULT NULL,
                `port`                  INT DEFAULT NULL,
                `credentials_user`      VARCHAR(255) DEFAULT NULL,
                `username`              VARCHAR(255) DEFAULT NULL,
                `location`              VARCHAR(255) DEFAULT NULL,
                `capacity_gb`           DECIMAL(10,2) DEFAULT NULL,
                `used_gb`               DECIMAL(10,2) DEFAULT NULL,
                `encryption_enabled`    TINYINT(1)  NOT NULL DEFAULT '0',
                `encryption_algorithm`  VARCHAR(50)  DEFAULT 'aes256',
                `is_offsite`            TINYINT(1)  NOT NULL DEFAULT '0',
                `redundancy_type`       VARCHAR(50)  DEFAULT NULL,
                `users_id_tech`         INT {$default_key_sign} NOT NULL DEFAULT '0',
                `comment`               TEXT,
                `date_creation`         TIMESTAMP NULL DEFAULT NULL,
                `date_mod`              TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name`                   (`name`),
                KEY `entities_id`            (`entities_id`),
                KEY `storage_type`           (`storage_type`),
                KEY `locations_id`           (`locations_id`),
                KEY `expected_content_type`  (`expected_content_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation}
        ", 'BackupManager — criar tabela destinations');
    } else {
        plugin_backupmanager_upgrade_destinations($DB, $default_key_sign);
    }

    // ── Rotinas de Backup ────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_backupmanager_routines')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_backupmanager_routines` (
                `id`                                   INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `name`                                 VARCHAR(255) NOT NULL DEFAULT '',
                `entities_id`                          INT {$default_key_sign} NOT NULL DEFAULT '0',
                `is_recursive`                         TINYINT(1)  NOT NULL DEFAULT '0',
                `is_active`                            TINYINT(1)  NOT NULL DEFAULT '1',
                `priority`                             TINYINT(1)  NOT NULL DEFAULT '3',
                `backup_type`                          VARCHAR(50)  DEFAULT 'full',
                `source_type`                          VARCHAR(50) NOT NULL DEFAULT 'files',
                `source_itemtype`                      VARCHAR(100) DEFAULT 'Computer',
                `source_items_id`                      INT {$default_key_sign} NOT NULL DEFAULT '0',
                `plugin_backupmanager_servers_id`      INT {$default_key_sign} NOT NULL DEFAULT '0',
                `plugin_backupmanager_destinations_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `rto_minutes`                          INT NOT NULL DEFAULT '60',
                `rpo_hours`                            INT NOT NULL DEFAULT '24',
                `retention_copies`                     INT NOT NULL DEFAULT '7',
                `retention_days`                       INT NOT NULL DEFAULT '30',
                `schedule_cron`                        VARCHAR(100) DEFAULT NULL,
                `schedule_description`                 VARCHAR(255) DEFAULT NULL,
                `compression_enabled`                  TINYINT(1)  NOT NULL DEFAULT '1',
                `compression_algorithm`                VARCHAR(20)  DEFAULT 'gzip',
                `verification_enabled`                 TINYINT(1)  NOT NULL DEFAULT '1',
                `notification_on_failure`              TINYINT(1)  NOT NULL DEFAULT '1',
                `users_id_tech`                        INT {$default_key_sign} NOT NULL DEFAULT '0',
                `groups_id_tech`                       INT {$default_key_sign} NOT NULL DEFAULT '0',
                `compliance_iso27001`                  TINYINT(1)  NOT NULL DEFAULT '0',
                `compliance_nis2`                      TINYINT(1)  NOT NULL DEFAULT '0',
                `compliance_cis`                       TINYINT(1)  NOT NULL DEFAULT '0',
                `comment`                              TEXT,
                `date_creation`                        TIMESTAMP NULL DEFAULT NULL,
                `date_mod`                             TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name`               (`name`),
                KEY `entities_id`        (`entities_id`),
                KEY `is_active`          (`is_active`),
                KEY `plugin_backupmanager_servers_id`      (`plugin_backupmanager_servers_id`),
                KEY `plugin_backupmanager_destinations_id` (`plugin_backupmanager_destinations_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation}
        ", 'BackupManager — criar tabela routines');
    } else {
        plugin_backupmanager_upgrade_routines($DB, $default_key_sign);
    }

    // ── Checklists ───────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_backupmanager_checklists')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_backupmanager_checklists` (
                `id`                               INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `entities_id`                      INT {$default_key_sign} NOT NULL DEFAULT '0',
                `plugin_backupmanager_routines_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `step_order`                       INT NOT NULL DEFAULT '0',
                `step_description`                 TEXT,
                `step_type`                        VARCHAR(20)  DEFAULT 'manual',
                `is_mandatory`                     TINYINT(1)  NOT NULL DEFAULT '1',
                `reference_standard`               VARCHAR(50)  DEFAULT NULL,
                `json_meta`                        LONGTEXT DEFAULT NULL,
                `date_creation`                    TIMESTAMP NULL DEFAULT NULL,
                `date_mod`                         TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `plugin_backupmanager_routines_id` (`plugin_backupmanager_routines_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation}
        ", 'BackupManager — criar tabela checklists');
    }

    // ── Logs de Execução ─────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_backupmanager_logs')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_backupmanager_logs` (
                `id`                               INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `name`                             VARCHAR(255) NOT NULL DEFAULT '',
                `entities_id`                      INT {$default_key_sign} NOT NULL DEFAULT '0',
                `plugin_backupmanager_routines_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `users_id`                         INT {$default_key_sign} NOT NULL DEFAULT '0',
                `status`                           VARCHAR(20)   DEFAULT 'running',
                `execution_date`                   TIMESTAMP NULL DEFAULT NULL,
                `execution_end`                    TIMESTAMP NULL DEFAULT NULL,
                `size_mb`                          DECIMAL(12,2) DEFAULT NULL,
                `checksum`                         VARCHAR(128)  DEFAULT NULL,
                `remote_path`                      VARCHAR(500)  DEFAULT NULL,
                `verified`                         TINYINT(1)   NOT NULL DEFAULT '0',
                `restore_tested`                   TINYINT(1)   NOT NULL DEFAULT '0',
                `error_message`                    TEXT,
                `json_meta`                        LONGTEXT DEFAULT NULL,
                `date_creation`                    TIMESTAMP NULL DEFAULT NULL,
                `date_mod`                         TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name`                             (`name`),
                KEY `entities_id`                      (`entities_id`),
                KEY `plugin_backupmanager_routines_id` (`plugin_backupmanager_routines_id`),
                KEY `status`                           (`status`),
                KEY `execution_date`                   (`execution_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation}
        ", 'BackupManager — criar tabela logs');
    } else {
        plugin_backupmanager_upgrade_logs($DB, $default_key_sign);
    }

    // ── Configurações do Plugin ──────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_backupmanager_configs')) {
        $DB->queryOrDie("
            CREATE TABLE `glpi_plugin_backupmanager_configs` (
                `id`            INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `config_key`    VARCHAR(100) NOT NULL DEFAULT '',
                `config_value`  TEXT,
                `date_creation` TIMESTAMP NULL DEFAULT NULL,
                `date_mod`      TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `config_key` (`config_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
              COLLATE={$default_collation}
        ", 'BackupManager — criar tabela configs');
    }

    plugin_backupmanager_upgrade_configs($DB);
    plugin_backupmanager_grant_superadmin_rights();

    return true;
}

// ── Upgrade: colunas novas em servers ────────────────────────────────────────
function plugin_backupmanager_upgrade_servers($DB, $default_key_sign) {
    $table = 'glpi_plugin_backupmanager_servers';
    $cols  = plugin_backupmanager_existing_columns($DB, $table);

    $to_add = [
        'locations_id'            => "INT {$default_key_sign} NOT NULL DEFAULT '0' AFTER `items_id`",
        'backup_software'         => "VARCHAR(100) DEFAULT NULL AFTER `os_type`",
        'backup_software_version' => "VARCHAR(50)  DEFAULT NULL AFTER `backup_software`",
        'retention_days'          => "INT NOT NULL DEFAULT '30' AFTER `backup_software_version`",
        'encryption_enabled'      => "TINYINT(1) NOT NULL DEFAULT '0' AFTER `last_backup_status`",
        'encryption_algorithm'    => "VARCHAR(50) DEFAULT 'aes256' AFTER `encryption_enabled`",
        'groups_id_tech'          => "INT {$default_key_sign} NOT NULL DEFAULT '0' AFTER `users_id_tech`",
    ];

    foreach ($to_add as $col => $def) {
        if (!in_array($col, $cols)) {
            $DB->queryOrDie(
                "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}",
                "BackupManager upgrade — adicionar {$col} em {$table}"
            );
        }
    }
}

// ── Upgrade: colunas novas em destinations ───────────────────────────────────
function plugin_backupmanager_upgrade_destinations($DB, $default_key_sign) {
    $table = 'glpi_plugin_backupmanager_destinations';
    $cols  = plugin_backupmanager_existing_columns($DB, $table);

    $to_add = [
        'locations_id'          => "INT {$default_key_sign} NOT NULL DEFAULT '0' AFTER `items_id`",
        'destination_type'      => "VARCHAR(50) DEFAULT 'local' AFTER `storage_type`",
        'expected_content_type' => "VARCHAR(50) NOT NULL DEFAULT 'file' AFTER `destination_type`",
        'username'              => "VARCHAR(255) DEFAULT NULL AFTER `credentials_user`",
        'location'              => "VARCHAR(255) DEFAULT NULL AFTER `host`",
        'redundancy_type'       => "VARCHAR(50)  DEFAULT NULL AFTER `is_offsite`",
        'users_id_tech'         => "INT {$default_key_sign} NOT NULL DEFAULT '0' AFTER `redundancy_type`",
        'encryption_algorithm'  => "VARCHAR(50) DEFAULT 'aes256' AFTER `encryption_enabled`",
    ];

    foreach ($to_add as $col => $def) {
        if (!in_array($col, $cols)) {
            $DB->queryOrDie(
                "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}",
                "BackupManager upgrade — adicionar {$col} em {$table}"
            );
        }
    }

    $indexes = [];
    $result = $DB->query("SHOW INDEX FROM `{$table}`");
    while ($row = $DB->fetchAssoc($result)) {
        $indexes[] = $row['Key_name'];
    }

    if (!in_array('expected_content_type', $indexes)) {
        $DB->queryOrDie(
            "ALTER TABLE `{$table}` ADD KEY `expected_content_type` (`expected_content_type`)",
            "BackupManager upgrade — adicionar índice expected_content_type em {$table}"
        );
    }
}

// ── Upgrade: colunas novas em routines ───────────────────────────────────────
function plugin_backupmanager_upgrade_routines($DB, $default_key_sign) {
    $table = 'glpi_plugin_backupmanager_routines';
    $cols  = plugin_backupmanager_existing_columns($DB, $table);

    $to_add = [
        'source_type'     => "VARCHAR(50) NOT NULL DEFAULT 'files' AFTER `backup_type`",
        'source_itemtype' => "VARCHAR(100) DEFAULT 'Computer' AFTER `source_type`",
        'source_items_id' => "INT {$default_key_sign} NOT NULL DEFAULT '0' AFTER `source_itemtype`",
    ];

    foreach ($to_add as $col => $def) {
        if (!in_array($col, $cols)) {
            $DB->queryOrDie(
                "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}",
                "BackupManager upgrade — adicionar {$col} em {$table}"
            );
        }
    }
}

// ── Upgrade: colunas novas em logs ───────────────────────────────────────────
function plugin_backupmanager_upgrade_logs($DB, $default_key_sign) {
    $table = 'glpi_plugin_backupmanager_logs';
    $cols  = plugin_backupmanager_existing_columns($DB, $table);

    if (!in_array('name', $cols)) {
        $DB->queryOrDie(
            "ALTER TABLE `{$table}` ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `id`",
            "BackupManager upgrade — adicionar name em logs"
        );
    }

    if (!in_array('json_meta', $cols)) {
        $DB->queryOrDie(
            "ALTER TABLE `{$table}` ADD COLUMN `json_meta` LONGTEXT DEFAULT NULL AFTER `error_message`",
            "BackupManager upgrade — adicionar json_meta em logs"
        );
    }

    $indexes = [];
    $result = $DB->query("SHOW INDEX FROM `{$table}`");
    while ($row = $DB->fetchAssoc($result)) {
        $indexes[] = $row['Key_name'];
    }

    if (!in_array('name', $indexes)) {
        $DB->queryOrDie(
            "ALTER TABLE `{$table}` ADD KEY `name` (`name`)",
            "BackupManager upgrade — adicionar índice name em logs"
        );
    }
}

// ── Upgrade: garantir configs base ───────────────────────────────────────────
function plugin_backupmanager_upgrade_configs($DB) {
    if (!$DB->tableExists('glpi_plugin_backupmanager_configs')) {
        return;
    }

    $defaults = [
        'webhook_token'    => bin2hex(random_bytes(32)),
        'webhook_enabled'  => '0',
        'webhook_ip_allow' => '',
    ];

    foreach ($defaults as $key => $value) {
        if (!countElementsInTable('glpi_plugin_backupmanager_configs', ['config_key' => $key])) {
            $DB->insert('glpi_plugin_backupmanager_configs', [
                'config_key'    => $key,
                'config_value'  => $value,
                'date_creation' => date('Y-m-d H:i:s'),
                'date_mod'      => date('Y-m-d H:i:s'),
            ]);
        }
    }
}

// ── Helper: retorna colunas existentes de uma tabela ─────────────────────────
function plugin_backupmanager_existing_columns($DB, $table) {
    $cols   = [];
    $result = $DB->query("SHOW COLUMNS FROM `{$table}`");
    while ($row = $DB->fetchAssoc($result)) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

// ── Garantir direitos ao perfil Super-Admin ──────────────────────────────────
function plugin_backupmanager_grant_superadmin_rights() {
    global $DB;

    $rights = [
        'plugin_backupmanager_dashboard',
        'plugin_backupmanager_server',
        'plugin_backupmanager_destination',
        'plugin_backupmanager_routine',
        'plugin_backupmanager_checklist',
        'plugin_backupmanager_log',
    ];

    $profiles = getAllDataFromTable('glpi_profiles', [
        ['OR' => ['id' => 4, 'interface' => 'central']],
    ]);

    foreach ($profiles as $profile) {
        $pid = (int)$profile['id'];

        foreach ($rights as $right) {
            $existing = countElementsInTable('glpi_profilerights', [
                'profiles_id' => $pid,
                'name'        => $right,
            ]);

            $value = ALLSTANDARDRIGHT | READNOTE | UPDATENOTE;

            if ($existing) {
                $DB->update('glpi_profilerights', [
                    'rights' => $value,
                ], [
                    'profiles_id' => $pid,
                    'name'        => $right,
                ]);
            } else {
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $pid,
                    'name'        => $right,
                    'rights'      => $value,
                ]);
            }
        }
    }
}

// ── Desinstalar ───────────────────────────────────────────────────────────────
function plugin_backupmanager_uninstall() {
    global $DB;

    $tables = [
        'glpi_plugin_backupmanager_logs',
        'glpi_plugin_backupmanager_checklists',
        'glpi_plugin_backupmanager_routines',
        'glpi_plugin_backupmanager_destinations',
        'glpi_plugin_backupmanager_servers',
        'glpi_plugin_backupmanager_configs',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->queryOrDie(
                "DROP TABLE `{$table}`",
                "BackupManager — remover tabela {$table}"
            );
        }
    }

    $rights = [
        'plugin_backupmanager_dashboard',
        'plugin_backupmanager_server',
        'plugin_backupmanager_destination',
        'plugin_backupmanager_routine',
        'plugin_backupmanager_checklist',
        'plugin_backupmanager_log',
    ];

    foreach ($rights as $right) {
        $DB->delete('glpi_profilerights', ['name' => $right]);
    }

    return true;
}