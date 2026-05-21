<?php
/**
 * PluginBackupmanagerProfile
 * GLPI 10.x — aba em Perfis
 * Correção: getTabNameForItem e displayTabContentForItem NÃO são static (CommonGLPI)
 */
if (!defined('GLPI_ROOT')) { die("Sorry. You can't access this file directly"); }

class PluginBackupmanagerProfile extends CommonDBTM {

    static $rightname = 'profile';

    // ── Direitos do plugin ───────────────────────────────────────────────────
    static function getAllRights() {
        return [
            ['label' => 'Dashboard',            'field' => 'plugin_backupmanager_dashboard'],
            ['label' => 'Backup Servers',        'field' => 'plugin_backupmanager_server'],
            ['label' => 'Storage Destinations',  'field' => 'plugin_backupmanager_destination'],
            ['label' => 'Backup Routines',       'field' => 'plugin_backupmanager_routine'],
            ['label' => 'Checklists',            'field' => 'plugin_backupmanager_checklist'],
            ['label' => 'Backup Logs',           'field' => 'plugin_backupmanager_log'],
        ];
    }

    // ── OBRIGATÓRIO — instância, não static ─────────────────────────────────
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item instanceof Profile) {
            return __('BackupManager', 'backupmanager');
        }
        return '';
    }

    // ── OBRIGATÓRIO — instância, não static ─────────────────────────────────
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item instanceof Profile) {
            self::showForProfile($item->getID());
        }
        return true;
    }

    // ── Formulário de direitos ───────────────────────────────────────────────
    static function showForProfile($profiles_id = 0) {
        $canedit = Session::haveRight('profile', UPDATE);

        if ($canedit) {
            $target = Toolbox::getItemTypeFormURL('Profile');
            echo "<form method='post' action='$target'>";
            echo Html::hidden('id',               ['value' => $profiles_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        }

        echo "<table class='tab_cadre_fixe'>";

        // Cabeçalho
        echo "<tr class='tab_bg_2'>";
        echo "<th>" . __('Feature', 'backupmanager') . "</th>";
        foreach ([READ => __('Read'), CREATE => __('Create'),
                  UPDATE => __('Update'), DELETE => __('Delete'),
                  PURGE  => __('Purge')] as $bit => $label) {
            echo "<th class='center'>$label</th>";
        }
        echo "</tr>";

        foreach (self::getAllRights() as $right) {
            $current = self::getProfileRightValue($profiles_id, $right['field']);

            echo "<tr class='tab_bg_1'>";
            echo "<td><strong>" . $right['label'] . "</strong></td>";

            foreach ([READ, CREATE, UPDATE, DELETE, PURGE] as $bit) {
                $checked = ($current & $bit) ? 'checked' : '';
                echo "<td class='center'>";
                if ($canedit) {
                    echo "<input type='checkbox'
                               name='_rights[{$right['field']}][{$bit}]'
                               value='1' $checked>";
                } else {
                    echo ($current & $bit) ? '✔' : '–';
                }
                echo "</td>";
            }
            echo "</tr>";
        }

        echo "</table>";

        if ($canedit) {
            echo "<div class='center mt-2'>";
            echo Html::submit(__('Save'), [
                'name'  => 'update_backupmanager_rights',
                'class' => 'btn btn-primary',
            ]);
            echo "</div>";
            Html::closeForm();
        }
    }

    // ── Hook pre_item_update ─────────────────────────────────────────────────
    static function preItemUpdate(Profile $item) {
        if (isset($_POST['update_backupmanager_rights'], $_POST['_rights'])) {
            self::processRightsForm($item->getID(), $_POST['_rights']);
        }
    }

    static function processRightsForm($profiles_id, $posted) {
        if (!Session::haveRight('profile', UPDATE)) return;
        foreach (self::getAllRights() as $right) {
            $bits  = $posted[$right['field']] ?? [];
            $value = 0;
            foreach ([READ, CREATE, UPDATE, DELETE, PURGE] as $bit) {
                if (!empty($bits[$bit])) $value |= $bit;
            }
            self::grantRightToProfile($profiles_id, $right['field'], $value);
        }
    }

    // ── Helpers DB ───────────────────────────────────────────────────────────
    static function getProfileRightValue($profiles_id, $rightname) {
        global $DB;
        foreach ($DB->request([
            'SELECT' => ['rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['profiles_id' => (int)$profiles_id, 'name' => $rightname],
        ]) as $row) {
            return (int)$row['rights'];
        }
        return 0;
    }

    static function grantRightToProfile($profiles_id, $rightname, $value) {
        global $DB;
        $count = countElementsInTable('glpi_profilerights', [
            'profiles_id' => (int)$profiles_id,
            'name'        => $rightname,
        ]);
        if ($count === 0) {
            $DB->insert('glpi_profilerights', [
                'profiles_id' => (int)$profiles_id,
                'name'        => $rightname,
                'rights'      => (int)$value,
            ]);
        } else {
            $DB->update('glpi_profilerights',
                ['rights'      => (int)$value],
                ['profiles_id' => (int)$profiles_id, 'name' => $rightname]
            );
        }
    }

    static function initProfile() {
        foreach (self::getAllRights() as $right) {
            if (countElementsInTable('glpi_profilerights', ['name' => $right['field']]) == 0) {
                ProfileRight::addProfileRights([$right['field']]);
            }
        }
    }

    static function createFirstAccess($profiles_id) {
        $full = READ | CREATE | UPDATE | DELETE | PURGE;
        foreach (self::getAllRights() as $right) {
            self::grantRightToProfile($profiles_id, $right['field'], $full);
        }
    }

    static function removeRights() {
        global $DB;
        foreach (self::getAllRights() as $right) {
            $DB->delete('glpi_profilerights', ['name' => $right['field']]);
        }
    }
}
