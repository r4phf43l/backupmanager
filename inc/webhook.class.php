<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginBackupmanagerWebhook
{
    public static function mapStatus($value): string
    {
        $value = strtolower(trim((string)$value));

        $map = [
            'ok'       => 'success',
            'success'  => 'success',
            'info'     => 'success',
            'notice'   => 'success',
            'warning'  => 'partial',
            'partial'  => 'partial',
            'error'    => 'failed',
            'failed'   => 'failed',
            'critical' => 'failed',
            'running'  => 'running',
        ];

        return $map[$value] ?? 'partial';
    }

    public static function debugLog(string $message, array $context = []): void
    {
        $line = '[BackupManagerWebhook] ' . $message;

        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        Toolbox::logInFile('backupmanager-webhook', $line . PHP_EOL);
    }

    public static function processPayload(array $payload, string $client_ip, array $config): array
    {
        global $DB;

        self::debugLog('PROCESS_START', [
            'client_ip'     => $client_ip,
            'payload_keys'  => array_keys($payload),
            'has_fields'    => isset($payload['fields']) && is_array($payload['fields']),
            'config_keys'   => array_keys($config),
            'timestamp_now' => date('Y-m-d H:i:s'),
        ]);

        $fields = [];
        if (isset($payload['fields']) && is_array($payload['fields'])) {
            $fields = $payload['fields'];
        }

        $title      = self::pickString($payload, $fields, ['title'], 'PBS notification');
        $message    = self::pickText($payload, $fields, ['message'], '');
        $severity   = self::pickString($payload, $fields, ['severity', 'status'], 'info');
        $event_type = self::pickString($payload, $fields, ['event_type', 'type'], 'test');
        $hostname   = self::pickString($payload, $fields, ['hostname'], '');

        $job_id      = self::pickString($payload, $fields, ['job_id', 'job-id'], '');
        $datastore   = self::pickString($payload, $fields, ['datastore'], '');
        $backup_id   = self::pickString($payload, $fields, ['backup_id', 'backup-id'], '');
        $backup_type = self::pickString($payload, $fields, ['backup_type', 'backup-type'], '');
        $source_id   = self::pickString($payload, $fields, ['source_id', 'source-id'], '');
        $owner       = self::pickString($payload, $fields, ['owner'], '');
        $verify      = self::pickString($payload, $fields, ['verify_state', 'verify-state'], '');
        $checksum    = self::pickString($payload, $fields, ['checksum'], '');
        $remote      = self::pickString($payload, $fields, ['remote'], '');
        $namespace   = self::pickString($payload, $fields, ['ns'], '');

        $timestamp   = self::pickTimestamp($payload, $fields, ['timestamp', 'start-time', 'start_time'], time());
        $endtime     = self::pickTimestamp($payload, $fields, ['end-time', 'end_time'], $timestamp);

        $size_bytes  = self::pickNumber($payload, $fields, ['size', 'transfer-size'], null);
        $size_mb     = $size_bytes !== null ? round(((float)$size_bytes / 1048576), 2) : null;

        $status = self::mapStatus($severity);

        if (isset($payload['status']) && trim((string)$payload['status']) !== '') {
            $status = self::mapStatus($payload['status']);
        } elseif (isset($fields['status']) && trim((string)$fields['status']) !== '') {
            $status = self::mapStatus($fields['status']);
        }

        $execution_date = $timestamp ? date('Y-m-d H:i:s', (int)$timestamp) : null;
        $execution_end  = $endtime ? date('Y-m-d H:i:s', (int)$endtime) : null;

        $routine_id  = self::findRoutineId($job_id, $datastore, $source_id, $backup_id);
        $entities_id = self::resolveEntity($routine_id);
        $users_id    = self::resolveUserId($owner);

        $remote_parts = [];
        if ($remote !== '') {
            $remote_parts[] = $remote;
        }
        if ($datastore !== '') {
            $remote_parts[] = $datastore;
        }
        if ($namespace !== '') {
            $remote_parts[] = $namespace;
        }
        if ($backup_type !== '') {
            $remote_parts[] = $backup_type;
        }
        if ($backup_id !== '') {
            $remote_parts[] = $backup_id;
        }

        $remote_path = '';
        if (!empty($remote_parts)) {
            $remote_path = implode('/', array_map(function ($v) {
                return trim((string)$v, '/');
            }, $remote_parts));
        }

        if ($checksum !== '' && !preg_match('/^[a-f0-9]{32,128}$/i', $checksum)) {
            $checksum = '';
        }

        $verified       = in_array(strtolower($verify), ['ok', 'verified', 'success', 'true', '1'], true) ? 1 : 0;
$restore_tested = self::deriveRestoreTested($event_type, $title, $message);

$log_name = self::sanitizeStr($title, 255);
$remote_path_name = self::sanitizeStr($remote_path, 255);

if ($log_name === '') {
    $log_name_parts = [];

    if ($event_type !== '') {
        $log_name_parts[] = ucfirst($event_type);
    }

    if ($hostname !== '') {
        $log_name_parts[] = $hostname;
    }

    $log_name = self::sanitizeStr(implode(' - ', array_filter($log_name_parts)), 255);
}

if ($remote_path_name !== '') {
    $name_lc        = mb_strtolower($log_name);
    $remote_path_lc = mb_strtolower($remote_path_name);

    if ($log_name === '') {
        $log_name = $remote_path_name;
    } elseif (mb_strpos($name_lc, $remote_path_lc) === false) {
        $log_name = self::sanitizeStr($log_name . ' - ' . $remote_path_name, 255);
    }
}

if ($log_name === '') {
    $log_name = 'PBS notification';
}

        $error_parts = [];
        if ($title !== '') {
            $error_parts[] = "[{$title}]";
        }
        if ($event_type !== '') {
            $error_parts[] = "event={$event_type}";
        }
        if ($severity !== '') {
            $error_parts[] = "severity={$severity}";
        }
        if ($hostname !== '') {
            $error_parts[] = "host={$hostname}";
        }
        if ($job_id !== '') {
            $error_parts[] = "job={$job_id}";
        }
        if ($datastore !== '') {
            $error_parts[] = "datastore={$datastore}";
        }
        if ($backup_id !== '') {
            $error_parts[] = "backup_id={$backup_id}";
        }
        if ($backup_type !== '') {
            $error_parts[] = "backup_type={$backup_type}";
        }
        if ($source_id !== '') {
            $error_parts[] = "source={$source_id}";
        }
        if ($owner !== '') {
            $error_parts[] = "owner={$owner}";
        }
        if ($message !== '') {
            $error_parts[] = $message;
        }

        $error_message = self::sanitizeDbText(implode(' | ', $error_parts));

        $log_data = [
            'name'                             => $log_name,
            'entities_id'                      => (int)$entities_id,
            'plugin_backupmanager_routines_id' => (int)$routine_id,
            'users_id'                         => (int)$users_id,
            'status'                           => $status,
            'execution_date'                   => $execution_date,
            'execution_end'                    => $execution_end,
            'size_mb'                          => $size_mb,
            'checksum'                         => $checksum !== '' ? $checksum : null,
            'remote_path'                      => $remote_path !== '' ? $remote_path : null,
            'verified'                         => $verified,
            'restore_tested'                   => $restore_tested,
            'error_message'                    => $error_message,
            'date_creation'                    => date('Y-m-d H:i:s'),
            'date_mod'                         => date('Y-m-d H:i:s'),
        ];

        self::debugLog('LOG_DATA_READY', $log_data);

        $ok = $DB->insert('glpi_plugin_backupmanager_logs', $log_data);

        if (!$ok) {
            $dbError = $DB->error() ?: 'unknown DB error';

            Toolbox::logInFile('backupmanager-webhook', "DB_ERROR=" . $dbError . PHP_EOL);
            Toolbox::logInFile('backupmanager-webhook', "LOG_DATA=" . json_encode($log_data, JSON_UNESCAPED_UNICODE) . PHP_EOL);

            self::auditLog('WEBHOOK_DB_ERROR', $client_ip, $dbError);

            return [
                'success'  => false,
                'message'  => 'Database insert failed',
                'db_error' => $dbError
            ];
        }

        $log_id = $DB->insertId();

        if ($routine_id > 0) {
            self::updateServerLastBackup($routine_id, $execution_date, $status);
        }

        self::auditLog(
            'WEBHOOK_RECEIVED',
            $client_ip,
            "log_id={$log_id} routine={$routine_id} status={$status} event={$event_type}"
        );

        return [
            'success' => true,
            'log_id'  => $log_id
        ];
    }

    public static function findRoutineId(string $job_id, string $datastore, string $source_id, string $backup_id = ''): int
    {
        global $DB;

        $candidates = array_filter([$job_id, $source_id, $backup_id]);

        foreach ($candidates as $candidate) {
            $safe = $DB->escape($candidate);

            $sql = "
                SELECT `id`
                FROM `glpi_plugin_backupmanager_routines`
                WHERE `is_active` = 1
                  AND (
                    `name` LIKE '%{$safe}%'
                    OR `schedule_description` LIKE '%{$safe}%'
                  )
                ORDER BY `id` ASC
                LIMIT 1
            ";

            $result = $DB->query($sql);
            if ($result && ($row = $DB->fetchAssoc($result))) {
                return (int)$row['id'];
            }
        }

        if ($datastore !== '') {
            $safeDatastore = $DB->escape($datastore);

            $sql = "
                SELECT r.`id`
                FROM `glpi_plugin_backupmanager_routines` r
                INNER JOIN `glpi_plugin_backupmanager_destinations` d
                    ON d.`id` = r.`plugin_backupmanager_destinations_id`
                WHERE r.`is_active` = 1
                  AND (
                    d.`name` LIKE '%{$safeDatastore}%'
                    OR d.`host` LIKE '%{$safeDatastore}%'
                    OR d.`remote_path` LIKE '%{$safeDatastore}%'
                  )
                ORDER BY r.`id` ASC
                LIMIT 1
            ";

            $result = $DB->query($sql);
            if ($result && ($row = $DB->fetchAssoc($result))) {
                return (int)$row['id'];
            }
        }

        return 0;
    }

    public static function resolveUserId(string $owner): int
    {
        global $DB;

        $owner = trim($owner);
        if ($owner === '') {
            return 0;
        }

        $login = $owner;
        if (strpos($login, '@') !== false) {
            $login = explode('@', $login)[0];
        }

        $safe = $DB->escape($login);

        $sql = "
            SELECT `id`
            FROM `glpi_users`
            WHERE `name` = '{$safe}'
               OR `realname` = '{$safe}'
               OR `firstname` = '{$safe}'
            ORDER BY `id` ASC
            LIMIT 1
        ";

        $result = $DB->query($sql);
        if ($result && ($row = $DB->fetchAssoc($result))) {
            return (int)$row['id'];
        }

        return 0;
    }

    public static function updateServerLastBackup(int $routine_id, ?string $date, string $status): void
    {
        global $DB;

        if ($date === null) {
            return;
        }

        $routine = new PluginBackupmanagerRoutine();
        if (!$routine->getFromDB($routine_id)) {
            return;
        }

        $server_id = (int)($routine->fields['plugin_backupmanager_servers_id'] ?? 0);
        if ($server_id <= 0) {
            return;
        }

        $DB->update(
            'glpi_plugin_backupmanager_servers',
            [
                'last_backup_date'   => $date,
                'last_backup_status' => $status,
                'date_mod'           => date('Y-m-d H:i:s'),
            ],
            ['id' => $server_id]
        );
    }

    public static function ipInAllowlist(string $ip, array $allowed): bool
    {
        foreach ($allowed as $entry) {
            if (strpos($entry, '/') !== false) {
                if (self::ipInCidr($ip, $entry)) {
                    return true;
                }
            } else {
                if ($ip === $entry) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr, 2);
        $bits = (int)$bits;

        $ip_long  = ip2long($ip);
        $net_long = ip2long($network);

        if ($ip_long === false || $net_long === false) {
            return false;
        }

        $mask = $bits > 0 ? (~0 << (32 - $bits)) : 0;
        return (($ip_long & $mask) === ($net_long & $mask));
    }

    public static function auditLog(string $event, string $ip, string $detail = ''): void
    {
        $msg = "[BackupManager Webhook] {$event} | IP:{$ip}";
        if ($detail !== '') {
            $msg .= " | {$detail}";
        }
        Toolbox::logInfo($msg);
    }

    public static function pickString(array $payload, array $fields, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && trim((string)$payload[$key]) !== '') {
                return self::sanitizeStr($payload[$key]);
            }
            if (isset($fields[$key]) && trim((string)$fields[$key]) !== '') {
                return self::sanitizeStr($fields[$key]);
            }
        }
        return $default;
    }

    public static function pickText(array $payload, array $fields, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && trim((string)$payload[$key]) !== '') {
                return self::sanitizeDbText($payload[$key]) ?? $default;
            }
            if (isset($fields[$key]) && trim((string)$fields[$key]) !== '') {
                return self::sanitizeDbText($fields[$key]) ?? $default;
            }
        }
        return $default;
    }

    public static function pickTimestamp(array $payload, array $fields, array $keys, int $default): int
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (int)$payload[$key];
            }
            if (isset($fields[$key]) && is_numeric($fields[$key])) {
                return (int)$fields[$key];
            }
        }
        return $default;
    }

    public static function pickNumber(array $payload, array $fields, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return $payload[$key];
            }
            if (isset($fields[$key]) && is_numeric($fields[$key])) {
                return $fields[$key];
            }
        }
        return $default;
    }

    public static function deriveRestoreTested(string $event_type, string $title, string $message): int
    {
        $txt = strtolower($event_type . ' ' . $title . ' ' . $message);
        return (strpos($txt, 'restore') !== false || strpos($txt, 'recover') !== false) ? 1 : 0;
    }

    public static function sanitizeStr($value, int $maxlen = 255): string
    {
        if (!is_string($value)) {
            $value = (string)$value;
        }

        $value = strip_tags(trim($value));
        $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return mb_substr($value, 0, $maxlen);
    }

    public static function sanitizeDbText($value, int $maxlen = 65535): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $value = (string)$value;
        }

        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $value);
        $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);

        // Normaliza aspas tipográficas e remove aspas simples/dobras problemáticas
        $value = str_replace(
            ["‘", "’", "‚", "`", "´", "“", "”", "„"],
            ["'", "'", "'", "'", "'", '"', '"', '"'],
            $value
        );

        // Para evitar quebra de SQL em ambientes GLPI/MySQL mais sensíveis,
        // convertemos aspas simples e duplas em equivalentes visuais seguros.
        $value = str_replace("'", "’", $value);
        $value = str_replace('"', '”', $value);

        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxlen);
    }

    public static function resolveEntity(int $routine_id): int
    {
        global $DB;

        if ($routine_id <= 0) {
            return 0;
        }

        $sql = "
            SELECT `entities_id`
            FROM `glpi_plugin_backupmanager_routines`
            WHERE `id` = {$routine_id}
            LIMIT 1
        ";

        $result = $DB->query($sql);
        if ($result && ($row = $DB->fetchAssoc($result))) {
            return (int)($row['entities_id'] ?? 0);
        }

        return 0;
    }
}