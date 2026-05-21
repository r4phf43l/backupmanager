<?php
include('../../../inc/includes.php');
Session::checkLoginUser();

if (!Session::haveRight('config', READ) && !Session::haveRight('plugin_backupmanager_routine', READ)) {
   Html::displayRightError();
}

Html::header(
   __('Backup Schedule','backupmanager'),
   $_SERVER['PHP_SELF'],
   'management',
   'PluginBackupmanagerDashboard',
   'routine'
);

$routine = new PluginBackupmanagerRoutine();
$rows = $routine->find([], ['is_active DESC', 'name ASC']);

function bmBucketLabel($bucket) {
   $labels = [
      'frequent'    => __('Frequent','backupmanager'),
      'hourly'      => __('Hourly','backupmanager'),
      'daily'       => __('Daily','backupmanager'),
      'weekly'      => __('Weekly','backupmanager'),
      'monthly'     => __('Monthly','backupmanager'),
      'custom'      => __('Custom','backupmanager'),
      'unscheduled' => __('Unscheduled','backupmanager'),
   ];
   return $labels[$bucket] ?? $bucket;
}

function bmPriorityClass($priority) {
   switch ((int)$priority) {
      case PluginBackupmanagerRoutine::PRIORITY_CRITICAL: return 'is-critical';
      case PluginBackupmanagerRoutine::PRIORITY_HIGH:     return 'is-high';
      case PluginBackupmanagerRoutine::PRIORITY_LOW:      return 'is-low';
      default:                                            return 'is-medium';
   }
}

function bmDayName(int $d): string {
    $days = [
        0 => __('Sun','backupmanager'), 1 => __('Mon','backupmanager'),
        2 => __('Tue','backupmanager'), 3 => __('Wed','backupmanager'),
        4 => __('Thu','backupmanager'), 5 => __('Fri','backupmanager'),
        6 => __('Sat','backupmanager'),
    ];
    return $days[$d] ?? (string)$d;
}

function bmMonthName(int $m): string {
    $months = [
        1 => __('Jan','backupmanager'),  2 => __('Feb','backupmanager'),
        3 => __('Mar','backupmanager'),  4 => __('Apr','backupmanager'),
        5 => __('May','backupmanager'),  6 => __('Jun','backupmanager'),
        7 => __('Jul','backupmanager'),  8 => __('Aug','backupmanager'),
        9 => __('Sep','backupmanager'), 10 => __('Oct','backupmanager'),
        11 => __('Nov','backupmanager'),12 => __('Dec','backupmanager'),
    ];
    return $months[$m] ?? (string)$m;
}

function bmIsSingleValue(string $field): bool {
    return ctype_digit(trim($field));
}

function bmExpandField(string $field, callable $labelFn, int $min, int $max): ?string {
    $field = trim($field);
    if ($field === '*') return null;
    if (preg_match('/^\\*\\/(\\d+)$/', $field, $m)) {
        return sprintf(__('every %s', 'backupmanager'), $m[1]);
    }
    $parts  = explode(',', $field);
    $labels = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/^(\\d+)-(\\d+)$/', $part, $m)) {
            $from = (int)$m[1];
            $to   = (int)$m[2];
            $labels[] = ($from === $to) ? $labelFn($from) : $labelFn($from) . '–' . $labelFn($to);
        } elseif (ctype_digit($part)) {
            $labels[] = $labelFn((int)$part);
        } else {
            $labels[] = $part;
        }
    }
    return implode(', ', $labels);
}

function bmCronSummary(string $cron): string {
    $cron = trim($cron);
    if ($cron === '') return __('No schedule', 'backupmanager');
    $parts = preg_split('/\\s+/', $cron);
    if (count($parts) !== 5) return $cron;
    [$minute, $hour, $dom, $month, $dow] = $parts;
    if ($cron === '* * * * *') return __('Every minute', 'backupmanager');
    if (preg_match('/^\\*\\/(\\d+) \\* \\* \\* \\*$/', $cron, $m))
        return sprintf(__('Every %s minutes', 'backupmanager'), $m[1]);
    if (preg_match('/^0 \\*\\/(\\d+) \\* \\* \\*$/', $cron, $m))
        return sprintf(__('Every %s hours', 'backupmanager'), $m[1]);
    $minuteStr = bmExpandField($minute, fn($v) => sprintf('%02d', $v), 0, 59);
    $hourStr   = bmExpandField($hour,   fn($v) => sprintf('%02d', $v), 0, 23);
    $domStr    = bmExpandField($dom,    fn($v) => (string)$v,          1, 31);
    $monthStr  = bmExpandField($month,  'bmMonthName',                 1, 12);
    $dowStr    = bmExpandField($dow,    'bmDayName',                   0, 6);
    $timeStr = '';
    if ($hourStr === null && $minuteStr === null) {
        $timeStr = '';
    } elseif (ctype_digit($hour) && ctype_digit($minute)) {
        $timeStr = sprintf(__('at %02d:%02d', 'backupmanager'), (int)$hour, (int)$minute);
    } elseif ($hourStr !== null && $minuteStr !== null) {
        $timeStr = sprintf(__('at hour %s, minute %s', 'backupmanager'), $hourStr, $minuteStr);
    } elseif ($hourStr !== null) {
        $timeStr = sprintf(__('at hour %s', 'backupmanager'), $hourStr);
    } elseif ($minuteStr !== null) {
        $timeStr = sprintf(__('at minute %s', 'backupmanager'), $minuteStr);
    }
    $parts_out = [];
    if ($dowStr !== null)   $parts_out[] = sprintf(__('on %s', 'backupmanager'),   $dowStr);
    if ($monthStr !== null) $parts_out[] = sprintf(__('in %s', 'backupmanager'),   $monthStr);
    if ($domStr !== null)   $parts_out[] = sprintf(__('day %s', 'backupmanager'),  $domStr);
    $when = implode(', ', $parts_out);
    if ($when && $timeStr) return ucfirst($when) . ' ' . $timeStr;
    if ($when)             return ucfirst($when);
    if ($timeStr)          return sprintf(__('Daily %s', 'backupmanager'), $timeStr);
    return $cron;
}

function bmTimeBucket(string $cron): string {
    $cron = trim($cron);
    if ($cron === '') return 'unscheduled';
    $parts = preg_split('/\\s+/', $cron);
    if (count($parts) !== 5) return 'custom';
    [$minute, $hour, $dom, $month, $dow] = $parts;
    if ($cron === '* * * * *') return 'frequent';
    if (preg_match('/^\\*\\/(\\d+) \\* \\* \\* \\*$/', $cron, $m) && (int)$m[1] <= 15) return 'frequent';
    if (preg_match('/^\\*\\/\\d+ \\* \\* \\* \\*$/', $cron))  return 'hourly';
    if (preg_match('/^0 \\*\\/\\d+ \\* \\* \\*$/', $cron))   return 'hourly';
    $isDomWild = ($dom   === '*');
    $isMonWild = ($month === '*');
    $isDowWild = ($dow   === '*');
    $hasFixedTime = ctype_digit($hour) && ctype_digit($minute);
    $dowIsSingleDay = bmIsSingleValue($dow);
    if ($isDomWild && $isMonWild && !$isDowWild && $dowIsSingleDay && $hasFixedTime) return 'weekly';
    if ($isDomWild && $isMonWild && $hasFixedTime) return 'daily';
    if (!$isDomWild && $isMonWild && $isDowWild && bmIsSingleValue($dom) && $hasFixedTime) return 'monthly';
    return 'custom';
}

/**
 * Extracts numeric DOW values from a cron dow field.
 * Returns array of ints [0..6], or empty if wildcard/complex.
 */
function bmExtractDow(string $dowField): array {
    $dowField = trim($dowField);
    if ($dowField === '*') return [];
    $result = [];
    foreach (explode(',', $dowField) as $part) {
        $part = trim($part);
        if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
            for ($i = (int)$m[1]; $i <= (int)$m[2]; $i++) $result[] = $i;
        } elseif (ctype_digit($part)) {
            $result[] = (int)$part;
        }
    }
    return array_unique($result);
}

/**
 * Extracts numeric hour values from a cron hour field.
 * Returns array of ints [0..23], or empty if wildcard/step.
 */
function bmExtractHours(string $hourField): array {
    $hourField = trim($hourField);
    if ($hourField === '*') return [];
    if (str_contains($hourField, '/')) return [];
    $result = [];
    foreach (explode(',', $hourField) as $part) {
        $part = trim($part);
        if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
            for ($i = (int)$m[1]; $i <= (int)$m[2]; $i++) $result[] = $i;
        } elseif (ctype_digit($part)) {
            $result[] = (int)$part;
        }
    }
    return array_unique($result);
}

// ── Build enriched rows (add bucket, parsed dow/hours for JS filtering) ──────
$enrichedRows = [];
foreach ($rows as $row) {
    $cron   = trim((string)($row['schedule_cron'] ?? ''));
    $bucket = bmTimeBucket($cron);
    $parts  = preg_split('/\s+/', $cron);

    $dowValues  = [];
    $hourValues = [];

    if (count($parts) === 5) {
        $dowValues  = bmExtractDow($parts[4]);
        $hourValues = bmExtractHours($parts[1]);
    }

    $enrichedRows[] = [
        'id'          => (int)($row['id'] ?? 0),
        'name'        => Toolbox::stripTags($row['name'] ?? ''),
        'cron'        => $cron,
        'summary'     => Toolbox::stripTags(bmCronSummary($cron)),
        'bucket'      => $bucket,
        'bucketLabel' => bmBucketLabel($bucket),
        'priorityClass' => bmPriorityClass((int)($row['priority'] ?? 0)),
        'priorityLabel' => PluginBackupmanagerRoutine::getPriorityLabel((int)($row['priority'] ?? 0)),
        'sourceLabel' => PluginBackupmanagerRoutine::getSourceTypeLabel($row['source_type'] ?? ''),
        'active'      => !empty($row['is_active']),
        'activeLabel' => !empty($row['is_active']) ? __('Active') : __('Inactive'),
        'desc'        => Toolbox::stripTags($row['schedule_description'] ?? ''),
        'dow'         => $dowValues,   // [] = any/wildcard
        'hours'       => $hourValues,  // [] = any/wildcard
    ];
}

$jsonRows    = json_encode($enrichedRows, JSON_HEX_TAG | JSON_HEX_APOS);
$totalCount  = count($rows);
$withCron    = count(array_filter($rows, fn($r) => trim((string)($r['schedule_cron'] ?? '')) !== ''));
$unscheduled = count(array_filter($enrichedRows, fn($r) => $r['bucket'] === 'unscheduled'));

// ── i18n strings for JS ───────────────────────────────────────────────────────
$i18n = json_encode([
   'noRoutines'  => __('No routines match the current filters.', 'backupmanager'),
   'noInBucket'  => __('No routines in this bucket.', 'backupmanager'),
   'cronLabel'   => __('CRON', 'backupmanager'),
   'buckets'     => [
      'frequent'    => __('Frequent','backupmanager'),
      'hourly'      => __('Hourly','backupmanager'),
      'daily'       => __('Daily','backupmanager'),
      'weekly'      => __('Weekly','backupmanager'),
      'monthly'     => __('Monthly','backupmanager'),
      'custom'      => __('Custom','backupmanager'),
      'unscheduled' => __('Unscheduled','backupmanager'),
   ],
   'days' => [
      __('Sun','backupmanager'), __('Mon','backupmanager'), __('Tue','backupmanager'),
      __('Wed','backupmanager'), __('Thu','backupmanager'), __('Fri','backupmanager'),
      __('Sat','backupmanager'),
   ],
]);

// ── Day-of-week labels for filter UI ─────────────────────────────────────────
$dowLabels = [
   0 => __('Sunday','backupmanager'),    1 => __('Monday','backupmanager'),
   2 => __('Tuesday','backupmanager'),   3 => __('Wednesday','backupmanager'),
   4 => __('Thursday','backupmanager'),  5 => __('Friday','backupmanager'),
   6 => __('Saturday','backupmanager'),
];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
/* ── Design tokens ─────────────────────────────────────────── */
:root {
  --bm-bg:#f7f6f2; --bm-surface:#fbfbf9; --bm-surface-2:#f3f0ec;
  --bm-surface-3:#edeae5; --bm-border:oklch(0.28 0.01 80 / 0.12);
  --bm-text:#28251d; --bm-muted:#7a7974; --bm-faint:#bab9b4;
  --bm-primary:#01696f; --bm-primary-hi:#cedcd8;
  --bm-critical:#a13544; --bm-high:#da7101; --bm-medium:#006494; --bm-low:#437a22;
  --bm-radius-sm:6px; --bm-radius-md:10px; --bm-radius-lg:14px; --bm-radius-xl:18px;
  --bm-shadow-sm:0 1px 2px oklch(0.2 0.01 80/.06);
  --bm-shadow-md:0 4px 12px oklch(0.2 0.01 80/.08);
  --bm-trans:180ms cubic-bezier(.16,1,.3,1);
  --bm-font:'Satoshi','Inter',system-ui,sans-serif;
}
@media(prefers-color-scheme:dark){:root{
  --bm-bg:#171614; --bm-surface:#1c1b19; --bm-surface-2:#22211f;
  --bm-surface-3:#2d2c2a; --bm-border:oklch(0.9 0 0 / 0.08);
  --bm-text:#cdccca; --bm-muted:#797876; --bm-faint:#5a5957;
  --bm-primary:#4f98a3; --bm-primary-hi:#313b3b;
  --bm-critical:#dd6974; --bm-high:#fdab43; --bm-medium:#5591c7; --bm-low:#6daa45;
  --bm-shadow-sm:0 1px 2px oklch(0 0 0/.2); --bm-shadow-md:0 4px 12px oklch(0 0 0/.3);
}}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--bm-font);font-size:15px;color:var(--bm-text);background:var(--bm-bg);line-height:1.5}
button{cursor:pointer;background:none;border:none;font:inherit;color:inherit;transition:color var(--bm-trans),background var(--bm-trans),box-shadow var(--bm-trans)}

/* ── Layout ────────────────────────────────────────────────── */
.bm-wrap{padding:clamp(16px,3vw,32px)}
.bm-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.bm-title{font-size:clamp(20px,2.5vw,28px);font-weight:700;line-height:1.1}
.bm-sub{color:var(--bm-muted);font-size:13px;margin-top:4px}

/* ── Stats ─────────────────────────────────────────────────── */
.bm-stats{display:flex;gap:10px;flex-wrap:wrap}
.bm-stat{background:var(--bm-surface);border:1px solid var(--bm-border);border-radius:var(--bm-radius-md);padding:10px 14px;min-width:110px;box-shadow:var(--bm-shadow-sm)}
.bm-stat span{font-size:12px;color:var(--bm-muted);display:block}
.bm-stat b{display:block;font-size:22px;font-weight:700;font-variant-numeric:tabular-nums}

/* ── Filter bar ────────────────────────────────────────────── */
.bm-filters{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;padding:14px 16px;
  background:var(--bm-surface);border:1px solid var(--bm-border);border-radius:var(--bm-radius-lg);
  margin-bottom:20px;box-shadow:var(--bm-shadow-sm)}
.bm-filter-group{display:flex;flex-direction:column;gap:4px}
.bm-filter-group label{font-size:11px;font-weight:600;color:var(--bm-muted);text-transform:uppercase;letter-spacing:.05em}
.bm-filter-group select,.bm-filter-group input[type=text]{
  background:var(--bm-surface-2);border:1px solid var(--bm-border);border-radius:var(--bm-radius-sm);
  padding:7px 10px;font-size:13px;color:var(--bm-text);min-width:160px;outline:none;
  transition:border-color var(--bm-trans),box-shadow var(--bm-trans)}
.bm-filter-group select:focus,.bm-filter-group input[type=text]:focus{
  border-color:var(--bm-primary);box-shadow:0 0 0 3px var(--bm-primary-hi)}

/* ── View toggle ───────────────────────────────────────────── */
.bm-view-toggle{display:flex;gap:2px;background:var(--bm-surface-2);border:1px solid var(--bm-border);
  border-radius:var(--bm-radius-md);padding:3px}
.bm-view-btn{padding:7px 16px;border-radius:calc(var(--bm-radius-md) - 3px);font-size:13px;font-weight:500;
  color:var(--bm-muted);display:flex;align-items:center;gap:6px}
.bm-view-btn svg{width:15px;height:15px;flex-shrink:0}
.bm-view-btn.active{background:var(--bm-surface);color:var(--bm-text);box-shadow:var(--bm-shadow-sm)}
.bm-view-btn:hover:not(.active){color:var(--bm-text);background:oklch(from var(--bm-primary) l c h / .06)}

/* ── Active filter summary ─────────────────────────────────── */
.bm-active-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;min-height:28px}
.bm-filter-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;
  background:var(--bm-primary-hi);border:1px solid oklch(from var(--bm-primary) l c h / .25);
  border-radius:999px;font-size:12px;font-weight:500;color:var(--bm-primary)}
.bm-filter-pill button{width:14px;height:14px;display:flex;align-items:center;justify-content:center;
  border-radius:999px;color:var(--bm-primary);padding:0;font-size:14px;line-height:1}
.bm-filter-pill button:hover{background:oklch(from var(--bm-primary) l c h / .15)}
.bm-result-count{font-size:13px;color:var(--bm-muted);margin-left:auto}

/* ── Timeline view ─────────────────────────────────────────── */
#bm-timeline-view{display:block}
#bm-kanban-view{display:none}
.bm-timeline{display:grid;gap:10px}
.bm-event{display:grid;grid-template-columns:160px 1fr;gap:10px;align-items:stretch}
.bm-time-col{background:var(--bm-surface-2);border:1px solid var(--bm-border);border-radius:var(--bm-radius-md);
  padding:12px 14px;font-weight:600;font-size:13px;display:flex;flex-direction:column;justify-content:center}
.bm-time-col .bm-bucket-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--bm-muted);margin-bottom:3px}
.bm-time-col .bm-time-value{font-size:15px}
.bm-card{background:var(--bm-surface);border:1px solid var(--bm-border);border-radius:var(--bm-radius-lg);
  padding:14px 16px;box-shadow:var(--bm-shadow-sm)}
.bm-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
.bm-name{font-weight:700;font-size:15px}
.bm-summary{color:var(--bm-muted);font-size:13px;margin-top:2px}
.bm-meta{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.bm-tag{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;
  background:var(--bm-surface-2);border:1px solid var(--bm-border);font-size:12px;font-family:monospace}
.bm-tag.plain{font-family:inherit}
.bm-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:500}
.bm-badge.active{background:color-mix(in oklch,var(--bm-low) 12%,transparent);color:var(--bm-low)}
.bm-badge.inactive{background:color-mix(in oklch,var(--bm-faint) 20%,transparent);color:var(--bm-muted)}

/* ── Kanban view ───────────────────────────────────────────── */
.bm-kanban-wrap{overflow-x:auto;padding-bottom:8px}
.bm-kanban{display:grid;grid-template-columns:repeat(4,minmax(240px,1fr));gap:12px;min-width:960px}
.bm-col{background:var(--bm-surface-2);border:1px solid var(--bm-border);border-radius:var(--bm-radius-xl);padding:12px;min-height:200px}
.bm-col-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.bm-col-title{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.bm-col-count{background:var(--bm-surface-3);border:1px solid var(--bm-border);border-radius:999px;
  font-size:11px;padding:2px 8px;color:var(--bm-muted);font-variant-numeric:tabular-nums}
.bm-stack{display:grid;gap:8px}
.bm-mini{background:var(--bm-surface);border:1px solid var(--bm-border);border-radius:var(--bm-radius-md);
  padding:11px 13px;box-shadow:var(--bm-shadow-sm);position:relative;overflow:hidden}
.bm-mini::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:var(--bm-radius-sm) 0 0 var(--bm-radius-sm)}
.bm-mini.is-critical::before{background:var(--bm-critical)}
.bm-mini.is-high::before{background:var(--bm-high)}
.bm-mini.is-medium::before{background:var(--bm-medium)}
.bm-mini.is-low::before{background:var(--bm-low)}
.bm-mini-title{font-weight:700;font-size:14px;padding-left:8px;margin-bottom:5px}
.bm-mini-summary{margin:0;color:var(--bm-muted);font-size:12px;padding-left:8px}
.bm-mini-foot{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;padding-left:8px}
.bm-empty{color:var(--bm-muted);font-size:13px;padding:8px 2px}

/* ── Empty state ───────────────────────────────────────────── */
.bm-no-results{display:none;flex-direction:column;align-items:center;text-align:center;
  padding:60px 20px;color:var(--bm-muted)}
.bm-no-results svg{width:48px;height:48px;color:var(--bm-faint);margin-bottom:16px}
.bm-no-results h3{color:var(--bm-text);margin-bottom:6px;font-size:16px}

/* ── Hour range display in time column ─────────────────────── */
.bm-hour-dots{display:flex;flex-wrap:wrap;gap:3px;margin-top:6px}
.bm-dot{width:8px;height:8px;border-radius:50%;background:var(--bm-primary);opacity:.7}

/* ── Responsive ────────────────────────────────────────────── */
@media(max-width:1100px){.bm-kanban{grid-template-columns:repeat(3,minmax(220px,1fr))}}
@media(max-width:900px){.bm-kanban{grid-template-columns:repeat(2,minmax(200px,1fr))}.bm-event{grid-template-columns:1fr}}
@media(max-width:640px){.bm-kanban{grid-template-columns:1fr}.bm-filters{flex-direction:column}.bm-filter-group select,.bm-filter-group input[type=text]{min-width:unset;width:100%}}
</style>
</head>
<body>
<div class="bm-wrap">

  <!-- Header -->
  <div class="bm-head">
    <div>
      <h1 class="bm-title"><?= __('Backup Schedule Board','backupmanager') ?></h1>
      <div class="bm-sub"><?= __('Visualizing existing cron schedules — read-only view.','backupmanager') ?></div>
    </div>
    <div class="bm-stats">
      <div class="bm-stat"><span><?= __('Total','backupmanager') ?></span><b><?= $totalCount ?></b></div>
      <div class="bm-stat"><span><?= __('Scheduled','backupmanager') ?></span><b><?= $withCron ?></b></div>
      <div class="bm-stat"><span><?= __('Unscheduled','backupmanager') ?></span><b><?= $unscheduled ?></b></div>
    </div>
  </div>

  <!-- Filter bar -->
  <div class="bm-filters" role="search" aria-label="<?= __('Filter routines','backupmanager') ?>">

    <!-- View toggle -->
    <div class="bm-filter-group">
      <label><?= __('View','backupmanager') ?></label>
      <div class="bm-view-toggle" role="group" aria-label="<?= __('View mode','backupmanager') ?>">
        <button class="bm-view-btn active" id="btn-timeline" aria-pressed="true" onclick="setView('timeline')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          Timeline
        </button>
        <button class="bm-view-btn" id="btn-kanban" aria-pressed="false" onclick="setView('kanban')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="5" height="15" rx="1"/></svg>
          Kanban
        </button>
      </div>
    </div>

    <!-- Frequency bucket -->
    <div class="bm-filter-group">
      <label for="f-bucket"><?= __('Frequency','backupmanager') ?></label>
      <select id="f-bucket" onchange="applyFilters()">
        <option value=""><?= __('All frequencies','backupmanager') ?></option>
        <option value="frequent"><?= __('Frequent','backupmanager') ?></option>
        <option value="hourly"><?= __('Hourly','backupmanager') ?></option>
        <option value="daily"><?= __('Daily','backupmanager') ?></option>
        <option value="weekly"><?= __('Weekly','backupmanager') ?></option>
        <option value="monthly"><?= __('Monthly','backupmanager') ?></option>
        <option value="custom"><?= __('Custom','backupmanager') ?></option>
        <option value="unscheduled"><?= __('Unscheduled','backupmanager') ?></option>
      </select>
    </div>

    <!-- Day of week -->
    <div class="bm-filter-group">
      <label for="f-dow"><?= __('Day of week','backupmanager') ?></label>
      <select id="f-dow" onchange="applyFilters()">
        <option value=""><?= __('Any day','backupmanager') ?></option>
        <?php foreach ($dowLabels as $num => $label): ?>
        <option value="<?= $num ?>"><?= $label ?></option>
        <?php endforeach ?>
      </select>
    </div>

    <!-- Hour range -->
    <div class="bm-filter-group">
      <label for="f-hour"><?= __('Hour (0–23)','backupmanager') ?></label>
      <select id="f-hour" onchange="applyFilters()">
        <option value=""><?= __('Any hour','backupmanager') ?></option>
        <?php for ($h = 0; $h < 24; $h++): ?>
        <option value="<?= $h ?>"><?= sprintf('%02d:xx', $h) ?></option>
        <?php endfor ?>
      </select>
    </div>

    <!-- Status -->
    <div class="bm-filter-group">
      <label for="f-status"><?= __('Status','backupmanager') ?></label>
      <select id="f-status" onchange="applyFilters()">
        <option value=""><?= __('All','backupmanager') ?></option>
        <option value="1"><?= __('Active','backupmanager') ?></option>
        <option value="0"><?= __('Inactive','backupmanager') ?></option>
      </select>
    </div>

    <!-- Search -->
    <div class="bm-filter-group" style="flex:1;min-width:180px">
      <label for="f-search"><?= __('Search','backupmanager') ?></label>
      <input type="text" id="f-search" placeholder="<?= __('Routine name…','backupmanager') ?>" oninput="applyFilters()" autocomplete="off">
    </div>

  </div><!-- /filters -->

  <!-- Active filter pills + result count -->
  <div class="bm-active-filters" id="bm-pills" aria-live="polite"></div>

  <!-- ── TIMELINE VIEW ─────────────────────────────────────── -->
  <div id="bm-timeline-view" role="region" aria-label="<?= __('Timeline view','backupmanager') ?>">
    <div class="bm-timeline" id="bm-timeline"></div>
    <div class="bm-no-results" id="bm-tl-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <h3><?= __('No routines match the filters','backupmanager') ?></h3>
      <p><?= __('Try removing or changing the active filters.','backupmanager') ?></p>
    </div>
  </div>

  <!-- ── KANBAN VIEW ───────────────────────────────────────── -->
  <div id="bm-kanban-view" role="region" aria-label="<?= __('Kanban view','backupmanager') ?>">
    <div class="bm-kanban-wrap">
      <div class="bm-kanban" id="bm-kanban"></div>
    </div>
    <div class="bm-no-results" id="bm-kb-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <h3><?= __('No routines match the filters','backupmanager') ?></h3>
      <p><?= __('Try removing or changing the active filters.','backupmanager') ?></p>
    </div>
  </div>

</div><!-- /bm-wrap -->

<script>
(function(){
  const ROWS    = <?= $jsonRows ?>;
  const I18N    = <?= $i18n ?>;
  const BUCKETS = ['frequent','hourly','daily','weekly','monthly','custom','unscheduled'];

  let currentView = 'timeline';
  let filtered    = [...ROWS];

  /* ── helpers ──────────────────────────────────────────────── */
  function esc(str){ const d=document.createElement('div'); d.textContent=str; return d.innerHTML; }

  function priorityHtml(label){
    return label;
  }

  function badgeHtml(active){
    const cls = active ? 'active' : 'inactive';
    const lbl = active ? I18N.active ?? 'Active' : I18N.inactive ?? 'Inactive';
    return `<span class="bm-badge ${cls}">${esc(lbl)}</span>`;
  }

  /* ── filter logic ─────────────────────────────────────────── */
  function matchesFilters(row, f){
    if (f.bucket && row.bucket !== f.bucket) return false;
    if (f.status !== '' && String(row.active ? 1 : 0) !== f.status) return false;
    if (f.search && !row.name.toLowerCase().includes(f.search.toLowerCase())) return false;

    // DOW: if row has explicit dow values, check inclusion; if row.dow is [] (wildcard/*), always pass
    if (f.dow !== '' && row.dow.length > 0 && !row.dow.includes(parseInt(f.dow))) return false;
    // For unscheduled or wildcard dow rows: DOW filter still passes (wildcard = "runs any day")

    // Hour: same logic — explicit list vs wildcard
    if (f.hour !== '' && row.hours.length > 0 && !row.hours.includes(parseInt(f.hour))) return false;

    return true;
  }

  function getFilters(){
    return {
      bucket: document.getElementById('f-bucket').value,
      dow:    document.getElementById('f-dow').value,
      hour:   document.getElementById('f-hour').value,
      status: document.getElementById('f-status').value,
      search: document.getElementById('f-search').value.trim(),
    };
  }

  /* ── filter pills ─────────────────────────────────────────── */
  function renderPills(f, count){
    const container = document.getElementById('bm-pills');
    container.innerHTML = '';

    const addPill = (label, clearFn) => {
      const pill = document.createElement('span');
      pill.className = 'bm-filter-pill';
      const btn = document.createElement('button');
      btn.innerHTML = '×';
      btn.setAttribute('aria-label', 'Remove filter');
      btn.onclick = () => { clearFn(); applyFilters(); };
      pill.textContent = label;
      pill.appendChild(btn);
      container.appendChild(pill);
    };

    if (f.bucket)  addPill(I18N.buckets[f.bucket] ?? f.bucket,  () => document.getElementById('f-bucket').value = '');
    if (f.dow !== '') {
      const dayNames = I18N.days;
      addPill(dayNames[parseInt(f.dow)] ?? f.dow, () => document.getElementById('f-dow').value = '');
    }
    if (f.hour !== '') addPill(String(f.hour).padStart(2,'0')+':xx', () => document.getElementById('f-hour').value = '');
    if (f.status !== '') {
      const lbl = f.status === '1' ? (I18N.active ?? 'Active') : (I18N.inactive ?? 'Inactive');
      addPill(lbl, () => document.getElementById('f-status').value = '');
    }
    if (f.search)  addPill('"' + f.search + '"', () => { document.getElementById('f-search').value = ''; });

    const countEl = document.createElement('span');
    countEl.className = 'bm-result-count';
    countEl.textContent = count + ' / ' + ROWS.length;
    container.appendChild(countEl);
  }

  /* ── timeline render ──────────────────────────────────────── */
  function renderTimeline(rows){
    const container = document.getElementById('bm-timeline');
    const empty     = document.getElementById('bm-tl-empty');
    container.innerHTML = '';

    if (!rows.length){
      empty.style.display = 'flex';
      return;
    }
    empty.style.display = 'none';

    rows.forEach(row => {
      const article = document.createElement('article');
      article.className = 'bm-event';
      article.dataset.id = row.id;

      article.innerHTML = `
        <div class="bm-time-col">
          <div class="bm-bucket-label">${esc(I18N.buckets[row.bucket] ?? row.bucket)}</div>
          <div class="bm-time-value">${row.cron ? esc(row.cron) : '—'}</div>
        </div>
        <div class="bm-card">
          <div class="bm-card-head">
            <div>
              <div class="bm-name">${esc(row.name)}</div>
              <div class="bm-summary">${esc(row.summary)}</div>
            </div>
            ${priorityHtml(row.priorityLabel)}
          </div>
          <div class="bm-meta">
            ${row.cron ? `<span class="bm-tag">${esc(I18N.cronLabel)}: ${esc(row.cron)}</span>` : ''}
            ${badgeHtml(row.active)}
            ${row.sourceLabel}
            ${row.desc ? `<span class="bm-tag plain">${esc(row.desc)}</span>` : ''}
          </div>
        </div>`;

      container.appendChild(article);
    });
  }

  /* ── kanban render ────────────────────────────────────────── */
  function renderKanban(rows){
    const container = document.getElementById('bm-kanban');
    const empty     = document.getElementById('bm-kb-empty');
    container.innerHTML = '';

    const grouped = {};
    BUCKETS.forEach(b => grouped[b] = []);
    rows.forEach(r => { if (grouped[r.bucket]) grouped[r.bucket].push(r); else grouped['custom'].push(r); });

    BUCKETS.forEach(bucket => {
      const col = document.createElement('div');
      col.className = 'bm-col';

      const colRows = grouped[bucket];
      col.innerHTML = `
        <div class="bm-col-head">
          <span class="bm-col-title">${esc(I18N.buckets[bucket] ?? bucket)}</span>
          <span class="bm-col-count">${colRows.length}</span>
        </div>
        <div class="bm-stack" id="kb-stack-${bucket}"></div>`;

      container.appendChild(col);

      const stack = col.querySelector('.bm-stack');

      if (!colRows.length){
        stack.innerHTML = `<div class="bm-empty">${esc(I18N.noInBucket)}</div>`;
      } else {
        colRows.forEach(row => {
          const card = document.createElement('div');
          card.className = `bm-mini ${row.priorityClass}`;
          card.dataset.id = row.id;
          card.innerHTML = `
            <div class="bm-mini-title">${esc(row.name)}</div>
            <p class="bm-mini-summary">${esc(row.summary)}</p>
            <div class="bm-mini-foot">
              ${badgeHtml(row.active)}
              ${row.priorityLabel}
            </div>`;
          stack.appendChild(card);
        });
      }
    });

    const visibleTotal = rows.length;
    if (!visibleTotal) {
      empty.style.display = 'flex';
      container.style.display = 'none';
    } else {
      empty.style.display = 'none';
      container.style.display = '';
    }
  }

  /* ── main apply ───────────────────────────────────────────── */
  window.applyFilters = function(){
    const f = getFilters();
    filtered = ROWS.filter(r => matchesFilters(r, f));
    renderPills(f, filtered.length);
    if (currentView === 'timeline') renderTimeline(filtered);
    else renderKanban(filtered);
  };

  /* ── view switch ──────────────────────────────────────────── */
  window.setView = function(view){
    currentView = view;
    document.getElementById('bm-timeline-view').style.display = view === 'timeline' ? 'block' : 'none';
    document.getElementById('bm-kanban-view').style.display   = view === 'kanban'   ? 'block' : 'none';
    document.getElementById('btn-timeline').classList.toggle('active', view === 'timeline');
    document.getElementById('btn-kanban').classList.toggle('active', view === 'kanban');
    document.getElementById('btn-timeline').setAttribute('aria-pressed', view === 'timeline');
    document.getElementById('btn-kanban').setAttribute('aria-pressed', view === 'kanban');
    applyFilters();
  };

  /* ── init ─────────────────────────────────────────────────── */
  applyFilters();
})();
</script>
</body>
</html>
<?php
Html::footer();