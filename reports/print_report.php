<?php
/**
 * EODB-TaskFlow - Printable report (Administrator and Supervisor)
 *
 * A clean, standalone page for printing or "Save as PDF" (browser print
 * dialog). Uses exactly the same filters as the on-screen report.
 *
 *   RURAL HEALTH UNIT – MULANAY
 *   EODB-TaskFlow
 *   EMPLOYEE TASK MONITORING REPORT
 *   (report name, report date, date range, employee, department, ...)
 *   table + summary
 *   Prepared by / Reviewed by / Approved by signature lines
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_functions.php';
require_role('admin', 'supervisor');

$type = get_str('type', 20);
if (!isset(REPORT_TYPES[$type])) {
    $type = 'tasks';
}

$filters = read_report_filters($type);
$data = match ($type) {
    'tasks'          => fetch_task_report($filters),
    'productivity'   => fetch_productivity_report($filters),
    'accomplishment' => fetch_accomplishment_report($filters),
};
$table = report_table($type, $data);
$recordCount = count($data);
log_report($type, 'print view', $recordCount);

// Summary lines under the table
$summary = [];
if ($type === 'tasks') {
    $counts = count_by_status($data);
    foreach ($counts as $status => $count) {
        $summary[$status] = (string) $count;
    }
    $summary['Total tasks'] = (string) $recordCount;
    $summary['Completion rate'] = completion_rate($counts['Completed'], $recordCount) . '%';
} elseif ($type === 'accomplishment') {
    $onTime = count(array_filter($data, fn ($r) => (int) $r['days_after_deadline'] <= 0));
    $summary['Completed tasks'] = (string) $recordCount;
    $summary['Completed on or before the deadline'] = $onTime . ($recordCount > 0 ? ' (' . completion_rate($onTime, $recordCount) . '%)' : '');
    $summary['Completed after the deadline'] = (string) ($recordCount - $onTime);
    $summary['Employees'] = (string) count(array_unique(array_column($data, 'employee_name')));
} else {
    $totals = productivity_totals($data);
    $summary['Employees'] = (string) $recordCount;
    $summary['Total tasks'] = (string) $totals['total'];
    $summary['Completed'] = (string) $totals['completed'];
    $summary['Overall completion rate'] = $totals['completion_rate'] . '%';
}

$preparer = current_user();
$office = mb_strtoupper(str_replace(' - ', ' – ', office_name()));
$landscape = $type !== 'productivity';
$wrapColumns = ['Task', 'Remarks'];

send_no_cache_headers();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(REPORT_TYPES[$type][0]) ?> – <?= e(date('Y-m-d')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
    <style>@page { size: A4 <?= $landscape ? 'landscape' : 'portrait' ?>; margin: 14mm 12mm; }</style>
</head>
<body>

<div class="print-toolbar no-print">
    <button type="button" id="printButton" class="toolbar-button primary">Print / Save as PDF</button>
    <a href="<?= e(url(REPORT_TYPES[$type][1] . (($q = report_filter_query($filters)) ? '?' . http_build_query($q) : ''))) ?>" class="toolbar-button">Back to report</a>
    <span class="toolbar-hint">To save a PDF, choose "Save as PDF" as the printer.</span>
</div>

<main class="report-page">
    <header class="report-header">
        <img src="<?= e(asset('images/logo.svg')) ?>" alt="" class="report-logo">
        <div class="report-heading">
            <div class="report-office"><?= e($office) ?></div>
            <div class="report-system"><?= e(app_name()) ?></div>
            <div class="report-title">EMPLOYEE TASK MONITORING REPORT</div>
            <div class="report-subtitle"><?= e(REPORT_TYPES[$type][0]) ?></div>
        </div>
    </header>

    <table class="report-info">
        <tr>
            <th>Report date</th><td><?= e(date('F d, Y h:i A')) ?></td>
            <?php $info = report_filter_summary($filters); ?>
            <th>Date range</th><td><?= e($info['Date range']) ?></td>
        </tr>
        <tr>
            <th>Employee</th><td><?= e($info['Employee']) ?></td>
            <th>Department</th><td><?= e($info['Department']) ?></td>
        </tr>
        <?php
        unset($info['Date range'], $info['Employee'], $info['Department']);
        $extra = array_chunk($info, 2, true);
        foreach ($extra as $pair): ?>
            <tr>
                <?php foreach ($pair as $label => $value): ?>
                    <th><?= e($label) ?></th><td><?= e($value) ?></td>
                <?php endforeach; ?>
                <?php if (count($pair) === 1): ?><th></th><td></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="report-table-wrap">
        <thead>
        <tr>
            <th class="col-no">No.</th>
            <?php foreach ($table['headers'] as $header): ?>
                <th><?= e($header) ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php if (!$table['rows']): ?>
            <tr><td colspan="<?= count($table['headers']) + 1 ?>" class="empty">No records match the selected filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($table['rows'] as $index => $cells): ?>
            <tr>
                <td class="col-no"><?= $index + 1 ?></td>
                <?php foreach ($cells as $i => $cell): ?>
                    <td class="<?= in_array($table['headers'][$i], $wrapColumns, true) ? 'wrap' : 'nowrap' ?>"><?= e($cell) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($table['totals'] && $table['rows']): ?>
            <tfoot>
            <tr>
                <td></td>
                <?php foreach ($table['totals'] as $cell): ?>
                    <td class="nowrap"><?= e($cell) ?></td>
                <?php endforeach; ?>
            </tr>
            </tfoot>
        <?php endif; ?>
    </table>
    </div>

    <section class="report-summary">
        <h2>Summary</h2>
        <table>
            <?php foreach ($summary as $label => $value): ?>
                <tr><th><?= e($label) ?></th><td><?= e($value) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php if ($type !== 'tasks'): ?>
            <p class="note">
                <?= $type === 'productivity'
                    ? 'Completion Rate = Completed Tasks ÷ Total Tasks × 100. These figures are for monitoring and reporting only and are not an automatic disciplinary score.'
                    : 'Timeliness compares the date completed with the task deadline.' ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="signatures">
        <div class="signature">
            <div class="signature-label">Prepared by:</div>
            <div class="signature-line"><?= e(mb_strtoupper($preparer['full_name'])) ?></div>
            <div class="signature-caption"><?= e($preparer['position'] ?: role_label($preparer['role'])) ?></div>
            <div class="signature-date">Date: ____________________</div>
        </div>
        <div class="signature">
            <div class="signature-label">Reviewed by:</div>
            <div class="signature-line">&nbsp;</div>
            <div class="signature-caption">Signature over printed name</div>
            <div class="signature-date">Date: ____________________</div>
        </div>
        <div class="signature">
            <div class="signature-label">Approved by:</div>
            <div class="signature-line">&nbsp;</div>
            <div class="signature-caption">Signature over printed name</div>
            <div class="signature-date">Date: ____________________</div>
        </div>
    </section>

    <footer class="report-footer">
        Generated by <?= e(app_name()) ?> on <?= e(date('F d, Y h:i A')) ?> by <?= e($preparer['full_name']) ?>
        · <?= $recordCount ?> record<?= $recordCount === 1 ? '' : 's' ?>
    </footer>
</main>

<script src="<?= e(asset('js/print.js')) ?>"></script>
</body>
</html>
