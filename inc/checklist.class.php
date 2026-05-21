<?php
/**
 * PluginBackupmanagerChecklist
 * Itens de validação vinculados a uma Rotina
 * Referências: CIS Control 11.4, ISO 27001 A.8.13
 */
if (!defined('GLPI_ROOT')) { die("Sorry. You can't access this file directly"); }

class PluginBackupmanagerChecklist extends CommonDBTM {

    static $rightname = 'plugin_backupmanager_checklist';

    function getEmpty() {
        parent::getEmpty();
        $this->fields += [
            'plugin_backupmanager_routines_id' => 0,
            'step_order'                       => 0,
            'step_description'                 => '',
            'step_type'                        => 'manual',
            'is_mandatory'                     => 1,
            'reference_standard'               => '',
        ];
    }


    static function getTypeName($nb = 0) {
        return _n('Checklist Item', 'Checklist Items', $nb, 'backupmanager');
    }

    static function canCreate() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, CREATE); }
    static function canView()   { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, READ); }
    static function canUpdate() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, UPDATE); }
    static function canDelete() { return Session::haveRight("config", UPDATE) || Session::haveRight(static::$rightname, DELETE); }

    static function showForRoutine($routines_id) {
        global $DB;
        $canedit = Session::haveRight(static::$rightname, UPDATE);

        $result = $DB->query("SELECT * FROM `glpi_plugin_backupmanager_checklists`
            WHERE `plugin_backupmanager_routines_id` = ".(int)$routines_id."
            ORDER BY `step_order` ASC");

        echo "<div class='bm-checklist-wrapper'>";
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='noHover'><th>#</th><th>".__('Step Description','backupmanager')."</th>
              <th>".__('Type','backupmanager')."</th><th>".__('Mandatory','backupmanager')."</th>
              <th>".__('Standard Reference','backupmanager')."</th>";
        if ($canedit) echo "<th>".__('Actions')."</th>";
        echo "</tr>";

        while ($row = $DB->fetchAssoc($result)) {
            $ref = self::getReferenceBadge($row['reference_standard']);
            echo "<tr class='tab_bg_1'>";
            echo "<td>".(int)$row['step_order']."</td>";
            echo "<td>".htmlspecialchars($row['step_description'])."</td>";
            echo "<td>".($row['step_type']==='automated'
                ? "<span class='badge bg-success'>Auto</span>"
                : "<span class='badge bg-secondary'>Manual</span>")."</td>";
            echo "<td>".($row['is_mandatory']
                ? "<span class='badge bg-danger'>".__('Yes')."</span>"
                : "<span class='badge bg-light text-dark'>".__('No')."</span>")."</td>";
            echo "<td>$ref</td>";
            if ($canedit) {
                $url = Plugin::getWebDir('backupmanager').'/front/checklist.form.php?id='.(int)$row['id'];
                echo "<td><a href='$url' class='btn btn-sm btn-primary'>".__('Edit')."</a></td>";
            }
            echo "</tr>";
        }
        echo "</table>";

        if ($canedit) {
            $addUrl = Plugin::getWebDir('backupmanager').'/front/checklist.form.php?plugin_backupmanager_routines_id='.(int)$routines_id;
            echo "<div class='mt-2'><a href='$addUrl' class='btn btn-primary'><i class='fa fa-plus'></i> ".__('Add checklist item','backupmanager')."</a></div>";
        }
        echo "</div>";
    }

    static function getReferenceBadge($standard) {
        $map = [
            'ISO27001' => ['label'=>'ISO 27001','class'=>'bg-primary'],
            'CIS'      => ['label'=>'CIS',      'class'=>'bg-success'],
            'NIS2'     => ['label'=>'NIS2',     'class'=>'bg-warning text-dark'],
            'INTERNAL' => ['label'=>'Internal', 'class'=>'bg-secondary'],
        ];
        $s = $map[$standard] ?? ['label'=>($standard?:'---'),'class'=>'bg-light text-dark'];
        return "<span class='badge {$s['class']}'>{$s['label']}</span>";
    }

    function showForm($ID, $options = []) {
        if (!static::canView()) return false;
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        if (isset($options['plugin_backupmanager_routines_id'])) {
            echo Html::hidden('plugin_backupmanager_routines_id',
                ['value'=>(int)$options['plugin_backupmanager_routines_id']]);
        }

        echo "<tr class='tab_bg_1'><td>".__('Order','backupmanager')."</td><td>";
        echo Html::input('step_order',['type'=>'number','value'=>($this->fields['step_order'] ?? 0),'min'=>0]);
        echo "</td><td>".__('Type','backupmanager')."</td><td>";
        Dropdown::showFromArray('step_type',
            ['manual'=>__('Manual','backupmanager'),'automated'=>__('Automated','backupmanager')],
            ['value'=>($this->fields['step_type'] ?? '')]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>".__('Description','backupmanager')."</td><td colspan='3'>";
        echo Html::textarea(['name'=>'step_description','value'=>($this->fields['step_description'] ?? ''),'cols'=>80,'rows'=>3]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>".__('Mandatory','backupmanager')."</td><td>";
        Dropdown::showYesNo('is_mandatory', ($this->fields['is_mandatory'] ?? 1));
        echo "</td><td>".__('Standard Reference','backupmanager')."</td><td>";
        Dropdown::showFromArray('reference_standard',[
            ''=>'---','ISO27001'=>'ISO 27001','CIS'=>'CIS Controls','NIS2'=>'NIS2','INTERNAL'=>__('Internal','backupmanager'),
        ],['value'=>($this->fields['reference_standard'] ?? '')]);
        echo "</td></tr>";

        $this->showFormButtons($options);
        return true;
    }

    function getSearchOptions() {
        $tab = [];
        $tab[] = ['id'=>'common','name'=>self::getTypeName(2)];
        $tab[] = ['id'=>1,'table'=>$this->getTable(),'field'=>'step_description','name'=>__('Description','backupmanager'),'datatype'=>'text'];
        $tab[] = ['id'=>2,'table'=>$this->getTable(),'field'=>'reference_standard','name'=>__('Standard','backupmanager'),'datatype'=>'string'];
        $tab[] = ['id'=>3,'table'=>$this->getTable(),'field'=>'is_mandatory','name'=>__('Mandatory','backupmanager'),'datatype'=>'bool'];
        return $tab;
    }

    static function createDefaultItems($routines_id) {
        $items = [
            [10,'Verify backup job completed without errors (check logs)','CIS','manual',1],
            [20,'Validate backup file integrity (checksum / hash verification)','ISO27001','manual',1],
            [30,'Confirm backup was stored at the correct destination path','CIS','manual',1],
            [40,'Check backup size is within expected range (not zero / not too small)','ISO27001','manual',1],
            [50,'Confirm encryption was applied (if enabled)','NIS2','manual',1],
            [60,'Test restore of a sample file/database to verify recoverability (quarterly)','ISO27001','manual',0],
            [70,'Verify old backups were purged according to retention policy','CIS','manual',1],
            [80,'Confirm off-site / geo-redundant copy transfer completed','NIS2','manual',0],
            [90,'Notify technician in charge of any anomaly detected','INTERNAL','manual',1],
            [100,'Update backup register log with execution details','ISO27001','manual',1],
        ];
        $obj = new self();
        foreach ($items as $i) {
            $obj->add([
                'plugin_backupmanager_routines_id' => $routines_id,
                'entities_id'        => $_SESSION['glpiactive_entity'] ?? 0,
                'step_order'         => $i[0],
                'step_description'   => $i[1],
                'reference_standard' => $i[2],
                'step_type'          => $i[3],
                'is_mandatory'       => $i[4],
            ]);
        }
    }
}
