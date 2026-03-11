/**
 * BMGF Dashboard Admin JavaScript
 */

(function($) {
    'use strict';

    const BMGFAdmin = {
        data: {},
        currentTab: 'kpis',

        init: function() {
            this.data = bmgfAdmin.data || {};
            this.bindEvents();
            this.initTabs();
            this.populateAllForms();
        },

        bindEvents: function() {
            $(document).on('click', '.bmgf-tab-btn', this.handleTabClick.bind(this));
            $(document).on('click', '.bmgf-subtab-btn', this.handleSubtabClick.bind(this));
            $(document).on('click', '.bmgf-save-section', this.handleSaveSection.bind(this));
            $(document).on('click', '.bmgf-reset-defaults', this.handleResetDefaults.bind(this));
            $(document).on('input', 'input[type="color"]', this.handleColorChange.bind(this));
            $(document).on('click', '.bmgf-add-filter-item', this.handleAddFilterItem.bind(this));
            $(document).on('click', '.bmgf-filter-remove', this.handleRemoveFilterItem.bind(this));
        },

        initTabs: function() {
            const $firstTab = $('.bmgf-tab-btn').first();
            if ($firstTab.length) {
                $firstTab.addClass('active');
                const tabId = $firstTab.data('tab');
                $('#' + tabId).addClass('active');
                this.currentTab = tabId;
            }
        },

        handleTabClick: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const tabId = $btn.data('tab');

            $('.bmgf-tab-btn').removeClass('active');
            $btn.addClass('active');

            $('.bmgf-tab-panel').removeClass('active');
            $('#' + tabId).addClass('active');

            this.currentTab = tabId;
        },

        handleSubtabClick: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const subtabId = $btn.data('subtab');
            const $parent = $btn.closest('.bmgf-section');

            $parent.find('.bmgf-subtab-btn').removeClass('active');
            $btn.addClass('active');

            $parent.find('.bmgf-subtab-content').removeClass('active');
            $parent.find('#' + subtabId).addClass('active');
        },

        handleSaveSection: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const section = $btn.closest('form').data('section') || $btn.data('section');

            $btn.prop('disabled', true).html('<span class="bmgf-spinner"></span> Saving...');

            const data = this.collectSectionData(section);

            $.ajax({
                url: bmgfAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bmgf_save_section',
                    nonce: bmgfAdmin.nonce,
                    section: section,
                    data: data
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', 'Changes saved successfully!');
                        this.data[section] = data; // Update local data
                    } else {
                        this.showNotice('error', response.data.message || 'Failed to save changes.');
                    }
                },
                error: () => {
                    this.showNotice('error', 'Network error. Please try again.');
                },
                complete: () => {
                    $btn.prop('disabled', false).html('Save Changes');
                }
            });
        },

        handleResetDefaults: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to reset all data to defaults? This cannot be undone.')) {
                return;
            }

            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).html('<span class="bmgf-spinner"></span> Resetting...');

            $.ajax({
                url: bmgfAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bmgf_reset_defaults',
                    nonce: bmgfAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice('success', 'Reset to defaults successfully!');
                        this.data = response.data.data;
                        this.populateAllForms();
                    } else {
                        this.showNotice('error', response.data.message || 'Failed to reset.');
                    }
                },
                error: () => {
                    this.showNotice('error', 'Network error. Please try again.');
                },
                complete: () => {
                    $btn.prop('disabled', false).html('Reset to Defaults');
                }
            });
        },

        handleColorChange: function(e) {
            const $input = $(e.currentTarget);
            const $preview = $input.siblings('.bmgf-color-preview');
            if ($preview.length) {
                $preview.css('background-color', $input.val());
            }
        },

        handleAddFilterItem: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const filterKey = $btn.data('filter');
            const $list = $btn.siblings('.bmgf-filter-list');

            const $item = $(`
                <div class="bmgf-filter-item">
                    <input type="text" name="filters[${filterKey}][]" value="" placeholder="Enter value...">
                    <span class="bmgf-filter-remove dashicons dashicons-no-alt"></span>
                </div>
            `);

            $list.append($item);
            $item.find('input').focus();
        },

        handleRemoveFilterItem: function(e) {
            e.preventDefault();
            $(e.currentTarget).closest('.bmgf-filter-item').remove();
        },

        collectSectionData: function(section) {
            let data = {};
            const $panel = $('#' + section);

            switch (section) {
                case 'branding':
                    data = this.collectBrandingData();
                    break;
                case 'kpis':
                    data = this.collectKPIsData($panel);
                    break;
                case 'regional_data':
                    data = this.collectRegionalData($panel);
                    break;
                case 'sector_data':
                    data = this.collectSectorData($panel);
                    break;
                case 'publishers':
                    data = this.collectPublishersData($panel);
                    break;
                case 'top_institutions':
                    data = this.collectTopItemsData($panel, 'institutions');
                    break;
                case 'top_textbooks':
                    data = this.collectTopItemsData($panel, 'textbooks');
                    break;
                case 'period_data':
                    data = this.collectPeriodData($panel);
                    break;
                case 'institution_size_data':
                    data = this.collectSizeData($panel);
                    break;
                case 'filters':
                    data = this.collectFiltersData($panel);
                    break;
            }

            return data;
        },

        collectBrandingData: function() {
            const data = {};
            // Select elements explicitly inside the branding form to avoid DOM ID issues
            const $form = $('form[data-section="branding"]');
            
            $form.find('input, select, textarea').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    data[name] = $(this).val();
                }
            });
            return data;
        },

        collectKPIsData: function($panel) {
            const data = {};
            $panel.find('input[name^="kpis["]').each(function() {
                const name = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
                data[name] = parseFloat($(this).val()) || 0;
            });
            return data;
        },

        collectRegionalData: function($panel) {
            const data = { calc1: [], calc2: [] };

            ['calc1', 'calc2'].forEach(calc => {
                $panel.find(`#regional-${calc} .bmgf-data-table tbody tr`).each(function() {
                    const $row = $(this);
                    data[calc].push({
                        name: $row.find('input[name$="[name]"]').val(),
                        percentage: parseInt($row.find('input[name$="[percentage]"]').val()) || 0,
                        value: parseInt($row.find('input[name$="[value]"]').val()) || 0
                    });
                });
            });

            return data;
        },

        collectSectorData: function($panel) {
            const data = { calc1: [], calc2: [] };

            ['calc1', 'calc2'].forEach(calc => {
                $panel.find(`#sector-${calc} .bmgf-data-table tbody tr`).each(function() {
                    const $row = $(this);
                    data[calc].push({
                        name: $row.find('input[name$="[name]"]').val(),
                        percentage: parseInt($row.find('input[name$="[percentage]"]').val()) || 0,
                        value: parseInt($row.find('input[name$="[value]"]').val()) || 0
                    });
                });
            });

            return data;
        },

        collectPublishersData: function($panel) {
            const data = [];

            $panel.find('.bmgf-data-table tbody tr').each(function() {
                const $row = $(this);
                data.push({
                    name: $row.find('input[name$="[name]"]').val(),
                    market_share: parseInt($row.find('input[name$="[market_share]"]').val()) || 0,
                    enrollment: parseInt($row.find('input[name$="[enrollment]"]').val()) || 0,
                    avg_price: parseFloat($row.find('input[name$="[avg_price]"]').val()) || 0,
                    color: $row.find('input[name$="[color]"]').val()
                });
            });

            return data;
        },

        collectTopItemsData: function($panel, type) {
            const data = [];

            $panel.find('.bmgf-data-table tbody tr').each(function() {
                const $row = $(this);
                const item = {
                    name: $row.find('input[name$="[name]"]').val(),
                    enrollment: parseInt($row.find('input[name$="[enrollment]"]').val()) || 0
                };

                if (type === 'textbooks') {
                    item.publisher = $row.find('input[name$="[publisher]"]').val();
                }

                data.push(item);
            });

            return data;
        },

        collectPeriodData: function($panel) {
            const data = [];

            $panel.find('.bmgf-data-table tbody tr').each(function() {
                const $row = $(this);
                data.push({
                    period: $row.find('input[name$="[period]"]').val(),
                    calc1: parseInt($row.find('input[name$="[calc1]"]').val()) || 0,
                    calc2: parseInt($row.find('input[name$="[calc2]"]').val()) || 0
                });
            });

            return data;
        },

        collectSizeData: function($panel) {
            const data = [];

            $panel.find('.bmgf-data-table tbody tr').each(function() {
                const $row = $(this);
                data.push({
                    size: $row.find('input[name$="[size]"]').val(),
                    calc1: parseInt($row.find('input[name$="[calc1]"]').val()) || 0,
                    calc2: parseInt($row.find('input[name$="[calc2]"]').val()) || 0
                });
            });

            return data;
        },

        collectFiltersData: function($panel) {
            const data = {};

            $panel.find('.bmgf-filter-list').each(function() {
                const $list = $(this);
                const filterKey = $list.data('filter');
                data[filterKey] = [];

                $list.find('input').each(function() {
                    const val = $(this).val().trim();
                    if (val) {
                        data[filterKey].push(val);
                    }
                });
            });

            return data;
        },

        populateAllForms: function() {
            this.populateBranding();
            this.populateKPIs();
            this.populateRegionalData();
            this.populateSectorData();
            this.populatePublishers();
            this.populateTopInstitutions();
            this.populateTopTextbooks();
            this.populatePeriodData();
            this.populateSizeData();
            this.populateFilters();
        },

        populateBranding: function() {
            const branding = this.data.branding || {};
            // Select elements explicitly inside the branding form
            const $form = $('form[data-section="branding"]');
            
            Object.keys(branding).forEach(key => {
                const $input = $form.find(`[name="${key}"]`);
                if ($input.length) {
                    let val = branding[key];
                    
                    // Fallback para evitar el error de consola en colores
                    if ($input.attr('type') === 'color' && !/^#[0-9A-F]{6}$/i.test(val)) {
                        val = '#008384';
                    }
                    
                    $input.val(val);
                    
                    if ($input.attr('type') === 'color') {
                        $input.trigger('input');
                    }
                }
            });
        },

        populateKPIs: function() {
            const kpis = this.data.kpis || {};
            Object.keys(kpis).forEach(key => {
                $(`input[name="kpis[${key}]"]`).val(kpis[key]);
            });
        },

        populateRegionalData: function() {
            const regional = this.data.regional_data || {};

            ['calc1', 'calc2'].forEach(calc => {
                const $tbody = $(`#regional-${calc} .bmgf-data-table tbody`);
                $tbody.empty();

                const items = regional[calc] || [];
                items.forEach((item, i) => {
                    $tbody.append(this.createRegionalRow(calc, i, item));
                });
            });
        },

        createRegionalRow: function(calc, index, item) {
            return `
                <tr>
                    <td class="row-number">${index + 1}</td>
                    <td><input type="text" name="regional_data[${calc}][${index}][name]" value="${this.escapeHtml(item.name)}"></td>
                    <td><input type="number" name="regional_data[${calc}][${index}][percentage]" value="${item.percentage}" min="0" max="100"></td>
                    <td><input type="number" name="regional_data[${calc}][${index}][value]" value="${item.value}" min="0"></td>
                </tr>
            `;
        },

        populateSectorData: function() {
            const sectors = this.data.sector_data || {};

            ['calc1', 'calc2'].forEach(calc => {
                const $tbody = $(`#sector-${calc} .bmgf-data-table tbody`);
                $tbody.empty();

                const items = sectors[calc] || [];
                items.forEach((item, i) => {
                    $tbody.append(this.createSectorRow(calc, i, item));
                });
            });
        },

        createSectorRow: function(calc, index, item) {
            return `
                <tr>
                    <td class="row-number">${index + 1}</td>
                    <td><input type="text" name="sector_data[${calc}][${index}][name]" value="${this.escapeHtml(item.name)}"></td>
                    <td><input type="number" name="sector_data[${calc}][${index}][percentage]" value="${item.percentage}" min="0" max="100"></td>
                    <td><input type="number" name="sector_data[${calc}][${index}][value]" value="${item.value}" min="0"></td>
                </tr>
            `;
        },

        populatePublishers: function() {
            const publishers = this.data.publishers || [];
            const $tbody = $('#publishers .bmgf-data-table tbody');
            $tbody.empty();

            publishers.forEach((item, i) => {
                $tbody.append(this.createPublisherRow(i, item));
            });
        },

        createPublisherRow: function(index, item) {
            return `
                <tr>
                    <td class="row-number">${index + 1}</td>
                    <td><input type="text" name="publishers[${index}][name]" value="${this.escapeHtml(item.name)}"></td>
                    <td><input type="number" name="publishers[${index}][market_share]" value="${item.market_share}" min="0" max="100"></td>
                    <td><input type="number" name="publishers[${index}][enrollment]" value="${item.enrollment}" min="0"></td>
                    <td><input type="number" name="publishers[${index}][avg_price]" value="${item.avg_price}" min="0" step="0.01"></td>
                    <td class="bmgf-color-cell">
                        <span class="bmgf-color-preview" style="background-color: ${item.color}"></span>
                        <input type="color" name="publishers[${index}][color]" value="${item.color}">
                    </td>
                </tr>
            `;
        },

        populateTopInstitutions: function() {
            const institutions = this.data.top_institutions || [];
            const $tbody = $('#top_institutions .bmgf-data-table tbody');
            $tbody.empty();

            institutions.forEach((item, i) => {
                $tbody.append(`
                    <tr>
                        <td class="row-number">${i + 1}</td>
                        <td><input type="text" name="top_institutions[${i}][name]" value="${this.escapeHtml(item.name)}"></td>
                        <td><input type="number" name="top_institutions[${i}][enrollment]" value="${item.enrollment}" min="0"></td>
                    </tr>
                `);
            });
        },

        populateTopTextbooks: function() {
            const textbooks = this.data.top_textbooks || [];
            const $tbody = $('#top_textbooks .bmgf-data-table tbody');
            $tbody.empty();

            textbooks.forEach((item, i) => {
                $tbody.append(`
                    <tr>
                        <td class="row-number">${i + 1}</td>
                        <td><input type="text" name="top_textbooks[${i}][name]" value="${this.escapeHtml(item.name)}"></td>
                        <td><input type="text" name="top_textbooks[${i}][publisher]" value="${this.escapeHtml(item.publisher)}"></td>
                        <td><input type="number" name="top_textbooks[${i}][enrollment]" value="${item.enrollment}" min="0"></td>
                    </tr>
                `);
            });
        },

        populatePeriodData: function() {
            const periods = this.data.period_data || [];
            const $tbody = $('#period_data .bmgf-data-table tbody');
            $tbody.empty();

            periods.forEach((item, i) => {
                $tbody.append(`
                    <tr>
                        <td class="row-number">${i + 1}</td>
                        <td><input type="text" name="period_data[${i}][period]" value="${this.escapeHtml(item.period)}"></td>
                        <td><input type="number" name="period_data[${i}][calc1]" value="${item.calc1}" min="0"></td>
                        <td><input type="number" name="period_data[${i}][calc2]" value="${item.calc2}" min="0"></td>
                    </tr>
                `);
            });
        },

        populateSizeData: function() {
            const sizes = this.data.institution_size_data || [];
            const $tbody = $('#institution_size_data .bmgf-data-table tbody');
            $tbody.empty();

            sizes.forEach((item, i) => {
                $tbody.append(`
                    <tr>
                        <td class="row-number">${i + 1}</td>
                        <td><input type="text" name="institution_size_data[${i}][size]" value="${this.escapeHtml(item.size)}"></td>
                        <td><input type="number" name="institution_size_data[${i}][calc1]" value="${item.calc1}" min="0"></td>
                        <td><input type="number" name="institution_size_data[${i}][calc2]" value="${item.calc2}" min="0"></td>
                    </tr>
                `);
            });
        },

        populateFilters: function() {
            const filters = this.data.filters || {};

            Object.keys(filters).forEach(key => {
                const $list = $(`.bmgf-filter-list[data-filter="${key}"]`);
                $list.empty();

                const items = filters[key] || [];
                items.forEach(item => {
                    $list.append(`
                        <div class="bmgf-filter-item">
                            <input type="text" name="filters[${key}][]" value="${this.escapeHtml(item)}">
                            <span class="bmgf-filter-remove dashicons dashicons-no-alt"></span>
                        </div>
                    `);
                });
            });
        },

        showNotice: function(type, message) {
            const $notice = $(`
                <div class="bmgf-notice bmgf-notice-${type}">
                    <span class="bmgf-notice-icon dashicons dashicons-${type === 'success' ? 'yes-alt' : 'warning'}"></span>
                    <span>${message}</span>
                </div>
            `);

            $('.bmgf-admin-header').after($notice);

            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        BMGFAdmin.init();
    });

})(jQuery);