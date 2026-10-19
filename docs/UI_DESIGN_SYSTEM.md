# UI Design System – EODB-TaskFlow

The interface follows a professional government-office dashboard style: light-gray canvas, white panels, charcoal navigation, one restrained blue accent, and the same status colours everywhere. All styles live in `assets/css/style.css` (application) and `assets/css/print.css` (printed reports). Colours, spacing, type sizes, and shadows are defined once as CSS variables (design tokens) at the top of `style.css`.

## 1. Colour tokens

| Purpose | Token | Value |
|---|---|---|
| Page background | `--tf-canvas` | `#f3f4f6` |
| Panels, cards, top bar | `--tf-surface` | `#ffffff` |
| Borders | `--tf-border` / `--tf-border-strong` | `#e3e6ea` / `#d1d6dc` |
| Text / muted text | `--tf-text` / `--tf-text-muted` | `#1f2937` / `#6b7280` |
| Sidebar | `--tf-nav-bg` | `#16191d` |
| Accent (main buttons, links, focus, active menu) | `--tf-accent` | `#1d4ed8` |

### Task status (used for badges, progress bars, stat cards, and charts)

| Status | Colour | Soft background |
|---|---|---|
| Pending | `#6b7280` gray | `#f1f2f4` |
| In Progress | `#2563eb` blue | `#e8effd` |
| Completed | `#15803d` green | `#e7f4ec` |
| Overdue | `#b91c1c` red | `#fdecec` |

The same four values are used by the charts (`STATUS_COLORS` in `includes/report_functions.php`).

### Priority (outlined badge with a flag icon, so it is never confused with status)

Low `#6b7280` · Medium `#475569` · High `#b45309` · Urgent `#b91c1c`

## 2. Typography and spacing

* Font: Segoe UI / system fonts (no download needed; works offline). Task codes use a monospace font (`.task-code`).
* Sizes: base 15 px, page title 22 px, stat numbers 25.6 px, labels 11.5 px uppercase.
* Spacing scale: 4, 8, 12, 16, 20, 24 px (`--tf-space-1` … `--tf-space-6`).
* Corners: 8 px for cards, 6 px for controls, full round for pills. Shadows are very light (`--tf-shadow-sm`, `--tf-shadow-md`).

## 3. Components

| Component | Class / helper | Notes |
|---|---|---|
| Page header | `.page-header`, `.page-title`, `.page-subtitle` | Title, short description, main action on the right |
| Stat card | `.stat-card` + `.stat-pending` / `.stat-progress` / `.stat-completed` / `.stat-overdue` / `.stat-accent` / `.stat-dark` | Icon chip tinted with the status colour; clickable version `.stat-card-link` |
| Status badge | `task_status_badge()` → `.badge-status-*` | Soft pill with a coloured dot |
| Priority badge | `priority_badge()` → `.badge-priority-*` | Outlined, flag icon |
| Progress bar | `progress_bar()` → `.bar-*` | Colour follows the status |
| Main / secondary buttons | `.btn-primary` / `.btn-outline-secondary` | Blue only for the main action of a page or form |
| Filter panel | `.filter-bar` | Uppercase small labels, white panel |
| Table | `.table` inside `.card` + `.table-responsive` | On phones, `.table-mobile-cards` turns rows into labelled cards |
| Form sections / actions | `.form-section-title`, `.form-actions` | Actions stack full-width on phones |
| Detail list | `.detail-list` | Label / value grid on details pages |
| Timeline | `.timeline`, `.timeline-item` | Task history |
| Notification icon chip | `.notification-type-<type> .notification-item-icon` | Colour by notification type |
| Empty state | `.empty-state` | Icon + short message |
| Charts | `assets/js/reports.js` | Data from PHP in `<script type="application/json">`; status colours |

## 4. Layout

* **Sidebar** (`includes/sidebar.php`): grouped menu (Overview, Task Management / My Work, Administration, Account) built from `sidebar_menu()`; only pages allowed for the role are listed. Active page: light highlight with a blue marker. Unread notification count on the Notifications item.
* **Collapsible sidebar** (desktop): the button left of the system name in the top bar collapses the menu to icons; the choice is remembered in the browser (`assets/js/sidebar-state.js`, `app.js`). Tablets and phones use the slide-in menu (☰).
* **Top bar** (`includes/navbar.php`): system name, quick search, notifications, user menu.
* **Breakpoints**: phone < 576 px (two compact stat cards per row, full-width buttons), < 768 px (tables become cards, login panels stack), < 992 px (slide-in sidebar).

## 5. Rules for future changes

1. Use the tokens (`var(--tf-...)`) instead of new colour codes.
2. Use the status classes and helpers for anything that shows a task status, so colours stay consistent.
3. Use `btn-primary` only for the single main action of a page or form.
4. Avoid inline `style` attributes; the only remaining ones are dynamic progress-bar widths.
5. Asset URLs include the file's modification time (`asset()`), so browsers load updated CSS/JS immediately.
