<?php
/**
 * EODB-TaskFlow - CSV export of a report (Administrator and Supervisor)
 *
 * Downloads the same records as the on-screen report, with a title block
 * (report name, office, date generated, filters). The file is UTF-8 with a
 * byte-order mark so Microsoft Excel shows names with ñ and other characters
 * correctly.
 *
 * Security: cells that start with = + - @ (or a tab / carriage return) are
 * prefixed with an apostrophe so a spreadsheet never runs them as formulas
 * (CSV / formula injection).
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
log_report($type, 'CSV', count($data));

/** Neutralises spreadsheet formulas in a cell value. */
function csv_safe(string $value): string
{
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

$fileName = 'eodb_' . $type . '_report_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('X-Content-Type-Options: nosniff');
send_no_cache_headers();

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // UTF-8 byte-order mark for Excel

$write = static function (array $cells) use ($out): void {
    fputcsv($out, array_map(static fn ($c) => csv_safe((string) $c), $cells));
};

$write([office_name()]);
$write([app_name() . ' - Employee Task Monitoring Report']);
$write([REPORT_TYPES[$type][0]]);
$write(['Report date', date('Y-m-d H:i')]);
foreach (report_filter_summary($filters) as $label => $value) {
    $write([$label, $value]);
}
$write(['Prepared by', current_user()['full_name']]);
$write([]);

$write($table['headers']);
foreach ($table['rows'] as $row) {
    $write($row);
}
if ($table['totals'] && $table['rows']) {
    $write($table['totals']);
}

fclose($out);
exit;
