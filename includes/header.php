<?php
/**
 * EODB-TaskFlow - Page header and layout start
 *
 * Usage at the top of a page (after require_role()/require_login()):
 *
 *   $pageTitle  = 'Employees';     // browser tab title
 *   $activeMenu = 'employees';     // sidebar item to highlight (optional)
 *   require __DIR__ . '/../includes/header.php';
 *   ... page content ...
 *   require __DIR__ . '/../includes/footer.php';
 *
 * Opens the layout (sidebar + top navigation) and shows pending flash messages.
 */

declare(strict_types=1);

$pageTitle  = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(app_name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(CDN_BOOTSTRAP_CSS) ?>">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <script src="<?= e(asset('js/theme-init.js')) ?>"></script>
    <script src="<?= e(asset('js/sidebar-state.js')) ?>"></script>
</head>
<body class="app-body">
<a class="visually-hidden-focusable skip-link" href="#mainContent">Skip to main content</a>

<div class="app-wrapper">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <?php require __DIR__ . '/navbar.php'; ?>

        <main class="app-content" id="mainContent">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
