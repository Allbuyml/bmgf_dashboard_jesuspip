<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bmgf-tab-panel" id="branding">
    <form class="bmgf-form" data-section="branding">
        
        <div class="bmgf-section">
            <h3 class="bmgf-section-title">General Settings</h3>
            <div class="bmgf-form-grid">
                <div class="bmgf-form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" value="">
                    <p class="bmgf-help-text">Used in the footer and alt attributes.</p>
                </div>
            </div>
        </div>

        <div class="bmgf-section">
            <h3 class="bmgf-section-title">Typography</h3>
            <div class="bmgf-form-grid">
                <div class="bmgf-form-group">
                    <label>Dashboard Font Family</label>
                    <select name="font_family" style="width: 100%; max-width: 400px;">
                        <option value="Inter Tight">Inter Tight (Default)</option>
                        <option value="Arial">Arial</option>
                        <option value="Roboto">Roboto</option>
                        <option value="Open Sans">Open Sans</option>
                        <option value="Montserrat">Montserrat</option>
                        <option value="Lato">Lato</option>
                        <option value="Poppins">Poppins</option>
                    </select>
                    <p class="bmgf-help-text">Select the font that will be applied globally to all charts and texts.</p>
                </div>
            </div>
        </div>

        <div class="bmgf-section">
            <h3 class="bmgf-section-title">Dashboard Titles</h3>
            <div class="bmgf-form-grid">
                <div class="bmgf-form-group">
                    <label>Dashboard Title (Line 1)</label>
                    <input type="text" name="dashboard_title_line1" value="">
                </div>
                
                <div class="bmgf-form-group">
                    <label>Dashboard Title (Line 2 - Highlight Italic)</label>
                    <input type="text" name="dashboard_title_line2_highlight" value="">
                </div>

                <div class="bmgf-form-group">
                    <label>Dashboard Title (Line 2 - Normal)</label>
                    <input type="text" name="dashboard_title_line2_normal" value="">
                </div>
            </div>
        </div>

        <div class="bmgf-section">
            <h3 class="bmgf-section-title">Logos</h3>
            <p class="bmgf-help-text" style="margin-bottom: 15px; color: #6b7280;">
                <em>Note: If you leave the Footer Logo empty, the system will automatically use the Main Logo everywhere.</em>
            </p>
            <div class="bmgf-form-grid">
                <div class="bmgf-form-group">
                    <label>Header Main Logo (Global)</label>
                    <div style="display:flex;gap:10px;">
                        <input type="url" name="logo_url" id="bmgf_logo_url" value="" style="flex:1;">
                        <button type="button" class="button bmgf-upload-image-btn" data-target="bmgf_logo_url">Upload</button>
                    </div>
                </div>

                <div class="bmgf-form-group">
                    <label>Footer Logo (Optional)</label>
                    <div style="display:flex;gap:10px;">
                        <input type="url" name="footer_logo_url" id="bmgf_footer_logo_url" value="" style="flex:1;">
                        <button type="button" class="button bmgf-upload-image-btn" data-target="bmgf_footer_logo_url">Upload</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="bmgf-section">
            <h3 class="bmgf-section-title">Brand Colors</h3>
            <p class="bmgf-help-text" style="margin-bottom: 15px; color: #6b7280;">These colors are dynamically injected into all charts, map, and inner pages.</p>
            
            <div class="bmgf-form-grid bmgf-form-grid-3">
                <div class="bmgf-form-group">
                    <label>Primary Color</label>
                    <input type="color" name="primary_color" value="#008384">
                    <p class="bmgf-help-text">Main theme, active tabs, Calculus I charts.</p>
                </div>

                <div class="bmgf-form-group">
                    <label>Secondary Color</label>
                    <input type="color" name="secondary_color" value="#234A5D">
                    <p class="bmgf-help-text">Dashboard Texts, Titles, inactive tabs.</p>
                </div>

                <div class="bmgf-form-group">
                    <label>Accent Color</label>
                    <input type="color" name="accent_color" value="#7FBFC0">
                    <p class="bmgf-help-text">Calculus II chart data, main map highlight.</p>
                </div>

                <div class="bmgf-form-group">
                    <label>Tertiary Color</label>
                    <input type="color" name="tertiary_color" value="#4A81A8">
                    <p class="bmgf-help-text">3rd data series and map states mid-tones.</p>
                </div>

                <div class="bmgf-form-group">
                    <label>Quaternary Color</label>
                    <input type="color" name="quaternary_color" value="#92A4CF">
                    <p class="bmgf-help-text">4th data series or soft graphic details.</p>
                </div>

                <div class="bmgf-form-group">
                    <label>Light Background Color</label>
                    <input type="color" name="light_accent_color" value="#D3DEF6">
                    <p class="bmgf-help-text">Soft backgrounds (like the Lavender card).</p>
                </div>
            </div>
        </div>

        <div class="bmgf-action-bar">
            <button type="button" class="bmgf-btn bmgf-btn-primary bmgf-save-section" data-section="branding">Save Branding</button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // WordPress Media Uploader for Logos
    var custom_uploader;
    $('.bmgf-upload-image-btn').click(function(e) {
        e.preventDefault();
        var targetInput = $('#' + $(this).data('target'));

        if (custom_uploader) {
            custom_uploader.open();
            custom_uploader.targetInput = targetInput;
            return;
        }
        
        custom_uploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Image',
            button: { text: 'Choose Image' },
            multiple: false
        });

        custom_uploader.targetInput = targetInput;

        custom_uploader.on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            custom_uploader.targetInput.val(attachment.url).trigger('change');
        });

        custom_uploader.open();
    });
});
</script>