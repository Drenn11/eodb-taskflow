<?php
/**
 * EODB-TaskFlow - Top navigation bar
 *
 * Page context (menu group / page title), sidebar toggle, quick search,
 * Light/Dark Mode toggle, notification dropdown with the unread count, and
 * the user menu (profile, logout). Included by header.php.
 *
 * Notifications: clicking one marks it as read and opens the related task
 * (notifications/open.php). The unread badge refreshes every 60 seconds
 * (assets/js/app.js -> notifications/fetch.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/notification_functions.php';

$navUser = current_user();
$unreadCount = unread_notification_count((int) $navUser['id']);

$latestStmt = db()->prepare(
    'SELECT id, task_id, title, message, type, is_read, created_at
       FROM notifications
      WHERE user_id = ?
      ORDER BY is_read, created_at DESC, id DESC
      LIMIT 6'
);
$latestStmt->execute([$navUser['id']]);
$latestNotifications = $latestStmt->fetchAll();

$profilePage = url('profile/index.php');
$navPhoto    = profile_photo_url($navUser['profile_photo']);
$navReturn   = ltrim(substr((string) ($_SERVER['REQUEST_URI'] ?? ''), strlen(BASE_URL)), '/');

// Page context shown in the bar: the menu group of the highlighted sidebar item
$navContext = '';
foreach (sidebar_menu($navUser['role']) as $navItem) {
    if ($navItem['key'] === $activeMenu) {
        $navContext = $navItem['group'];
        break;
    }
}
?>
<header class="app-topbar">
    <button type="button" class="btn btn-link topbar-toggle d-lg-none" id="sidebarToggle"
            aria-label="Open navigation menu" aria-controls="appSidebar" aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <button type="button" class="btn btn-link topbar-toggle d-none d-lg-inline-flex" id="sidebarCollapse"
            aria-label="Collapse navigation menu" aria-controls="appSidebar" aria-pressed="false" title="Collapse or expand the menu">
        <i class="fa-solid fa-bars-staggered" aria-hidden="true"></i>
    </button>

    <div class="topbar-title">
        <?php if ($navContext !== '' && $navContext !== $pageTitle): ?>
            <span class="topbar-context"><?= e($navContext) ?></span>
            <i class="fa-solid fa-chevron-right topbar-separator" aria-hidden="true"></i>
        <?php endif; ?>
        <span class="topbar-page"><?= e($pageTitle) ?></span>
    </div>

    <div class="topbar-actions">
        <!-- Quick search (Phase 9) -->
        <?php $searchHint = $navUser['role'] === 'employee' ? 'Search my tasks' : 'Search tasks or employees'; ?>
        <form method="get" action="<?= e(url('search/index.php')) ?>" class="topbar-search d-none d-md-block me-1" role="search">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                <input type="search" name="q" class="form-control" placeholder="<?= e($searchHint) ?>"
                       maxlength="100" aria-label="<?= e($searchHint) ?>">
            </div>
        </form>
        <a href="<?= e(url('search/index.php')) ?>" class="btn btn-link topbar-icon d-md-none" aria-label="<?= e($searchHint) ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </a>

        <!-- Light / Dark Mode (assets/js/app.js; label updated to the current theme) -->
        <button type="button" class="btn btn-link topbar-icon theme-toggle" id="themeToggle"
                aria-label="Switch to dark mode" title="Switch to dark mode">
            <i class="fa-solid fa-moon theme-icon-dark" aria-hidden="true"></i>
            <i class="fa-solid fa-sun theme-icon-light" aria-hidden="true"></i>
        </button>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn btn-link topbar-icon" type="button" data-bs-toggle="dropdown" id="notificationButton"
                    aria-expanded="false" aria-label="Notifications (<?= $unreadCount ?> unread)"
                    data-fetch-url="<?= e(url('notifications/fetch.php')) ?>">
                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                <span class="topbar-badge" id="notificationBadge" <?= $unreadCount > 0 ? '' : 'hidden' ?>><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-menu shadow-sm p-0">
                <div class="notification-menu-header">
                    <span class="fw-semibold">Notifications <span class="text-muted small fw-normal">(<?= $unreadCount ?> unread)</span></span>
                    <?php if ($unreadCount > 0): ?>
                        <form method="post" action="<?= e(url('notifications/mark_read.php')) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="all" value="1">
                            <input type="hidden" name="return" value="<?= e($navReturn) ?>">
                            <button type="submit" class="btn btn-link btn-sm p-0 small">Mark all read</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!$latestNotifications): ?>
                    <div class="px-3 py-4 text-center text-muted small">No notifications yet.</div>
                <?php endif; ?>
                <?php foreach ($latestNotifications as $note): ?>
                    <form method="post" action="<?= e(url('notifications/open.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                        <button type="submit" class="notification-item notification-type-<?= e($note['type']) ?><?= $note['is_read'] ? '' : ' unread' ?>">
                            <span class="notification-item-icon" aria-hidden="true"><i class="fa-solid <?= e(notification_icon($note['type'])) ?>"></i></span>
                            <span class="flex-grow-1 min-w-0 d-block text-start">
                                <span class="notification-item-title d-block"><?= e($note['title']) ?></span>
                                <span class="notification-item-message d-block"><?= e($note['message']) ?></span>
                                <span class="notification-item-time d-block"><?= e(format_datetime($note['created_at'])) ?></span>
                            </span>
                        </button>
                    </form>
                <?php endforeach; ?>
                <a href="<?= e(url('notifications/index.php')) ?>" class="notification-menu-footer">View all notifications</a>
            </div>
        </div>

        <!-- User menu -->
        <div class="dropdown">
            <button class="btn btn-link topbar-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if ($navPhoto): ?>
                    <img src="<?= e($navPhoto) ?>" alt="" class="avatar avatar-sm">
                <?php else: ?>
                    <span class="avatar avatar-sm"><?= e(initials($navUser['full_name'])) ?></span>
                <?php endif; ?>
                <span class="topbar-user-text d-none d-md-block">
                    <span class="topbar-user-name"><?= e($navUser['full_name']) ?></span>
                    <span class="topbar-user-role"><?= e(role_label($navUser['role'])) ?></span>
                </span>
                <i class="fa-solid fa-chevron-down small text-muted d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="px-3 py-2 d-md-none">
                    <div class="fw-semibold small"><?= e($navUser['full_name']) ?></div>
                    <div class="text-muted small"><?= e(role_label($navUser['role'])) ?></div>
                </li>
                <li><span class="dropdown-item-text small text-muted"><?= e($navUser['position'] ?? '') ?></span></li>
                <li><a class="dropdown-item" href="<?= e($profilePage) ?>"><i class="fa-solid fa-id-badge fa-fw me-2"></i>My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item">
                            <i class="fa-solid fa-right-from-bracket fa-fw me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
