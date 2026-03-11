<?php
/**
 * KPIs Tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="kpis" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Main KPIs</h2>
        <p class="bmgf-help-text">These metrics appear on the cover page of the dashboard.</p>

        <h3 class="bmgf-section-subtitle">Enrollment Overview</h3>
        <div class="bmgf-form-grid bmgf-form-grid-3">
            <div class="bmgf-form-group">
                <label for="kpi-total-institutions">Total Institutions</label>
                <input type="number" id="kpi-total-institutions" name="kpis[total_institutions]" min="0">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-total-enrollment">Total Calculus Enrollment</label>
                <input type="number" id="kpi-total-enrollment" name="kpis[total_enrollment]" min="0">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-total-fte-enrollment">Total FTE Enrollment</label>
                <input type="number" id="kpi-total-fte-enrollment" name="kpis[total_fte_enrollment]" min="0">
            </div>
        </div>

        <h3 class="bmgf-section-subtitle">Calculus I</h3>
        <div class="bmgf-form-grid bmgf-form-grid-3">
            <div class="bmgf-form-group">
                <label for="kpi-calc1-enrollment">Calc I Enrollment</label>
                <input type="number" id="kpi-calc1-enrollment" name="kpis[calc1_enrollment]" min="0">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-calc1-share">Calc I Share (%)</label>
                <input type="number" id="kpi-calc1-share" name="kpis[calc1_share]" min="0" max="100" step="0.1">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-avg-price-calc1">Avg Price Calc I ($)</label>
                <input type="number" id="kpi-avg-price-calc1" name="kpis[avg_price_calc1]" min="0" step="0.01">
            </div>
        </div>

        <h3 class="bmgf-section-subtitle">Calculus II</h3>
        <div class="bmgf-form-grid bmgf-form-grid-3">
            <div class="bmgf-form-group">
                <label for="kpi-calc2-enrollment">Calc II Enrollment</label>
                <input type="number" id="kpi-calc2-enrollment" name="kpis[calc2_enrollment]" min="0">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-calc2-share">Calc II Share (%)</label>
                <input type="number" id="kpi-calc2-share" name="kpis[calc2_share]" min="0" max="100" step="0.1">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-avg-price-calc2">Avg Price Calc II ($)</label>
                <input type="number" id="kpi-avg-price-calc2" name="kpis[avg_price_calc2]" min="0" step="0.01">
            </div>
        </div>

        <h3 class="bmgf-section-subtitle">Market Distribution</h3>
        <div class="bmgf-form-grid bmgf-form-grid-4">
            <div class="bmgf-form-group">
                <label for="kpi-commercial-share">Commercial Share (%)</label>
                <input type="number" id="kpi-commercial-share" name="kpis[commercial_share]" min="0" max="100">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-oer-share">OER Share (%)</label>
                <input type="number" id="kpi-oer-share" name="kpis[oer_share]" min="0" max="100">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-digital-share">Digital Share (%)</label>
                <input type="number" id="kpi-digital-share" name="kpis[digital_share]" min="0" max="100">
            </div>
            <div class="bmgf-form-group">
                <label for="kpi-print-share">Print Share (%)</label>
                <input type="number" id="kpi-print-share" name="kpis[print_share]" min="0" max="100">
            </div>
        </div>
    </div>

    <div class="bmgf-action-bar">
        <div class="bmgf-action-bar-left">
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="kpis">
                Save Changes
            </button>
        </div>
    </div>
</div>
