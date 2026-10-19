<?php
/**
 * EODB-TaskFlow - Notifications (all roles)
 *
 * Lists the logged-in user's own notifications, newest first.
 * Filters: unread only, type. Actions: open the related task (marks it read),
 * mark one as read, mark all as read.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notification_functions.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

$show = get_str('show', 10) === 'unread' ? 'unread' : 'all';
$type = get_str('type', 30);
if (!isset(NOTIFICATION_TYPE_INFO[$type])) {
    $type = '';
}

$where  = ['user_id = ?'];
$params = [$userId];
if ($show === 'unread') {
    $where[] = 'is_read = 0';
}
if ($type !== '') {
    $where[]  = 'type = ?';
    $params[] = $type;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM notifications {$whereSql}");
$countStmt->execute($params);
$pagination = paginate((int) $countStmt->fetchColumn(), records_per_page(), get_int('page'));

$listStmt = db()->prepare(
    "SELECT id, task_id, title, message, type, is_read, created_at
       FROM notifications
       {$whereSql}
      ORDER BY created_at DESC, id DESC
      LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
$listStmt->execute($params);
$notifications = $listStmt->fetchAll();

$unread = unread_notification_count($userId);
$filterQuery = array_filter(['show' => $show === 'unread' ? 'unread' : '', 'type' => $type], fn ($v) => $v !== '');
$returnPath = 'notifications/index.php' . ($filterQuery || $pagination['page'] > 1
    ? '?' . http_build_query($filterQuery + ['page' => $pagination['page']]) : '');

$pageTitle  = 'Notifications';
$activeMenu = 'notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle">You have <strong><?= $unread ?></strong> unread notification<?= $unread === 1 ? '' : 's' ?>.</p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post" action="<?= e(url('notifications/mark_read.php')) ?>" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="all" value="1">
            <input type="hidden" name="return" value="<?= e($returnPath) ?>">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check-double me-1"></i> Mark All as Read</button>
        </form>
    <?php endif; ?>
</div>

<form method="get" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-auto">
            <span class="form-label d-block">Show</span>
            <div class="btn-group" role="group" aria-label="Show">
                <a href="<?= e(url('notifications/index.php' . ($type !== '' ? '?type=' . urlencode($type) : ''))) ?>"
                   class="btn btn-outline-secondary<?= $show === 'all' ? ' active' : '' ?>">All</a>
                <a href="<?= e(url('notifications/index.php?' . http_build_query(array_filter(['show' => 'unread', 'type' => $type])))) ?>"
                   class="btn btn-outline-secondary<?= $show === 'unread' ? ' active' : '' ?>">Unread (<?= $unread ?>)</a>
            </div>
        </div>
        <div class="col-8 col-md-3">
            <label for="type" class="form-label">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">All types</option>
                <?php foreach (NOTIFICATION_TYPE_INFO as $key => [$label]): ?>
                    <option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($show === 'unread'): ?><input type="hidden" name="show" value="unread"><?php endif; ?>
        </div>
        <div class="col-4 col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?= $show === 'unread' ? 'Unread Notifications' : 'All Notifications' ?></span>
        <span class="small text-muted fw-normal"><?= e(pagination_summary($pagination, count($notifications))) ?></span>
    </div>

    <?php if (!$notifications): ?>
        <div class="empty-state">
            <i class="fa-regular fa-bell-slash d-block"></i>
            <?= $show === 'unread' ? 'You have no unread notifications.' : 'No notifications found.' ?>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($notifications as $note):
                $target = notification_target($note, $user['role'], $userId);
                ?>
                <li class="list-group-item notification-row notification-type-<?= e($note['type']) ?><?= $note['is_read'] ? '' : ' unread' ?>">
                    <div class="d-flex gap-3">
                        <span class="notification-item-icon" aria-hidden="true"><i class="fa-solid <?= e(notification_icon($note['type'])) ?>"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <span class="fw-semibold">
                                    <?= e($note['title']) ?>
                                    <?php if (!$note['is_read']): ?><span class="badge badge-status-progress ms-1">New</span><?php endif; ?>
                                </span>
                                <span class="small text-muted text-nowrap"><?= e(format_datetime($note['created_at'])) ?></span>
                            </div>
                            <div class="small mt-1"><?= e($note['message']) ?></div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="badge text-bg-light border"><?= e(notification_type_label($note['type'])) ?></span>
                                <?php if ($target): ?>
                                    <form method="post" action="<?= e(url('notifications/open.php')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary py-0">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open task
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!$note['is_read']): ?>
                                    <form method="post" action="<?= e(url('notifications/mark_read.php')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                                        <input type="hidden" name="return" value="<?= e($returnPath) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary py-0">
                                            <i class="fa-solid fa-check me-1"></i> Mark as read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($pagination['pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-end">
            <?= render_pagination($pagination, $filterQuery) ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
