<?php
/**
 * Filters Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="filters" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Filter Options</h2>
        <p class="bmgf-help-text">Manage the dropdown filter options available in the dashboard. Click the X to remove an item, or use the Add button to add new ones.</p>

        <div class="bmgf-form-grid bmgf-form-grid-2" style="gap: 30px;">
            <!-- States -->
            <div class="bmgf-card">
                <h3 class="bmgf-card-title">States</h3>
                <div class="bmgf-filter-list" data-filter="states">
                    <!-- Populated by JS -->
                </div>
                <button type="button" class="bmgf-btn bmgf-btn-secondary bmgf-add-filter-item" data-filter="states" style="margin-top: 10px; width: 100%;">
                    <span class="dashicons dashicons-plus-alt2"></span> Add State
                </button>
            </div>

            <!-- Regions -->
            <div class="bmgf-card">
                <h3 class="bmgf-card-title">Regions</h3>
                <div class="bmgf-filter-list" data-filter="regions">
                    <!-- Populated by JS -->
                </div>
                <button type="button" class="bmgf-btn bmgf-btn-secondary bmgf-add-filter-item" data-filter="regions" style="margin-top: 10px; width: 100%;">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Region
                </button>
            </div>

            <!-- Sectors -->
            <div class="bmgf-card">
                <h3 class="bmgf-card-title">Sectors</h3>
                <div class="bmgf-filter-list" data-filter="sectors">
                    <!-- Populated by JS -->
                </div>
                <button type="button" class="bmgf-btn bmgf-btn-secondary bmgf-add-filter-item" data-filter="sectors" style="margin-top: 10px; width: 100%;">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Sector
                </button>
            </div>

            <!-- Publishers -->
            <div class="bmgf-card">
                <h3 class="bmgf-card-title">Publishers</h3>
                <div class="bmgf-filter-list" data-filter="publishers">
                    <!-- Populated by JS -->
                </div>
                <button type="button" class="bmgf-btn bmgf-btn-secondary bmgf-add-filter-item" data-filter="publishers" style="margin-top: 10px; width: 100%;">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Publisher
                </button>
            </div>

            <!-- Courses -->
            <div class="bmgf-card">
                <h3 class="bmgf-card-title">Courses</h3>
                <div class="bmgf-filter-list" data-filter="courses">
                    <!-- Populated by JS -->
                </div>
                <button type="button" class="bmgf-btn bmgf-btn-secondary bmgf-add-filter-item" data-filter="courses" style="margin-top: 10px; width: 100%;">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Course
                </button>
            </div>

            <!-- Price Ranges -->
            <div class="bmgf-card">
                <h3 class="bmgf-card-title">Price Ranges</h3>
                <div class="bmgf-filter-list" data-filter="price_ranges">
                    <!-- Populated by JS -->
                </div>
                <button type="button" class="bmgf-btn bmgf-btn-secondary bmgf-add-filter-item" data-filter="price_ranges" style="margin-top: 10px; width: 100%;">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Price Range
                </button>
            </div>
        </div>
    </div>

    <div class="bmgf-action-bar">
        <div class="bmgf-action-bar-left">
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="filters">
                Save Changes
            </button>
        </div>
    </div>
</div>
