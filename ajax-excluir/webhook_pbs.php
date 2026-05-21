<?php
/**
 * Endpoint AJAX para receber webhooks do PBS
 */
define('GLPI_ROOT', dirname(__DIR__, 3));
define('GLPI_USE_CSRF_CHECK', false);
define('DO_NOT_CHECK_HTTP_REFERER', true);

include(GLPI_ROOT . '/inc/includes.php');

header('Content-Type: application/json; charset=utf-8');

function bmGetHeader(string $name): string {
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

function bmGetBearerToken(): string {
    $auth = bmGetHeader('Authorization');
    if ($auth !== '' && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim((string)$m[1]);
    }
    return '';
}

try {
    $enabled = trim((string)PluginBackupmanagerConfig::getConfig('webhook_enabled', '0'));
    if ($enabled !== '1') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'webhook disabled'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $saved_token = trim((string)PluginBackupmanagerConfig::getConfig('webhook_token', ''));
    if ($saved_token === '') {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'webhook token not configured'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $provided_token = bmGetBearerToken();
    if ($provided_token === '') {
        $provided_token = bmGetHeader('X-PBS-Token');
    }
    if ($provided_token === '') {
        $provided_token = bmGetHeader('X-BackupManager-Token');
    }

    $client_ip = Toolbox::getRemoteIpAddress();

    if ($provided_token === '' || !hash_equals($saved_token, $provided_token)) {
        $bearer = bmGetBearerToken();
        $xpbs   = bmGetHeader('X-PBS-Token');
        $xbm    = bmGetHeader('X-BackupManager-Token');

        Toolbox::logInFile(
            'backupmanager-webhook',
            "AUTH_FAIL ip={$client_ip} bearer=" . ($bearer !== '' ? 'yes' : 'no') .
            " x-pbs=" . ($xpbs !== '' ? 'yes' : 'no') .
            " x-bm=" . ($xbm !== '' ? 'yes' : 'no') . PHP_EOL
        );

        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'invalid token'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ip_allow_raw = trim((string)PluginBackupmanagerConfig::getConfig('webhook_ip_allow', ''));
    $allowed_ips = [];

    if ($ip_allow_raw !== '') {
        $allowed_ips = array_values(array_filter(array_map('trim', explode(',', $ip_allow_raw))));
    }

    if (!empty($allowed_ips) && !PluginBackupmanagerWebhook::ipInAllowlist($client_ip, $allowed_ips)) {
        Toolbox::logInFile(
            'backupmanager-webhook',
            "IP_DENY ip={$client_ip}" . PHP_EOL
        );

        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'ip not allowed'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    Toolbox::logInFile('backupmanager-webhook', "RAW=" . $raw . PHP_EOL);

    if ($raw === false || trim($raw) === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'empty payload'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'invalid json'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    Toolbox::logInFile(
        'backupmanager-webhook',
        "PAYLOAD=" . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL
    );

    $config = [
        'webhook_enabled' => $enabled,
        'webhook_token'   => $saved_token,
        'allowed_ips'     => $allowed_ips,
    ];

    $result = PluginBackupmanagerWebhook::processPayload($payload, $client_ip, $config);

    Toolbox::logInFile(
        'backupmanager-webhook',
        "RESULT=" . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL
    );

    http_response_code($result['success'] ? 200 : 400);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    Toolbox::logInFile(
        'backupmanager-webhook',
        "EXCEPTION=" . $e->getMessage() . PHP_EOL
    );

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'internal error',
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}