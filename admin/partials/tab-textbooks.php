<?php
/**
 * Top Textbooks Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="top_textbooks" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Top 10 Textbooks</h2>
        <p class="bmgf-help-text">The top textbooks by enrollment. Includes publisher information for color coding in charts.</p>

        <table class="bmgf-data-table">
            <thead>
                <tr>
                    <th width="50">Rank</th>
                    <th>Textbook Name</th>
                    <th width="150">Publisher</th>
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
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="top_textbooks">
                Save Changes
            </button>
        </div>
    </div>
</div>
