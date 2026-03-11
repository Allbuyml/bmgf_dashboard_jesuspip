<?php
/**
 * Sector Data Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="sector_data" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Enrollment by Sector</h2>
        <p class="bmgf-help-text">Data for the sector distribution charts (4-Year Public, 2-Year Public, 4-Year Private).</p>

        <div class="bmgf-subtabs">
            <button type="button" class="bmgf-subtab-btn active" data-subtab="sector-calc1">Calculus I</button>
            <button type="button" class="bmgf-subtab-btn" data-subtab="sector-calc2">Calculus II</button>
        </div>

        <div id="sector-calc1" class="bmgf-subtab-content active">
            <table class="bmgf-data-table">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Sector Name</th>
                        <th width="120">Percentage (%)</th>
                        <th width="150">Enrollment Value</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>

        <div id="sector-calc2" class="bmgf-subtab-content">
            <table class="bmgf-data-table">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Sector Name</th>
                        <th width="120">Percentage (%)</th>
                        <th width="150">Enrollment Value</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>
    </div>

    <div class="bmgf-action-bar">
        <div class="bmgf-action-bar-left">
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="sector_data">
                Save Changes
            </button>
        </div>
    </div>
</div>
