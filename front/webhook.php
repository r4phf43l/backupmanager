<?php
/**
 * Endpoint FRONT para receber webhooks do PBS
 * URL esperada no PBS:
 * /plugins/backupmanager/front/webhook.php
 */

define('GLPI_ROOT', dirname(__DIR__, 3));
define('GLPI_USE_CSRF_CHECK', false);
define('DO_NOT_CHECK_HTTP_REFERER', true);

include(GLPI_ROOT . '/inc/includes.php');

header('Content-Type: application/json; charset=utf-8');

function bmGetHeader(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (!empty($_SERVER[$serverKey])) {
        return trim((string)$_SERVER[$serverKey]);
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return trim((string)$value);
            }
        }
    }

    return '';
}

function bmGetBearerToken(): string
{
    $auth = bmGetHeader('Authorization');
    if ($auth !== '' && preg_match('/Bearer\\s+(.+)/i', $auth, $m)) {
        return trim((string)$m[1]);
    }
    return '';
}

function bmDebugLog(string $message, array $context = []): void
{
    $line = '[BackupManagerWebhook FRONT] ' . $message;

    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    Toolbox::logInFile('backupmanager-webhook', $line . PHP_EOL);
}

function bmReadNestedString(array $source, array $path): string
{
    $current = $source;

    foreach ($path as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return '';
        }
        $current = $current[$segment];
    }

    if ($current === null) {
        return '';
    }

    return trim((string)$current);
}

function bmBuildNormalizedTitle(array $payload): string
{
    $parts = [];

    $type = bmReadNestedString($payload, ['fields', 'type']);
    $hostname = bmReadNestedString($payload, ['fields', 'hostname']);
    $remote = bmReadNestedString($payload, ['fields', 'remote']);

    if ($type !== '') {
        $parts[] = $type;
    }
    if ($hostname !== '') {
        $parts[] = $hostname;
    }
    if ($remote !== '') {
        $parts[] = $remote;
    }

    $title = trim(implode(' - ', $parts));

    if ($title !== '') {
        return $title;
    }

    // fallback para campos fora de fields, se existirem
    $type = trim((string)($payload['type'] ?? ''));
    $hostname = trim((string)($payload['hostname'] ?? ''));
    $remote = trim((string)($payload['remote'] ?? ''));

    $parts = [];
    if ($type !== '') {
        $parts[] = $type;
    }
    if ($hostname !== '') {
        $parts[] = $hostname;
    }
    if ($remote !== '') {
        $parts[] = $remote;
    }

    $title = trim(implode(' - ', $parts));

    if ($title !== '') {
        return $title;
    }

    return trim((string)($payload['title'] ?? ''));
}

try {
    $client_ip = Toolbox::getRemoteIpAddress();

    bmDebugLog('FRONT_START', [
        'client_ip'      => $client_ip,
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'request_uri'    => $_SERVER['REQUEST_URI'] ?? '',
        'query_string'   => $_SERVER['QUERY_STRING'] ?? '',
        'content_type'   => $_SERVER['CONTENT_TYPE'] ?? '',
        'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        bmDebugLog('INVALID_METHOD', [
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);

        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'method not allowed'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $enabled = trim((string)PluginBackupmanagerConfig::getConfig('webhook_enabled', '0'));
    $saved_token = trim((string)PluginBackupmanagerConfig::getConfig('webhook_token', ''));
    $ip_allow_raw = trim((string)PluginBackupmanagerConfig::getConfig('webhook_ip_allow', ''));

    bmDebugLog('CONFIG_LOADED', [
        'enabled'      => $enabled,
        'has_token'    => $saved_token !== '',
        'ip_allow_raw' => $ip_allow_raw,
    ]);

    if ($enabled !== '1') {
        bmDebugLog('WEBHOOK_DISABLED');

        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'webhook disabled'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($saved_token === '') {
        bmDebugLog('TOKEN_NOT_CONFIGURED');

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'webhook token not configured'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $provided_token = bmGetBearerToken();
    $token_source = 'authorization';

    if ($provided_token === '') {
        $provided_token = bmGetHeader('X-PBS-Token');
        $token_source = 'x-pbs-token';
    }
    if ($provided_token === '') {
        $provided_token = bmGetHeader('X-BackupManager-Token');
        $token_source = 'x-backupmanager-token';
    }

    bmDebugLog('TOKEN_RECEIVED', [
        'token_source' => $token_source,
        'has_token'    => $provided_token !== '',
        'auth_header'  => bmGetHeader('Authorization') !== '' ? 'present' : 'absent',
        'x_pbs_token'  => bmGetHeader('X-PBS-Token') !== '' ? 'present' : 'absent',
        'x_bm_token'   => bmGetHeader('X-BackupManager-Token') !== '' ? 'present' : 'absent',
    ]);

    if ($provided_token === '' || !hash_equals($saved_token, $provided_token)) {
        bmDebugLog('AUTH_FAIL', [
            'client_ip'    => $client_ip,
            'token_source' => $token_source,
            'has_token'    => $provided_token !== '',
        ]);

        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'invalid token'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowed_ips = [];
    if ($ip_allow_raw !== '') {
        $allowed_ips = array_values(array_filter(array_map('trim', explode(',', $ip_allow_raw))));
    }

    bmDebugLog('IP_ALLOWLIST_PARSED', [
        'allowed_ips' => $allowed_ips,
        'client_ip'   => $client_ip,
    ]);

    if (!empty($allowed_ips) && !PluginBackupmanagerWebhook::ipInAllowlist($client_ip, $allowed_ips)) {
        bmDebugLog('IP_DENY', [
            'client_ip' => $client_ip,
            'allowed'   => $allowed_ips,
        ]);

        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'ip not allowed'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');

    bmDebugLog('RAW_RECEIVED', [
        'raw_len' => is_string($raw) ? strlen($raw) : 0,
        'raw'     => is_string($raw) ? $raw : null,
    ]);

    if ($raw === false || trim($raw) === '') {
        bmDebugLog('EMPTY_PAYLOAD');

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'empty payload'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode($raw, true);

    bmDebugLog('PAYLOAD_DECODED', [
        'json_last_error' => json_last_error(),
        'json_error_msg'  => json_last_error_msg(),
        'is_array'        => is_array($payload),
        'payload_keys'    => is_array($payload) ? array_keys($payload) : [],
        'payload'         => $payload,
    ]);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'invalid json'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($payload['fields']) || !is_array($payload['fields'])) {
        $payload['fields'] = [];
    }

    $normalized_title = bmBuildNormalizedTitle($payload);
    if ($normalized_title !== '') {
        $payload['title'] = $normalized_title;
        $payload['fields']['title'] = $normalized_title;
    }

    bmDebugLog('PAYLOAD_NORMALIZED', [
        'normalized_title' => $normalized_title,
        'fields_keys'      => array_keys($payload['fields']),
        'payload_title'    => $payload['title'] ?? '',
    ]);

    $config = [
        'webhook_enabled' => $enabled,
        'webhook_token'   => $saved_token,
        'allowed_ips'     => $allowed_ips,
    ];

    bmDebugLog('PROCESS_CALL', [
        'client_ip' => $client_ip,
        'config'    => [
            'webhook_enabled' => $enabled,
            'has_token'       => $saved_token !== '',
            'allowed_ips'     => $allowed_ips,
        ],
    ]);

    $result = PluginBackupmanagerWebhook::processPayload($payload, $client_ip, $config);

    bmDebugLog('PROCESS_RESULT', [
        'result' => $result,
    ]);

    http_response_code(!empty($result['success']) ? 200 : 400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    bmDebugLog('EXCEPTION', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => $e->getTraceAsString(),
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'internal error',
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}