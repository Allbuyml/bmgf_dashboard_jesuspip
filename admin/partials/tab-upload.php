<?php
/**
 * Upload Data Tab
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="upload_data" class="bmgf-tab-panel">
    <div class="bmgf-section">
        <h2 class="bmgf-section-title">Bulk Data Upload</h2>
        <p style="color: var(--bmgf-text-muted); margin-bottom: 24px;">
            Upload Institutions and/or Courses XLSX/CSV files to automatically populate all dashboard sections.
            You can upload one or both files. Preview the computed data before applying.
        </p>

        <div class="bmgf-upload-grid">
            <!-- Institutions File -->
            <div class="bmgf-upload-zone" id="bmgf-upload-institutions" data-type="institutions">
                <div class="bmgf-upload-zone-inner">
                    <div class="bmgf-upload-icon">
                        <span class="dashicons dashicons-building"></span>
                    </div>
                    <h3>Institutions File</h3>
                    <p>Drag & drop your institutions XLSX/CSV file here, or click to browse</p>
                    <p class="bmgf-upload-hint">Required columns: State, Region, Sector, School, FTE Enrollment, Calc I/II Enrollment, Publisher_Norm (or Publisher)</p>
                    <input type="file" class="bmgf-upload-input" accept=".xlsx,.csv" style="display:none;">
                    <div class="bmgf-upload-file-info" style="display:none;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span class="bmgf-upload-filename"></span>
                        <button type="button" class="bmgf-upload-remove" title="Remove file">&times;</button>
                    </div>
                </div>
                <div class="bmgf-upload-progress" style="display:none;">
                    <div class="bmgf-upload-progress-bar"><div class="bmgf-upload-progress-fill"></div></div>
                    <span class="bmgf-upload-progress-text">0%</span>
                </div>
            </div>

            <!-- Courses File -->
            <div class="bmgf-upload-zone" id="bmgf-upload-courses" data-type="courses">
                <div class="bmgf-upload-zone-inner">
                    <div class="bmgf-upload-icon">
                        <span class="dashicons dashicons-book-alt"></span>
                    </div>
                    <h3>Courses File</h3>
                    <p>Drag & drop your courses XLSX/CSV file here, or click to browse</p>
                    <p class="bmgf-upload-hint">Required columns: State, School, Period, Enrollments, Book Title Normalized, Calc Level, Publisher_Normalized</p>
                    <input type="file" class="bmgf-upload-input" accept=".xlsx,.csv" style="display:none;">
                    <div class="bmgf-upload-file-info" style="display:none;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span class="bmgf-upload-filename"></span>
                        <button type="button" class="bmgf-upload-remove" title="Remove file">&times;</button>
                    </div>
                </div>
                <div class="bmgf-upload-progress" style="display:none;">
                    <div class="bmgf-upload-progress-bar"><div class="bmgf-upload-progress-fill"></div></div>
                    <span class="bmgf-upload-progress-text">0%</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bmgf-upload-actions">
            <button type="button" class="bmgf-btn bmgf-btn-primary" id="bmgf-process-upload" disabled>
                <span class="dashicons dashicons-upload"></span>
                Process &amp; Preview
            </button>
            <div class="bmgf-upload-status" id="bmgf-upload-status" style="display:none;"></div>
        </div>

        <!-- Preview Section -->
        <div class="bmgf-upload-preview" id="bmgf-upload-preview" style="display:none;">
            <h2 class="bmgf-section-title" style="margin-top: 30px;">Data Preview</h2>
            <p style="color: var(--bmgf-text-muted); margin-bottom: 16px;">
                Review the computed data below before applying to the dashboard.
            </p>

            <div class="bmgf-preview-grid" id="bmgf-preview-content">
                <!-- Populated by JS -->
            </div>

            <div class="bmgf-upload-actions" style="margin-top: 20px;">
                <button type="button" class="bmgf-btn bmgf-btn-primary" id="bmgf-apply-upload">
                    <span class="dashicons dashicons-saved"></span>
                    Apply to Dashboard
                </button>
                <button type="button" class="bmgf-btn bmgf-btn-secondary" id="bmgf-cancel-upload">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
