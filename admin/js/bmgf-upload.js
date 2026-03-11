/**
 * BMGF Dashboard - File Upload Handler
 */

(function($) {
    'use strict';

    const BMGFUpload = {
        files: {
            institutions: null,
            courses: null
        },
        uploadedKeys: {
            institutions: null,
            courses: null
        },

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Drag & drop zones
            $('.bmgf-upload-zone').each(function() {
                const $zone = $(this);
                const $input = $zone.find('.bmgf-upload-input');
                const type = $zone.data('type');

                // Click to browse
                $zone.find('.bmgf-upload-zone-inner').on('click', function(e) {
                    if (!$(e.target).hasClass('bmgf-upload-remove')) {
                        $input.trigger('click');
                    }
                });

                // File input change
                $input.on('change', function() {
                    if (this.files && this.files[0]) {
                        BMGFUpload.setFile(type, this.files[0], $zone);
                    }
                });

                // Drag events
                $zone.on('dragover dragenter', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $zone.addClass('bmgf-upload-dragover');
                });

                $zone.on('dragleave drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $zone.removeClass('bmgf-upload-dragover');
                });

                $zone.on('drop', function(e) {
                    var files = e.originalEvent.dataTransfer.files;
                    if (files && files[0]) {
                        BMGFUpload.setFile(type, files[0], $zone);
                    }
                });

                // Remove file
                $zone.find('.bmgf-upload-remove').on('click', function(e) {
                    e.stopPropagation();
                    BMGFUpload.removeFile(type, $zone);
                });
            });

            // Process button
            $('#bmgf-process-upload').on('click', this.handleProcess.bind(this));

            // Apply button
            $('#bmgf-apply-upload').on('click', this.handleApply.bind(this));

            // Cancel button
            $('#bmgf-cancel-upload').on('click', this.handleCancel.bind(this));
        },

        setFile: function(type, file, $zone) {
            // Validate extension
            var ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'xlsx' && ext !== 'csv') {
                this.showStatus('error', 'Invalid file type. Please upload .xlsx or .csv files.');
                return;
            }

            // Validate size (50MB)
            if (file.size > 50 * 1024 * 1024) {
                this.showStatus('error', 'File is too large. Maximum size is 50MB.');
                return;
            }

            this.files[type] = file;
            this.uploadedKeys[type] = null;

            $zone.find('.bmgf-upload-filename').text(file.name + ' (' + this.formatFileSize(file.size) + ')');
            $zone.find('.bmgf-upload-file-info').show();
            $zone.addClass('bmgf-upload-has-file');

            this.updateProcessButton();
            // Hide previous preview
            $('#bmgf-upload-preview').hide();
        },

        removeFile: function(type, $zone) {
            this.files[type] = null;
            this.uploadedKeys[type] = null;

            $zone.find('.bmgf-upload-input').val('');
            $zone.find('.bmgf-upload-file-info').hide();
            $zone.find('.bmgf-upload-progress').hide();
            $zone.removeClass('bmgf-upload-has-file');

            this.updateProcessButton();
        },

        updateProcessButton: function() {
            var hasFile = this.files.institutions || this.files.courses;
            $('#bmgf-process-upload').prop('disabled', !hasFile);
        },

        handleProcess: function(e) {
            e.preventDefault();
            var self = this;
            var uploads = [];

            // Upload each file
            if (this.files.institutions) {
                uploads.push(this.uploadFile('institutions', this.files.institutions));
            }
            if (this.files.courses) {
                uploads.push(this.uploadFile('courses', this.files.courses));
            }

            if (uploads.length === 0) {
                this.showStatus('error', 'Please select at least one file to upload.');
                return;
            }

            var $btn = $('#bmgf-process-upload');
            $btn.prop('disabled', true).html('<span class="bmgf-spinner"></span> Processing...');
            this.showStatus('info', 'Uploading and processing files...');

            $.when.apply($, uploads).then(function() {
                // All uploads succeeded, now request preview
                self.requestPreview($btn);
            }).fail(function(error) {
                self.showStatus('error', error || 'Upload failed. Please try again.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Process & Preview');
            });
        },

        uploadFile: function(type, file) {
            var self = this;
            var deferred = $.Deferred();
            var $zone = $('#bmgf-upload-' + type);
            var $progress = $zone.find('.bmgf-upload-progress');
            var $fill = $zone.find('.bmgf-upload-progress-fill');
            var $text = $zone.find('.bmgf-upload-progress-text');

            $progress.show();

            var formData = new FormData();
            formData.append('action', 'bmgf_upload_file');
            formData.append('nonce', bmgfAdmin.nonce);
            formData.append('file_type', type);
            formData.append('file', file);

            var xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var pct = Math.round((e.loaded / e.total) * 100);
                    $fill.css('width', pct + '%');
                    $text.text(pct + '%');
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            self.uploadedKeys[type] = response.data.transient_key;
                            $fill.css('width', '100%');
                            $text.text('Done');
                            $zone.addClass('bmgf-upload-success');
                            deferred.resolve();
                        } else {
                            $zone.addClass('bmgf-upload-error');
                            var errMsg = response.data.message || 'Upload failed.';
                            if (response.data.debug) {
                                try {
                                    errMsg += ' [debug: ' + JSON.stringify(response.data.debug) + ']';
                                } catch (e) {}
                            }
                            deferred.reject(errMsg);
                        }
                    } catch (err) {
                        deferred.reject('Invalid server response.');
                    }
                } else {
                    deferred.reject('Server error (' + xhr.status + ')');
                }
            });

            xhr.addEventListener('error', function() {
                deferred.reject('Network error during upload.');
            });

            xhr.open('POST', bmgfAdmin.ajaxUrl);
            xhr.send(formData);

            return deferred.promise();
        },

        requestPreview: function($btn) {
            var self = this;

            $.ajax({
                url: bmgfAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bmgf_apply_upload',
                    nonce: bmgfAdmin.nonce,
                    institutions_key: this.uploadedKeys.institutions || '',
                    courses_key: this.uploadedKeys.courses || '',
                    preview_only: 1
                },
                success: function(response) {
                    if (response.success) {
                        self.showPreview(response.data.preview);
                        self.showStatus('success', 'Data processed successfully. Review the preview below and click "Apply" to save.');
                    } else {
                        self.showStatus('error', response.data.message || 'Processing failed.');
                    }
                },
                error: function() {
                    self.showStatus('error', 'Network error during processing.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Process & Preview');
                }
            });
        },

        showPreview: function(preview) {
            var $container = $('#bmgf-preview-content');
            $container.empty();

            var sectionLabels = {
                'kpis': 'KPIs',
                'regional_data': 'Regional Data',
                'sector_data': 'Sector Data',
                'publishers': 'Publishers',
                'top_institutions': 'Top Institutions',
                'top_textbooks': 'Top Textbooks',
                'period_data': 'Period Data',
                'institution_size_data': 'Institution Sizes',
                'state_data': 'State Map Data'
            };

            for (var key in preview) {
                if (preview.hasOwnProperty(key)) {
                    var label = sectionLabels[key] || key;
                    var $card = $('<div class="bmgf-preview-card">' +
                        '<div class="bmgf-preview-card-title">' +
                        '<span class="dashicons dashicons-yes-alt"></span> ' + label +
                        '</div>' +
                        '<div class="bmgf-preview-card-value">' + preview[key] + '</div>' +
                        '</div>');
                    $container.append($card);
                }
            }

            $('#bmgf-upload-preview').show();
        },

        handleApply: function(e) {
            e.preventDefault();

            var hasInstitutions = !!this.uploadedKeys.institutions;
            var hasCourses = !!this.uploadedKeys.courses;
            var confirmMessage = (hasInstitutions && hasCourses)
                ? 'This will overwrite all current dashboard data with the uploaded files. Continue?'
                : 'This will partially update the dashboard using only the uploaded file(s). Other sections will remain unchanged. Continue?';

            if (!confirm(confirmMessage)) {
                return;
            }

            var self = this;
            var $btn = $('#bmgf-apply-upload');
            $btn.prop('disabled', true).html('<span class="bmgf-spinner"></span> Applying...');
            this.showStatus('info', 'Saving data to dashboard...');

            $.ajax({
                url: bmgfAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bmgf_apply_upload',
                    nonce: bmgfAdmin.nonce,
                    institutions_key: this.uploadedKeys.institutions || '',
                    courses_key: this.uploadedKeys.courses || '',
                    preview_only: 0
                },
                success: function(response) {
                    if (response.success) {
                        self.showStatus('success', 'Data applied successfully! The dashboard has been updated.');
                        $('#bmgf-upload-preview').hide();

                        // Update BMGFAdmin data if available
                        if (typeof BMGFAdmin !== 'undefined' && response.data.computed) {
                            var computed = response.data.computed;
                            for (var section in computed) {
                                if (section !== 'state_data' && computed.hasOwnProperty(section)) {
                                    BMGFAdmin.data[section] = computed[section];
                                }
                            }
                            if (typeof BMGFAdmin.populateAllForms === 'function') {
                                BMGFAdmin.populateAllForms();
                            }
                        }

                        // Reset upload state
                        self.resetAll();
                    } else {
                        self.showStatus('error', response.data.message || 'Failed to apply data.');
                    }
                },
                error: function() {
                    self.showStatus('error', 'Network error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Apply to Dashboard');
                }
            });
        },

        handleCancel: function(e) {
            e.preventDefault();
            $('#bmgf-upload-preview').hide();
            this.showStatus('', '');
        },

        resetAll: function() {
            this.files = { institutions: null, courses: null };
            this.uploadedKeys = { institutions: null, courses: null };

            $('.bmgf-upload-zone').each(function() {
                var $zone = $(this);
                $zone.find('.bmgf-upload-input').val('');
                $zone.find('.bmgf-upload-file-info').hide();
                $zone.find('.bmgf-upload-progress').hide();
                $zone.removeClass('bmgf-upload-has-file bmgf-upload-success bmgf-upload-error bmgf-upload-dragover');
            });

            this.updateProcessButton();
        },

        showStatus: function(type, message) {
            var $status = $('#bmgf-upload-status');
            if (!message) {
                $status.hide();
                return;
            }

            var iconMap = {
                'success': 'dashicons-yes-alt',
                'error': 'dashicons-dismiss',
                'info': 'dashicons-info'
            };

            var icon = iconMap[type] || 'dashicons-info';
            $status.html('<span class="dashicons ' + icon + '"></span> ' + message)
                   .attr('class', 'bmgf-upload-status bmgf-upload-status-' + type)
                   .show();
        },

        formatFileSize: function(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }
    };

    $(document).ready(function() {
        BMGFUpload.init();
    });

})(jQuery);
