<?php
/**
 * Admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bmgf-admin-wrap">
    <div class="bmgf-admin-header">
        <h1>BMGF Calculus Dashboard Settings</h1>
        <p>Manage all dashboard data including branding, KPIs, charts, and filter options.</p>
    </div>

    <nav class="bmgf-tabs-nav">
        <button type="button" class="bmgf-tab-btn" data-tab="branding">Branding & Settings</button>
        <button type="button" class="bmgf-tab-btn" data-tab="upload_data">Upload Data</button>
        <button type="button" class="bmgf-tab-btn" data-tab="kpis">KPIs</button>
        <button type="button" class="bmgf-tab-btn" data-tab="regional_data">Regional Data</button>
        <button type="button" class="bmgf-tab-btn" data-tab="sector_data">Sector Data</button>
        <button type="button" class="bmgf-tab-btn" data-tab="publishers">Publishers</button>
        <button type="button" class="bmgf-tab-btn" data-tab="top_institutions">Top Institutions</button>
        <button type="button" class="bmgf-tab-btn" data-tab="top_textbooks">Top Textbooks</button>
        <button type="button" class="bmgf-tab-btn" data-tab="period_data">Periods</button>
        <button type="button" class="bmgf-tab-btn" data-tab="institution_size_data">Institution Sizes</button>
        <button type="button" class="bmgf-tab-btn" data-tab="filters">Filters</button>
    </nav>

    <div class="bmgf-tabs-content">
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-branding.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-upload.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-kpis.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-regional.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-sectors.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-publishers.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-institutions.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-textbooks.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-periods.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-sizes.php'; ?>
        <?php include BMGF_DASHBOARD_PATH . 'admin/partials/tab-filters.php'; ?>
    </div>

    <div class="bmgf-action-bar" style="background: #fff; padding: 20px 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 12px 12px; margin-top: -1px;">
        <div class="bmgf-action-bar-left">
            <button type="button" class="bmgf-btn bmgf-btn-danger bmgf-reset-defaults">
                <span class="dashicons dashicons-image-rotate"></span>
                Reset to Defaults
            </button>
        </div>
    </div>
</div>