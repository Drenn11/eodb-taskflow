# Phase 8 – Reports, Productivity Monitoring, and Printable Reports

## 1. What Phase 8 adds

| Feature | Files |
|---|---|
| Reports page with overview charts | `reports/index.php` |
| Task Report | `reports/task_report.php` |
| Employee Productivity Report | `reports/productivity_report.php` |
| Accomplishment Report | `reports/accomplishment_report.php` |
| Printable report / Save as PDF | `reports/print_report.php`, `assets/css/print.css`, `assets/js/print.js` |
| CSV export | `reports/export_csv.php` |
| Shared report logic (filters, queries, table layout) | `includes/report_functions.php`, `includes/report_filter_bar.php` |
| Charts (Chart.js 4.4.1 from CDN) | `assets/js/reports.js`, `CDN_CHARTJS` in `config/app.php`, `$useCharts` in `includes/footer.php` |

**Change from the Phase 1 plan:** instead of separate `admin/reports.php` and `supervisor/reports.php`, both roles use one shared `reports/` folder. The Administrator and the Supervisor both have *View / Generate reports*, so one implementation avoids two copies of the same code. The sidebar "Reports" link of both roles now opens `reports/index.php`. Employees cannot open any report page (403).

## 2. The three reports

| Report | Columns | Filters |
|---|---|---|
| **Task Report** | Task Code, Task, Employee, Priority, Status, Progress, Deadline, Date Created, Date Completed | search (code, task, employee), employee, department, status (incl. "all unfinished"), priority, date range applied to **date created / deadline / date completed** |
| **Employee Productivity Report** | Employee, Total Tasks, Completed, Pending, In Progress, Overdue, Completion Rate, On-time Rate, with a TOTAL row | search (name, ID, position), employee, department, active / inactive / all, period (tasks created), sort order |
| **Accomplishment Report** | Task Code, Task, Employee, Priority, Deadline, Date Completed, Timeliness (e.g. "1 day early", "2 days late"), Remarks | search, employee, department, priority, **completion date range** (default: first day of this month to today) |

Status in all reports follows **Algorithm 3**. Completion Rate follows **Algorithm 4** (Completed ÷ Total × 100) and is labelled *for monitoring and reporting only, not a disciplinary score*.

Filters are validated on the server: unknown values are ignored, invalid dates are dropped, and a reversed date range (From after To) is swapped automatically. A report shows at most 2,000 rows; a message appears if the limit is reached.

## 3. Charts (real database data)

| Chart | Page |
|---|---|
| **Task Status Chart** (Pending, In Progress, Completed, Overdue) | Reports page, Task Report |
| **Productivity Chart**: tasks per employee by status (completed per employee) | Reports page, Productivity Report |
| Completion rate per employee | Productivity Report |
| Tasks by priority | Task Report |
| Completed tasks per employee | Accomplishment Report |
| Completed tasks per month (last 6 months) | Reports page |

The PHP page puts the chart data in a `<script type="application/json">` block (encoded with `JSON_HEX_TAG` etc., so names can never break out of it); `reports.js` draws the charts. If the chart library cannot load (no internet), a note is shown and the tables still show every figure.

## 4. Printable report

**Print / Save as PDF** opens `reports/print_report.php` in a new tab with the same filters:

```
                RURAL HEALTH UNIT – MULANAY
                       EODB-TaskFlow
              EMPLOYEE TASK MONITORING REPORT
                     (report name)

Report date: …            Date range: …
Employee: …               Department: …
(status / priority / search, if used)

No. | report columns …                         (headers repeat on every page)

Summary: counts, completion rate, on-time figures

Prepared by:              Reviewed by:              Approved by:
______________________    ______________________    ______________________
NAME OF LOGGED-IN USER    Signature over printed    Signature over printed
Position                  name                      name
Date: ______________      Date: ______________      Date: ______________
```

- A4 **landscape** for the Task and Accomplishment Reports, **portrait** for the Productivity Report.
- Black-on-white layout; the toolbar is hidden when printing.
- **PDF:** in the print dialog choose *Save as PDF* (Chrome/Edge) or *Microsoft Print to PDF*. No extra library is needed.

## 5. CSV export

**Export CSV** downloads `eodb_<type>_report_<date>_<time>.csv` containing a title block (office, system, report name, report date, filters, prepared by), the column headers, all rows, and the TOTAL row (productivity).

- UTF-8 with a byte-order mark, so Excel displays characters such as **ñ** correctly.
- **CSV / formula injection protection:** any cell starting with `=`, `+`, `-`, `@`, tab, or carriage return is prefixed with an apostrophe, so Excel never runs it as a formula.

Every print view and CSV export is recorded in the activity log as **Generate Report** (report name, format, number of records).

## 6. Phase 8 test checklist

| # | Test | Expected result |
|---|---|---|
| 1 | Supervisor or admin → Reports | 3 report cards; Task Status, Productivity, and monthly charts |
| 2 | Task Report, no filters | All tasks, with status/priority charts and summary |
| 3 | Task Report, From = first day of this month | Only tasks created this month |
| 4 | Task Report, "Date range applies to" = Date completed | Only tasks completed in the range |
| 5 | Task Report, Status = Overdue, Priority = Urgent | Only urgent overdue tasks |
| 6 | Productivity Report | One row per active employee + TOTAL row; completion rate bars and chart |
| 7 | Productivity Report → "Active and inactive" | Inactive employees included |
| 8 | Accomplishment Report (default) | Tasks completed this month, with timeliness and remarks |
| 9 | Accomplishment Report, From = 1st of last month | More completed tasks |
| 10 | Print / Save as PDF | Header, report info, numbered table, summary, three signature lines; print preview is clean |
| 11 | Save as PDF in the print dialog | PDF file created |
| 12 | Export CSV, open in Excel | Title block, headers, rows; names display correctly |
| 13 | Create a task titled `=1+1`, export the Task Report CSV | Cell shows `'=1+1` as text, not 2 |
| 14 | Admin → Activity Logs → Action = Generate Report | Print and export entries |
| 15 | Employee opens `reports/index.php` or `reports/export_csv.php?type=tasks` | 403 Access Denied |
