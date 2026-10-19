<?php
/**
 * EODB-TaskFlow - Page footer and layout end
 *
 * Closes the layout opened by header.php and loads the JavaScript files.
 * A page can add its own scripts by setting $pageScripts before including:
 *   $pageScripts = ['js/users.js'];   // paths inside assets/
 * Pages with charts also set $useCharts = true to load Chart.js first.
 */

declare(strict_types=1);

$pageScripts = $pageScripts ?? [];
$useCharts   = $useCharts ?? false;   // true = load Chart.js before the page scripts
?>
        </main>

        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(office_name()) ?></span>
            <span><?= e(app_name()) ?> v<?= e(APP_VERSION) ?></span>
        </footer>
    </div>
</div>

<script src="<?= e(CDN_BOOTSTRAP_JS) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if ($useCharts): ?>
    <script src="<?= e(CDN_CHARTJS) ?>"></script>
<?php endif; ?>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
