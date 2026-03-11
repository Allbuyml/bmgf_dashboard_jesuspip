<?php
/**
 * Top Institutions Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="top_institutions" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Top 10 Institutions</h2>
        <p class="bmgf-help-text">The top institutions by enrollment. Enrollment values are in thousands (e.g., 98000 = 98K).</p>

        <table class="bmgf-data-table">
            <thead>
                <tr>
                    <th width="50">Rank</th>
                    <th>Institution Name</th>
                    <th width="150">Enrollment</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>

    <div class="bmgf-action-bar">
        <div class="bmgf-action-bar-left">
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="top_institutions">
                Save Changes
            </button>
        </div>
    </div>
</div>
