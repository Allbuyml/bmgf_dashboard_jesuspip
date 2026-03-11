<?php
/**
 * Regional Data Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="regional_data" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Enrollment by Region</h2>
        <p class="bmgf-help-text">Data for the regional enrollment distribution charts. Values in thousands (K).</p>

        <div class="bmgf-subtabs">
            <button type="button" class="bmgf-subtab-btn active" data-subtab="regional-calc1">Calculus I</button>
            <button type="button" class="bmgf-subtab-btn" data-subtab="regional-calc2">Calculus II</button>
        </div>

        <div id="regional-calc1" class="bmgf-subtab-content active">
            <table class="bmgf-data-table">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Region Name</th>
                        <th width="120">Percentage (%)</th>
                        <th width="150">Enrollment Value</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>

        <div id="regional-calc2" class="bmgf-subtab-content">
            <table class="bmgf-data-table">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Region Name</th>
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
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="regional_data">
                Save Changes
            </button>
        </div>
    </div>
</div>
