<?php
/**
 * Publishers Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="publishers" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Publisher Data</h2>
        <p class="bmgf-help-text">Market share, enrollment, and pricing data for each publisher. Used in pie charts and market share visualizations.</p>

        <table class="bmgf-data-table">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Publisher Name</th>
                    <th width="120">Market Share (%)</th>
                    <th width="140">Enrollment</th>
                    <th width="120">Avg Price ($)</th>
                    <th width="100">Color</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>

    <div class="bmgf-action-bar">
        <div class="bmgf-action-bar-left">
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="publishers">
                Save Changes
            </button>
        </div>
    </div>
</div>
