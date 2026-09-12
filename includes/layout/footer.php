<?php
declare(strict_types=1);

/**
 * RiskOps - Sayfa alt sablonu
 * /var/www/riskops/includes/layout/footer.php
 */

if (!defined('RISKOPS_BOOTSTRAPPED')) {
    http_response_code(403);
    exit('Direct access denied.');
}

$assetVersion = $assetVersion ?? '20260911n';
$needsCharts  = $needsCharts  ?? false;   // sayfa Chart.js istiyorsa true yapar
?>
        <?php require PARTIALS_PATH . '/print_footer.php'; ?>
    </main><!-- /.rk-content -->

    <footer class="text-center text-muted rk-u-foot-note">
        <?= e(app_name()) ?>
        <?php if ((string)setting('company_name', '') !== ''): ?>
            &middot; <?= e((string)setting('company_name')) ?>
        <?php endif; ?>
        &middot; <?= e(date('Y')) ?>
    </footer>

</div><!-- /.rk-main -->

<script src="<?= e(url('/assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?php if ($needsCharts): ?>
<script src="<?= e(url('/assets/vendor/chartjs/chart.umd.min.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(url('/assets/js/app.js?v=' . $assetVersion)) ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
