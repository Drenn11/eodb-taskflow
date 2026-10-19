<?php
/**
 * EODB-TaskFlow - Sidebar navigation
 *
 * Shows only the menu items allowed for the logged-in user's role
 * (see sidebar_menu() in includes/functions.php), grouped under headings.
 * On desktop the sidebar can be collapsed to icons (button in the top bar);
 * on tablets and phones it slides in from the left. The signed-in user and
 * the logout button sit at the bottom. Included by header.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/notification_functions.php';

$sidebarUser   = current_user();
$sidebarItems  = sidebar_menu($sidebarUser['role']);
$sidebarUnread = unread_notification_count((int) $sidebarUser['id']);
$currentPath   = ltrim(substr((string) ($_SERVER['SCRIPT_NAME'] ?? ''), strlen(BASE_URL)), '/');
$currentGroup  = null;
$sidebarPhoto  = profile_photo_url($sidebarUser['profile_photo']);
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
        <a href="<?= e(url(dashboard_path($sidebarUser['role']))) ?>" class="sidebar-brand-link" title="Go to my dashboard">
            <img src="<?= e(asset('images/mho-logo.png')) ?>" alt="Municipal Health Office, Mulanay, Quezon"
                 class="sidebar-logo" width="38" height="38">
            <span class="sidebar-brand-text">
                <span class="sidebar-brand-name d-block"><?= e(app_name()) ?></span>
                <span class="sidebar-brand-office d-block">RHU Mulanay</span>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($sidebarItems as $item):
            $isActive = $activeMenu === $item['key'] || ($activeMenu === '' && $currentPath === $item['path']);
            if ($item['group'] !== $currentGroup):
                if ($currentGroup !== null): ?>
        </ul>
                <?php endif;
                $currentGroup = $item['group']; ?>
        <p class="sidebar-section"><?= e($currentGroup) ?></p>
        <ul class="list-unstyled mb-0">
            <?php endif; ?>
            <li>
                <a href="<?= e(url($item['path'])) ?>"
                   class="sidebar-link<?= $isActive ? ' active' : '' ?>"
                   title="<?= e($item['label']) ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <span class="sidebar-label"><?= e($item['label']) ?></span>
                    <?php if ($item['key'] === 'notifications' && $sidebarUnread > 0): ?>
                        <span class="sidebar-badge" aria-label="<?= $sidebarUnread ?> unread"><?= $sidebarUnread > 99 ? '99+' : $sidebarUnread ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
        <?php if ($currentGroup !== null): ?>
        </ul>
        <?php endif; ?>
    </nav>

    <!-- Signed-in user and logout (POST with CSRF token, same as before) -->
    <div class="sidebar-user">
        <?php if ($sidebarPhoto): ?>
            <img src="<?= e($sidebarPhoto) ?>" alt="" class="avatar avatar-sm">
        <?php else: ?>
            <span class="avatar avatar-sm" aria-hidden="true"><?= e(initials($sidebarUser['full_name'])) ?></span>
        <?php endif; ?>
        <span class="sidebar-user-text">
            <span class="sidebar-user-name"><?= e($sidebarUser['full_name']) ?></span>
            <span class="sidebar-user-role"><?= e(role_label($sidebarUser['role'])) ?></span>
        </span>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="sidebar-user-action" title="Logout" aria-label="Logout">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
