<?php
/**
 * PluginBackupmanagerDashboard
 * GLPI 10.0.18 compatible
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginBackupmanagerDashboard extends CommonGLPI {

    static $rightname = 'plugin_backupmanager_dashboard';

    static function getTypeName($nb = 0) {
        return __('Backup Manager', 'backupmanager');
    }

    static function canView() {
        return Session::haveRight("config", UPDATE)
            || Session::haveRight(static::$rightname, READ);
    }

    static function getMenuName() {
        return __('Backup Manager', 'backupmanager');
    }

    static function getMenuContent() {
        if (!Session::haveRight("config", UPDATE)
            && !Session::haveRight(static::$rightname, READ)) {
            return false;
        }

        $base = Plugin::getWebDir('backupmanager');

        $menu = [
            'title' => self::getMenuName(),
            'page'  => "$base/front/dashboard.php",
            'icon'  => 'ti ti-database',
        ];

        $menu['options']['dashboard'] = [
            'title' => __('Dashboard', 'backupmanager'),
            'page'  => "$base/front/dashboard.php",
            'icon'  => 'ti ti-layout-dashboard',
        ];
        $menu['options']['server'] = [
            'title' => __('Backup Servers', 'backupmanager'),
            'page'  => "$base/front/server.php",
            'icon'  => 'ti ti-server',
            'links' => [
                'add'    => "$base/front/server.form.php",
                'search' => "$base/front/server.php",
            ],
        ];
        $menu['options']['destination'] = [
            'title' => __('Storage Buckets', 'backupmanager'),
            'page'  => "$base/front/destination.php",
            'icon'  => 'ti ti-device-floppy',
            'links' => [
                'add'    => "$base/front/destination.form.php",
                'search' => "$base/front/destination.php",
            ],
        ];
        $menu['options']['routine'] = [
            'title' => __('Backup Routines', 'backupmanager'),
            'page'  => "$base/front/routine.php",
            'icon'  => 'ti ti-clock',
            'links' => [
                'add'    => "$base/front/routine.form.php",
                'search' => "$base/front/routine.php",
            ],
        ];
        $menu['options']['log'] = [
            'title' => __('Backup Logs', 'backupmanager'),
            'page'  => "$base/front/log.php",
            'icon'  => 'ti ti-list',
            'links' => [
                'search' => "$base/front/log.php",
            ],
        ];
        $menu['options']['config'] = [
            'title' => __('Webhook / PBS Config', 'backupmanager'),
            'page'  => "$base/front/config.php",
            'icon'  => 'fas fa-plug',
        ];

        return $menu;
    }

    static function showDashboard() {
        global $DB;

        if (!self::canView()) {
            Html::displayRightError();
            return;
        }

        $tables_ok = $DB->tableExists('glpi_plugin_backupmanager_routines')
                  && $DB->tableExists('glpi_plugin_backupmanager_servers')
                  && $DB->tableExists('glpi_plugin_backupmanager_destinations')
                  && $DB->tableExists('glpi_plugin_backupmanager_logs');

        if (!$tables_ok) {
            echo "<div class='alert alert-warning m-3'>"
                . "<i class='ti ti-alert-triangle'></i> "
                . __('Plugin tables not found. Please reinstall the plugin.', 'backupmanager')
                . "</div>";
            return;
        }

        $total_routines = countElementsInTable(
            'glpi_plugin_backupmanager_routines', ['is_active' => 1]
        );
        $total_servers  = countElementsInTable(
            'glpi_plugin_backupmanager_servers', ['is_active' => 1]
        );
        $total_dests    = countElementsInTable(
            'glpi_plugin_backupmanager_destinations', ['is_active' => 1]
        );
        $total_logs     = countElementsInTable('glpi_plugin_backupmanager_logs');

        $last7 = date('Y-m-d H:i:s', strtotime('-7 days'));

        $success_7d = 0;
        $failed_7d  = 0;

        $tbl = 'glpi_plugin_backupmanager_logs';
        foreach (['success' => &$success_7d, 'failed' => &$failed_7d] as $status => &$counter) {
            $res = $DB->query(
                "SELECT COUNT(*) AS cnt
                 FROM `$tbl`
                 WHERE `status` = '" . $DB->escape($status) . "'
                   AND `execution_date` >= '" . $DB->escape($last7) . "'"
            );
            if ($res) {
                $row     = $DB->fetchAssoc($res);
                $counter = (int)($row['cnt'] ?? 0);
            }
        }
        unset($counter);

        $failed_servers = [];
        $res = $DB->query(
            "SELECT `id`, `name`
             FROM `glpi_plugin_backupmanager_servers`
             WHERE `last_backup_status` = 'failed'
               AND `is_active` = 1"
        );
        if ($res) {
            while ($row = $DB->fetchAssoc($res)) {
                $failed_servers[] = $row;
            }
        }

        echo "<div class='bm-dashboard p-3'>";
        echo "<h2 class='mb-4'>"
            . "<i class='ti ti-database me-2'></i>"
            . __('Backup Manager Dashboard', 'backupmanager')
            . "</h2>";

        echo "<div class='bm-kpi-row'>";
        self::kpiCard(__('Active Routines', 'backupmanager'), $total_routines, 'ti ti-clock', 'primary', 'front/routine.php');
        self::kpiCard(__('Active Servers', 'backupmanager'), $total_servers, 'ti ti-server', 'info', 'front/server.php');
        self::kpiCard(__('Storage Buckets', 'backupmanager'), $total_dests, 'ti ti-device-floppy', 'secondary', 'front/destination.php');
        self::kpiCard(__('Successes (7d)', 'backupmanager'), $success_7d, 'ti ti-check', 'success', 'front/log.php');
        self::kpiCard(__('Failures (7d)', 'backupmanager'), $failed_7d, 'ti ti-x', 'danger', 'front/log.php');
        self::kpiCard(__('Total Logs', 'backupmanager'), $total_logs, 'ti ti-list', 'secondary', 'front/log.php');
        echo "</div>";

        $base = Plugin::getWebDir('backupmanager');
        echo "<h3 class='mt-4 mb-2'>"
            . "<i class='ti ti-bolt me-1'></i>"
            . __('Quick Actions', 'backupmanager')
            . "</h3>";
        echo "<div class='d-flex gap-2 flex-wrap'>";

        foreach ([
            [$base . '/front/server.form.php',      'ti ti-plus',          __('Add Server', 'backupmanager')],
            [$base . '/front/destination.form.php', 'ti ti-plus',          __('Add Bucket', 'backupmanager')],
            [$base . '/front/routine.form.php',     'ti ti-plus',          __('Add Routine', 'backupmanager')],
            [$base . '/front/log.form.php',         'ti ti-plus',          __('Register Log', 'backupmanager')],
            [$base . '/front/config.php',           'ti ti-webhook',       __('Webhook Settings', 'backupmanager')],
            [$base . '/front/schedule.php',         'ti ti-calendar-time', __('Schedule', 'backupmanager')],
        ] as [$url, $icon, $label]) {
            echo "<a href='$url' class='btn btn-sm btn-outline-secondary'>"
                . "<i class='$icon me-1'></i>$label</a>";
        }

        echo "</div>";

        if (!empty($failed_servers)) {
            echo "<div class='alert alert-danger mt-3'>"
                . "<strong><i class='ti ti-alert-triangle me-1'></i>"
                . __('Servers with last backup FAILED:', 'backupmanager')
                . "</strong><ul class='mb-0 mt-1'>";
            foreach ($failed_servers as $srv) {
                $url = Plugin::getWebDir('backupmanager') . '/front/server.form.php?id=' . (int)$srv['id'];
                echo "<li><a href='$url'>" . htmlspecialchars($srv['name']) . "</a></li>";
            }
            echo "</ul></div>";
        }

        echo "<h3 class='mt-4 mb-2'>"
            . "<i class='ti ti-history me-1'></i>"
            . __('Recent Backup Executions', 'backupmanager')
            . "</h3>";

        $res = $DB->query(
            "SELECT l.*, r.name AS routine_name
             FROM `glpi_plugin_backupmanager_logs` l
             LEFT JOIN `glpi_plugin_backupmanager_routines` r
               ON r.id = l.plugin_backupmanager_routines_id
             ORDER BY l.execution_date DESC, l.id DESC
             LIMIT 10"
        );

        if (!$res || $DB->numrows($res) === 0) {
            echo "<div class='alert alert-info'>"
                . "<i class='ti ti-info-circle me-1'></i>"
                . __('No backup executions recorded yet.', 'backupmanager')
                . "</div>";
        } else {
            echo "<table class='tab_cadre_fixehov'>";
            echo "<tr class='noHover'>
                    <th>" . __('Date', 'backupmanager') . "</th>
                    <th>" . __('Name') . "</th>
                    <th>" . __('Routine', 'backupmanager') . "</th>
                    <th>" . __('Status') . "</th>
                    <th>" . __('Size (MB)', 'backupmanager') . "</th>
                    <th>" . __('Verified', 'backupmanager') . "</th>
                    <th>" . __('Restore Tested', 'backupmanager') . "</th>
                  </tr>";

            while ($row = $DB->fetchAssoc($res)) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') {
                    $name = $row['routine_name'] ?? '---';
                }

                echo "<tr class='tab_bg_1'>";
                echo "<td>" . Html::convDateTime($row['execution_date'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($name) . "</td>";
                echo "<td>" . htmlspecialchars($row['routine_name'] ?? '---') . "</td>";
                echo "<td>" . PluginBackupmanagerLog::getStatusBadge($row['status'] ?? '') . "</td>";
                echo "<td>" . (!empty($row['size_mb']) ? number_format((float)$row['size_mb'], 2) : '---') . "</td>";
                echo "<td>" . (!empty($row['verified'])
                    ? "<span class='badge bg-success'>OK</span>"
                    : "<span class='badge bg-warning text-dark'>No</span>") . "</td>";
                echo "<td>" . (!empty($row['restore_tested'])
                    ? "<span class='badge bg-success'>OK</span>"
                    : "<span class='badge bg-secondary'>No</span>") . "</td>";
                echo "</tr>";
            }

            echo "</table>";
        }

        
        echo "</div>";
    }

    private static function kpiCard($label, $value, $icon, $color, $link = '') {
        $base = Plugin::getWebDir('backupmanager');
        $url  = $link ? "$base/$link" : '#';

        echo "<a href='$url' class='bm-kpi-card bm-kpi-{$color} text-decoration-none'>
                <div class='bm-kpi-icon'><i class='{$icon}'></i></div>
                <div class='bm-kpi-value'>" . (int)$value . "</div>
                <div class='bm-kpi-label'>" . htmlspecialchars($label) . "</div>
              </a>";
    }
}