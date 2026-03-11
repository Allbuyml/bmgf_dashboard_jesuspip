<?php
/**
 * Institution Size Data Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="institution_size_data" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Enrollment by Institution Size</h2>
        <p class="bmgf-help-text">Calculus I and II enrollment by institution size category. Values in thousands (K).</p>

        <table class="bmgf-data-table">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Size Category</th>
                    <th width="150">Calc I (K)</th>
                    <th width="150">Calc II (K)</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>

    <div class="bmgf-action-bar">
        <div class="bmgf-action-bar-left">
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="institution_size_data">
                Save Changes
            </button>
        </div>
    </div>
</div>
