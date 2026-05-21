<?php
/**
 * PluginBackupmanagerConfig
 * Configurações globais do plugin (token webhook, IP allowlist etc.)
 */
if (!defined('GLPI_ROOT')) { die("Sorry. You can't access this file directly"); }

class PluginBackupmanagerConfig extends CommonDBTM {

    static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return __('BackupManager Config', 'backupmanager');
    }

    static function canCreate() { return Session::haveRight('config', UPDATE); }
    static function canView()   { return Session::haveRight('config', READ); }
    static function canUpdate() { return Session::haveRight('config', UPDATE); }

    static function getConfig($key, $default = '') {
        $rows = getAllDataFromTable('glpi_plugin_backupmanager_configs',
                                   ['config_key' => $key]);
        $row  = reset($rows);
        return $row ? $row['config_value'] : $default;
    }

    static function setConfig($key, $value) {
        global $DB;
        $rows = getAllDataFromTable('glpi_plugin_backupmanager_configs',
                                   ['config_key' => $key]);
        if ($rows) {
            $DB->update('glpi_plugin_backupmanager_configs',
                ['config_value' => $value, 'date_mod' => date('Y-m-d H:i:s')],
                ['config_key'   => $key]);
        } else {
            $DB->insert('glpi_plugin_backupmanager_configs', [
                'config_key'   => $key,
                'config_value' => $value,
                'date_creation'=> date('Y-m-d H:i:s'),
                'date_mod'     => date('Y-m-d H:i:s'),
            ]);
        }
    }

    static function generateToken(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Exibe a página de configuração do Webhook
     * O processamento do POST é feito exclusivamente em front/config.php
     */
    static function showWebhookConfig() {
        if (!Session::haveRight('config', UPDATE)) {
            Html::displayRightError(); return;
        }

        $token    = self::getConfig('webhook_token', '');
        $enabled  = self::getConfig('webhook_enabled', '0');
        $ip_allow = self::getConfig('webhook_ip_allow', '');
        $endpoint = rtrim(Plugin::getWebDir('backupmanager'), '/')
                  . '/ajax/webhook_pbs.php';
        $action   = Plugin::getWebDir('backupmanager') . '/front/config.php';

        echo "<form method='post' action='" . htmlspecialchars($action) . "'>";

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_2'><th colspan='2'>";
        echo "<i class='ti ti-webhook me-1'></i>"
            . __('Proxmox Backup Server — Webhook Settings', 'backupmanager');
        echo "</th></tr>";

        // Endpoint URL (somente leitura)
        echo "<tr class='tab_bg_1'>";
        echo "<td><strong>" . __('Webhook Endpoint', 'backupmanager') . "</strong></td>";
        echo "<td><code class='bm-code-block'>" . htmlspecialchars($endpoint) . "</code>
              <button type='button' class='btn btn-sm btn-outline-secondary ms-2'
                onclick='navigator.clipboard.writeText(\"" . htmlspecialchars($endpoint, ENT_QUOTES) . "\")'>
                <i class='ti ti-copy'></i> " . __('Copy') . "
              </button></td></tr>";

        // Enabled
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Enable Webhook', 'backupmanager') . "</td><td>";
        echo "<input type='checkbox' name='webhook_enabled' value='1'"
            . ($enabled === '1' ? ' checked' : '') . ">";
        echo "</td></tr>";

        // Token
        echo "<tr class='tab_bg_1'>";
        echo "<td><strong>" . __('Bearer Token', 'backupmanager') . "</strong><br>
              <small class='text-muted'>"
              . __('Use in PBS notification script as: Authorization: Bearer &lt;token&gt;', 'backupmanager')
              . "</small></td><td>";
        if (!empty($token)) {
            echo "<div class='input-group'>";
            echo "<input type='password' id='bm_token' class='form-control font-monospace'
                         name='webhook_token' value='" . htmlspecialchars($token, ENT_QUOTES) . "'
                         autocomplete='off'>";
            echo "<button type='button' class='btn btn-outline-secondary'
                          onclick='var i=document.getElementById(\"bm_token\");
                                   i.type=i.type===\"text\"?\"password\":\"text\"'>
                    <i class='ti ti-eye'></i></button>";
            echo "<button type='button' class='btn btn-outline-secondary'
                          onclick='navigator.clipboard.writeText(document.getElementById(\"bm_token\").value)'>
                    <i class='ti ti-copy'></i></button>";
            echo "</div>";
        } else {
            echo "<em class='text-warning'>" . __('No token set — generate one below.', 'backupmanager') . "</em>";
        }
        echo "</td></tr>";

        // Regenerar token
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Regenerate Token', 'backupmanager') . "</td><td>";
        echo "<input type='checkbox' name='regenerate_token' value='1'> ";
        echo "<small class='text-danger'>"
            . __('Check to generate a new random token (previous token will stop working)', 'backupmanager')
            . "</small>";
        echo "</td></tr>";

        // IP Allowlist
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('IP Allowlist', 'backupmanager') . "<br>
              <small class='text-muted'>"
              . __('Comma-separated IPs or CIDRs. Leave blank to allow any IP.', 'backupmanager')
              . "</small></td><td>";
        echo "<input type='text' name='webhook_ip_allow' class='form-control'
                     value='" . htmlspecialchars($ip_allow, ENT_QUOTES) . "'
                     placeholder='192.168.1.100, 10.0.0.0/8'>";
        echo "</td></tr>";

        // Botão salvar
        echo "<tr class='tab_bg_1'><td colspan='2'>";
        echo "<button type='submit' name='update_webhook_config' value='1' class='btn btn-primary'>";
        echo "<i class='ti ti-device-floppy me-1'></i>" . __('Save') . "</button>";
        echo "</td></tr>";

        // Guia PBS
        echo "<tr class='tab_bg_2'><th colspan='2'>"
            . __('PBS Notification Script', 'backupmanager') . "</th></tr>";
        echo "<tr class='tab_bg_1'><td colspan='2'>";
        self::showPbsGuide($endpoint, $token);
        echo "</td></tr>";

        echo "</table>";
        Html::closeForm();
    }

    static function showPbsGuide($endpoint, $token) {
        $webhook_url  = htmlspecialchars($endpoint);
        $bearer_token = htmlspecialchars($token);

        echo "<div class='alert alert-info'>";
        echo "<strong>" . __('PBS Notification Script', 'backupmanager') . "</strong><br><br>";
        echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:4px;overflow-x:auto;font-size:12px;'>";

        $script  = "#!/bin/bash\n";
        $script .= "WEBHOOK_URL=\"{$webhook_url}\"\n";
        $script .= "BEARER_TOKEN=\"*******************************************************\"n";
        $script .= "\n";
        $script .= "PAYLOAD=\$(\n";
        $script .= "cat <<EOF\n";
        $script .= "{\n";
        $script .= "  \"hostname\": \"\${PBS_HOSTNAME}\",\n";
        $script .= "  \"datastore\": \"\${PBS_DATASTORE}\",\n";
        $script .= "  \"backup_id\": \"\${PBS_BACKUP_ID}\",\n";
        $script .= "  \"backup_time\": \"\${PBS_BACKUP_TIME}\",\n";
        $script .= "  \"verify_state\": \"\${PBS_VERIFY_STATE}\"\n";
        $script .= "}\n";
        $script .= "EOF\n";
        $script .= ")\n";
        $script .= "\n";
        $script .= "curl -s -o /dev/null -w \"%{http_code}\" \\\n";
        $script .= "  -X POST \\\n";
        $script .= "  -H \"Content-Type: application/json\" \\\n";
        $script .= "  -H \"Authorization: Bearer \${BEARER_TOKEN}\" \\\n";
        $script .= "  --data \"\${PAYLOAD}\" \\\n";
        $script .= "  \"\${WEBHOOK_URL}\"\n";

        echo htmlspecialchars($script);
        echo "</pre>";
        echo "<small class='text-muted'>";
        echo __('Make the script executable: <code>chmod +x /etc/proxmox-backup/notify-scripts/glpi.sh</code>', 'backupmanager');
        echo "</small></div>";
    }
}