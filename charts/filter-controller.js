/**
 * Filter Controller for BMGF Calculus Dashboard
 * Manages filter state, events, and updates KPIs/charts based on selections
 */

(function() {
    'use strict';

    // Filter state - stored in localStorage for persistence across pages
    const STORAGE_KEY = 'bmgf_dashboard_filters';

    // Default filter state
    const defaultFilters = {
        course: 'All',
        period: 'All',
        state: 'All',
        sector: 'All',
        region: 'All',
        publisher: 'All',
        msiType: 'All',
        priceRange: 'All'
    };

    // Load filters from localStorage or use defaults
    function loadFilters() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                return { ...defaultFilters, ...JSON.parse(stored) };
            }
        } catch (e) {
            console.warn('Could not load filters from localStorage:', e);
        }
        return { ...defaultFilters };
    }

    // Save filters to localStorage
    function saveFilters(filters) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
        } catch (e) {
            console.warn('Could not save filters to localStorage:', e);
        }
    }

    // Current filter state
    let currentFilters = loadFilters();
    const fixedGlobalTotals = {
        fte: null
    };

    function hasActiveFilters() {
        return Object.values(currentFilters).some(value => value !== 'All');
    }

    function getBaseDashboardKpis() {
        if (!window.BMGF_DATA || !window.BMGF_DATA.kpis) {
            return null;
        }
        return window.BMGF_DATA.kpis;
    }

    function parseDisplayedNumber(value) {
        if (value === undefined || value === null) return 0;
        const cleaned = String(value).replace(/[^0-9.-]/g, '');
        if (cleaned === '') return 0;
        const parsed = Number(cleaned);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function getFixedFteTotal() {
        if (fixedGlobalTotals.fte !== null) {
            return fixedGlobalTotals.fte;
        }

        const stateSource = (typeof stateData !== 'undefined' && Array.isArray(stateData))
            ? stateData
            : (window.BMGF_DATA && Array.isArray(window.BMGF_DATA.state_data) ? window.BMGF_DATA.state_data : []);
        if (stateSource.length > 0) {
            fixedGlobalTotals.fte = stateSource.reduce((sum, item) => sum + (Number(item.fte) || 0), 0);
            return fixedGlobalTotals.fte;
        }

        const baseKpis = getBaseDashboardKpis();
        if (baseKpis && baseKpis.total_fte_enrollment !== undefined && baseKpis.total_fte_enrollment !== null) {
            fixedGlobalTotals.fte = Number(baseKpis.total_fte_enrollment) || 0;
            return fixedGlobalTotals.fte;
        }

        const fteEl = document.getElementById('kpi-fte');
        fixedGlobalTotals.fte = fteEl ? parseDisplayedNumber(fteEl.textContent) : 0;
        return fixedGlobalTotals.fte;
    }

    // Complete state/jurisdiction dictionary used by the "State" filter.
    const STATE_CODE_MAP = Object.freeze({
        'Alabama': 'AL',
        'Alaska': 'AK',
        'Arizona': 'AZ',
        'Arkansas': 'AR',
        'California': 'CA',
        'Colorado': 'CO',
        'Connecticut': 'CT',
        'Delaware': 'DE',
        'District of Columbia': 'DC',
        'Florida': 'FL',
        'Georgia': 'GA',
        'Guam': 'GU',
        'Hawaii': 'HI',
        'Idaho': 'ID',
        'Illinois': 'IL',
        'Indiana': 'IN',
        'Iowa': 'IA',
        'Kansas': 'KS',
        'Kentucky': 'KY',
        'Louisiana': 'LA',
        'Maine': 'ME',
        'Maryland': 'MD',
        'Massachusetts': 'MA',
        'Michigan': 'MI',
        'Minnesota': 'MN',
        'Mississippi': 'MS',
        'Missouri': 'MO',
        'Montana': 'MT',
        'Nebraska': 'NE',
        'Nevada': 'NV',
        'New Hampshire': 'NH',
        'New Jersey': 'NJ',
        'New Mexico': 'NM',
        'New York': 'NY',
        'North Carolina': 'NC',
        'North Dakota': 'ND',
        'Ohio': 'OH',
        'Oklahoma': 'OK',
        'Oregon': 'OR',
        'Pennsylvania': 'PA',
        'Puerto Rico': 'PR',
        'Rhode Island': 'RI',
        'South Carolina': 'SC',
        'South Dakota': 'SD',
        'Tennessee': 'TN',
        'Texas': 'TX',
        'Utah': 'UT',
        'Vermont': 'VT',
        'Virginia': 'VA',
        'Washington': 'WA',
        'West Virginia': 'WV',
        'Wisconsin': 'WI',
        'Wyoming': 'WY'
    });

    const STATE_NAME_BY_CODE = Object.freeze(
        Object.fromEntries(Object.entries(STATE_CODE_MAP).map(([name, code]) => [code, name]))
    );

    // Expose dictionaries for cross-chart reuse.
    window.BMGF_STATE_CODE_MAP = STATE_CODE_MAP;
    window.BMGF_STATE_NAME_BY_CODE = STATE_NAME_BY_CODE;

    // Region mapping for states
    const stateToRegion = {
        'Florida': 'Southeast', 'Georgia': 'Southeast', 'Alabama': 'Southeast',
        'Mississippi': 'Southeast', 'Tennessee': 'Southeast', 'South Carolina': 'Southeast',
        'North Carolina': 'Southeast', 'Kentucky': 'Southeast', 'Virginia': 'Southeast',
        'West Virginia': 'Southeast', 'Louisiana': 'Southeast', 'Arkansas': 'Southeast',
        'California': 'Far West', 'Oregon': 'Far West', 'Washington': 'Far West',
        'Nevada': 'Far West', 'Alaska': 'Far West', 'Hawaii': 'Far West',
        'New York': 'Mid East', 'New Jersey': 'Mid East', 'Pennsylvania': 'Mid East',
        'Delaware': 'Mid East', 'Maryland': 'Mid East', 'District of Columbia': 'Mid East',
        'Michigan': 'Great Lakes', 'Ohio': 'Great Lakes', 'Indiana': 'Great Lakes',
        'Illinois': 'Great Lakes', 'Wisconsin': 'Great Lakes',
        'Texas': 'Southwest', 'Arizona': 'Southwest', 'New Mexico': 'Southwest', 'Oklahoma': 'Southwest',
        'Colorado': 'Rocky Mountains', 'Utah': 'Rocky Mountains', 'Wyoming': 'Rocky Mountains',
        'Montana': 'Rocky Mountains', 'Idaho': 'Rocky Mountains',
        'Minnesota': 'Plains', 'Iowa': 'Plains', 'Missouri': 'Plains', 'Kansas': 'Plains',
        'Nebraska': 'Plains', 'South Dakota': 'Plains', 'North Dakota': 'Plains',
        'Massachusetts': 'New England', 'Connecticut': 'New England', 'Rhode Island': 'New England',
        'Maine': 'New England', 'New Hampshire': 'New England', 'Vermont': 'New England',
        'Puerto Rico': 'Outlying', 'Guam': 'Outlying'
    };

    function getDashboardFilters() {
        return (window.BMGF_DATA && window.BMGF_DATA.filters && typeof window.BMGF_DATA.filters === 'object')
            ? window.BMGF_DATA.filters
            : {};
    }

    function uniqueCleanStrings(values) {
        const set = new Set();
        (Array.isArray(values) ? values : []).forEach(value => {
            if (value === undefined || value === null) return;
            const clean = String(value).trim();
            if (!clean || clean.toLowerCase() === 'all') return;
            set.add(clean);
        });
        return Array.from(set);
    }

    function normalizeCourseValue(value) {
        const raw = String(value || '').trim().toLowerCase();
        if (!raw) return '';
        if (raw === 'calc i' || raw === 'calculus i' || raw === 'calc_i') return 'Calc I';
        if (raw === 'calc ii' || raw === 'calculus ii' || raw === 'calc_ii') return 'Calc II';
        if (raw.includes('calc') && raw.includes('ii') && !raw.includes('iii')) return 'Calc II';
        if (raw.includes('calc') && raw.includes('i')) return 'Calc I';
        return '';
    }

    function normalizeRegionValue(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const base = raw.replace(/\s*\(.*\)\s*$/, '');
        return base;
    }

    function getOptionsForFilter(filterName) {
        const dashboardFilters = getDashboardFilters();

        if (filterName === 'state') {
            return Object.keys(STATE_CODE_MAP)
                .sort((a, b) => a.localeCompare(b))
                .map(stateName => ({
                    value: stateName,
                    label: stateName + ' (' + STATE_CODE_MAP[stateName] + ')'
                }));
        }

        if (filterName === 'course') {
            const normalized = uniqueCleanStrings(dashboardFilters.courses)
                .map(normalizeCourseValue)
                .filter(Boolean);
            const uniqueCourses = Array.from(new Set(normalized));
            const ordered = ['Calc I', 'Calc II'].filter(course => uniqueCourses.includes(course));
            return (ordered.length > 0 ? ordered : ['Calc I', 'Calc II']).map(course => ({ value: course, label: course }));
        }

        if (filterName === 'sector') {
            return uniqueCleanStrings(dashboardFilters.sectors)
                .sort((a, b) => a.localeCompare(b))
                .map(sector => ({ value: sector, label: sector }));
        }

        if (filterName === 'region') {
            const regions = uniqueCleanStrings(dashboardFilters.regions)
                .map(normalizeRegionValue)
                .filter(Boolean);
            return Array.from(new Set(regions))
                .sort((a, b) => a.localeCompare(b))
                .map(region => ({ value: region, label: region }));
        }

        if (filterName === 'publisher') {
            return uniqueCleanStrings(dashboardFilters.publishers)
                .sort((a, b) => a.localeCompare(b))
                .map(publisher => ({ value: publisher, label: publisher }));
        }

        if (filterName === 'msiType') {
            return uniqueCleanStrings(dashboardFilters.msi_types || dashboardFilters.msiTypes)
                .sort((a, b) => a.localeCompare(b))
                .map(msi => ({ value: msi, label: msi }));
        }

        return [];
    }

    function normalizeSavedFilterValue(filterName, savedValue) {
        if (savedValue === undefined || savedValue === null) return 'All';
        const text = String(savedValue).trim();
        if (!text) return 'All';

        if (filterName === 'state') {
            const upper = text.toUpperCase();
            if (STATE_NAME_BY_CODE[upper]) {
                return STATE_NAME_BY_CODE[upper];
            }
        }

        if (filterName === 'course') {
            return normalizeCourseValue(text) || 'All';
        }

        if (filterName === 'region') {
            return normalizeRegionValue(text) || 'All';
        }

        return text;
    }

    function setSelectOptions(select, filterName, options) {
        const selected = normalizeSavedFilterValue(filterName, currentFilters[filterName]);

        select.innerHTML = '';
        select.add(new Option('All', 'All'));
        options.forEach(option => {
            select.add(new Option(option.label, option.value));
        });

        const validValues = new Set(['All', ...options.map(option => option.value)]);
        const value = validValues.has(selected) ? selected : 'All';
        select.value = value;
        currentFilters[filterName] = value;
    }

    function populateDynamicFilterOptions() {
        const filterGroups = document.querySelectorAll('.filter-group');
        filterGroups.forEach(group => {
            const label = group.querySelector('.filter-label');
            const select = group.querySelector('.filter-select');
            if (!label || !select) return;

            const filterName = getFilterNameFromLabel(label.textContent.trim());
            if (!filterName) return;

            const options = getOptionsForFilter(filterName);
            if (options.length > 0) {
                setSelectOptions(select, filterName, options);
            } else {
                const normalized = normalizeSavedFilterValue(filterName, currentFilters[filterName]);
                currentFilters[filterName] = normalized;
                if (normalized && normalized !== 'All') {
                    select.value = normalized;
                }
            }
        });
    }

    function getInstitutionSelection() {
        const institutionList = document.querySelector('.institution-list');
        if (!institutionList) {
            return { hasList: false, allSelected: true, selected: [] };
        }

        const allCheckbox = institutionList.querySelector('.institution-item:first-child input[type="checkbox"]');
        const labels = institutionList.querySelectorAll('.institution-item:not(:first-child)');
        const selected = [];

        labels.forEach(label => {
            const input = label.querySelector('input[type="checkbox"]');
            if (!input) return;
            if (input.checked) {
                const name = (label.textContent || '').trim();
                if (name) selected.push(name);
            }
        });

        const totalItems = labels.length;
        const allSelected = allCheckbox ? allCheckbox.checked : (totalItems > 0 && selected.length === totalItems);

        return {
            hasList: true,
            allSelected: allSelected || (totalItems > 0 && selected.length === totalItems),
            selected
        };
    }

    // Get filtered data from stateData
    function getFilteredData() {
        const sourceData = (typeof stateData !== 'undefined' && Array.isArray(stateData))
            ? stateData
            : (window.BMGF_DATA && Array.isArray(window.BMGF_DATA.state_data) ? window.BMGF_DATA.state_data : []);

        if (!sourceData || sourceData.length === 0) {
            console.warn('stateData not available');
            return [];
        }

        const filtered = sourceData.filter(item => {
            // Filter by state
            if (currentFilters.state !== 'All' && item.state !== currentFilters.state) {
                return false;
            }

            return true;
        });

        let withPeriod = filtered;

        // Institution checklist projection over state-level institution breakdown sourced from All_Institutions.School.
        const institutionSelection = getInstitutionSelection();
        if (institutionSelection.hasList && !institutionSelection.allSelected) {
            if (institutionSelection.selected.length === 0) {
                return [];
            }

            const selectedNames = new Set(institutionSelection.selected);
            withPeriod = withPeriod
                .map(item => {
                    const breakdown = item.institution_breakdown || {};
                    let calc_i = 0;
                    let calc_ii = 0;
                    let total = 0;
                    let fte = 0;
                    let institutions = 0;

                    Object.keys(breakdown).forEach(name => {
                        if (!selectedNames.has(name)) return;
                        const row = breakdown[name] || {};
                        calc_i += Number(row.calc_i) || 0;
                        calc_ii += Number(row.calc_ii) || 0;
                        total += Number(row.total) || 0;
                        fte += Number(row.fte) || 0;
                        institutions += 1;
                    });

                    if (institutions === 0) return null;
                    return {
                        ...item,
                        calc_i,
                        calc_ii,
                        total,
                        fte,
                        institutions
                    };
                })
                .filter(Boolean);
        }

        // Region filter projection over state-level region breakdown sourced from All_Institutions.Region.
        if (currentFilters.region && currentFilters.region !== 'All') {
            const projectedByRegion = withPeriod
                .map(item => {
                    const breakdown = item.region_breakdown || {};
                    const regionData = breakdown[currentFilters.region];
                    if (!regionData) return null;
                    return {
                        ...item,
                        total: Number(regionData.total) || 0,
                        calc_i: Number(regionData.calc_i) || 0,
                        calc_ii: Number(regionData.calc_ii) || 0,
                        fte: Number(regionData.fte) || 0,
                        institutions: Number(regionData.institutions) || 0
                    };
                })
                .filter(item => item && item.total > 0);

            // Fallback to full data when region breakdown is not available in the current dataset.
            withPeriod = projectedByRegion.length > 0 ? projectedByRegion : withPeriod;
        }

        // Publisher filter projection over state-level publisher breakdown sourced from All_Institutions.Publisher.
        if (currentFilters.publisher && currentFilters.publisher !== 'All') {
            const projectedByPublisher = withPeriod
                .map(item => {
                    const breakdown = item.publisher_breakdown || {};
                    const publisherData = breakdown[currentFilters.publisher];
                    if (!publisherData) return null;
                    return {
                        ...item,
                        total: Number(publisherData.total) || 0,
                        calc_i: Number(publisherData.calc_i) || 0,
                        calc_ii: Number(publisherData.calc_ii) || 0,
                        fte: Number(publisherData.fte) || 0,
                        institutions: Number(publisherData.institutions) || 0
                    };
                })
                .filter(item => item && item.total > 0);

            // Fallback to full data when publisher breakdown is not available in the current dataset.
            withPeriod = projectedByPublisher.length > 0 ? projectedByPublisher : withPeriod;
        }

        // Sector filter projection over state-level sector breakdown sourced from All_Institutions.Sector.
        if (currentFilters.sector && currentFilters.sector !== 'All') {
            const projectedBySector = withPeriod
                .map(item => {
                    const breakdown = item.sector_breakdown || {};
                    const sectorData = breakdown[currentFilters.sector];
                    if (!sectorData) return null;
                    return {
                        ...item,
                        total: Number(sectorData.total) || 0,
                        calc_i: Number(sectorData.calc_i) || 0,
                        calc_ii: Number(sectorData.calc_ii) || 0,
                        fte: Number(sectorData.fte) || 0,
                        institutions: Number(sectorData.institutions) || 0
                    };
                })
                .filter(item => item && item.total > 0);

            // Fallback to full data when sector breakdown is not available in the current dataset.
            withPeriod = projectedBySector.length > 0 ? projectedBySector : withPeriod;
        }

        // MSI Type filter projection over state-level MSI breakdown sourced from All_Institutions.MSI Type.
        if (currentFilters.msiType && currentFilters.msiType !== 'All') {
            const projectedByMsi = withPeriod
                .map(item => {
                    const breakdown = item.msi_breakdown || {};
                    const msiData = breakdown[currentFilters.msiType];
                    if (!msiData) return null;
                    return {
                        ...item,
                        total: Number(msiData.total) || 0,
                        calc_i: Number(msiData.calc_i) || 0,
                        calc_ii: Number(msiData.calc_ii) || 0,
                        fte: Number(msiData.fte) || 0,
                        institutions: Number(msiData.institutions) || 0
                    };
                })
                .filter(item => item && item.total > 0);

            // Fallback to full data when MSI breakdown is not available in the current dataset.
            withPeriod = projectedByMsi.length > 0 ? projectedByMsi : withPeriod;
        }

        // Period filter projection over state-level period breakdown sourced from All_Courses.Period.
        if (currentFilters.period && currentFilters.period !== 'All') {
            const projected = withPeriod
                .map(item => {
                    const breakdown = item.period_breakdown || {};
                    const periodData = breakdown[currentFilters.period];
                    if (!periodData) return null;
                    return {
                        ...item,
                        total: Number(periodData.total) || 0,
                        calc_i: Number(periodData.calc_i) || 0,
                        calc_ii: Number(periodData.calc_ii) || 0,
                        courses: Number(periodData.courses) || 0
                    };
                })
                .filter(item => item && item.total > 0);

            // Fallback to current composed data when period breakdown is not available.
            withPeriod = projected.length > 0 ? projected : withPeriod;
        }

        // Course filter projection over aggregated state data.
        // We cannot split institution count by course here, but we can align enrollment metrics.
        if (currentFilters.course === 'Calc I') {
            return withPeriod.map(item => ({
                ...item,
                total: item.calc_i || 0,
                calc_ii: 0
            }));
        }

        if (currentFilters.course === 'Calc II') {
            return withPeriod.map(item => ({
                ...item,
                total: item.calc_ii || 0,
                calc_i: 0
            }));
        }

        return withPeriod;
    }

    // Calculate KPIs from filtered data
    function calculateKPIs(filteredData) {
        const usingDashboardBaseKpis = !hasActiveFilters() &&
            window.BMGF_DATA &&
            window.BMGF_DATA.kpis;

        if (usingDashboardBaseKpis) {
            const k = window.BMGF_DATA.kpis;
            const totalEnrollment = Number(k.total_enrollment) || 0;
            return {
                totalInstitutions: Number(k.total_institutions) || 0,
                totalEnrollment: totalEnrollment,
                totalCalcI: Number(k.calc1_enrollment) || 0,
                totalCalcII: Number(k.calc2_enrollment) || 0,
                totalFTE: Number(k.total_fte_enrollment) || 0
            };
        }

        if (!filteredData || filteredData.length === 0) {
            return {
                totalInstitutions: 0,
                totalEnrollment: 0,
                totalCalcI: 0,
                totalCalcII: 0,
                totalFTE: 0
            };
        }

        const totals = filteredData.reduce((acc, item) => {
            acc.institutions += item.institutions || 0;
            acc.calcI += item.calc_i || 0;
            acc.calcII += item.calc_ii || 0;
            acc.total += item.total || 0;
            acc.fte += item.fte || 0;
            return acc;
        }, { institutions: 0, calcI: 0, calcII: 0, total: 0, fte: 0 });

        return {
            totalInstitutions: totals.institutions,
            totalEnrollment: totals.total,
            totalCalcI: totals.calcI,
            totalCalcII: totals.calcII,
            totalFTE: totals.fte
        };
    }

    // Format number for display
    function formatNumber(num) {
        return Number(num || 0).toLocaleString();
    }

    function getStateNameToCodeMap() {
        const map = {};
        const rows = (window.BMGF_DATA && Array.isArray(window.BMGF_DATA.state_data))
            ? window.BMGF_DATA.state_data
            : [];

        rows.forEach(item => {
            if (!item || !item.state || !item.code) return;
            map[item.state] = String(item.code).toUpperCase();
        });
        return map;
    }

    function calculateRegionsCovered(filteredData) {
        const regionCoverage = (window.BMGF_DATA && Array.isArray(window.BMGF_DATA.regionCoverage))
            ? window.BMGF_DATA.regionCoverage
            : [];

        if (!Array.isArray(regionCoverage) || regionCoverage.length === 0) {
            const uniqueRegions = new Set((filteredData || []).map(item => stateToRegion[item.state]).filter(Boolean));
            return uniqueRegions.size;
        }

        const stateNameToCode = getStateNameToCodeMap();
        const selectedStateCodes = new Set();
        const rows = Array.isArray(filteredData) ? filteredData : [];

        rows.forEach(item => {
            if (!item) return;
            const total = Number(item.total || 0);
            if (total <= 0) return;

            const code = item.code
                ? String(item.code).toUpperCase()
                : (item.state && stateNameToCode[item.state] ? stateNameToCode[item.state] : '');

            if (code) {
                selectedStateCodes.add(code);
            }
        });

        if (selectedStateCodes.size === 0) {
            return 0;
        }

        let covered = 0;
        regionCoverage.forEach(region => {
            const codes = Array.isArray(region && region.states) ? region.states : [];
            const isCovered = codes.some(code => selectedStateCodes.has(String(code).toUpperCase()));
            if (isCovered) {
                covered += 1;
            }
        });

        return covered;
    }

    function enforceInstitutionAllVisualState() {
        const institutionList = document.querySelector('.institution-list');
        if (!institutionList) return;

        const allCheckbox = institutionList.querySelector('.institution-item:first-child input[type="checkbox"]');
        const otherCheckboxes = institutionList.querySelectorAll('.institution-item:not(:first-child) input[type="checkbox"]');
        if (!allCheckbox || otherCheckboxes.length === 0) return;

        if (allCheckbox.checked) {
            otherCheckboxes.forEach(cb => {
                cb.checked = true;
            });
        } else {
            const allChecked = Array.from(otherCheckboxes).every(cb => cb.checked);
            if (allChecked) {
                allCheckbox.checked = true;
            }
        }
    }

    // Update KPI display elements
    function updateKPIDisplay(kpis) {
        const baseKpis = getBaseDashboardKpis();
        const useBase = !hasActiveFilters() && baseKpis;

        // Total Institutions is a global KPI and must stay fixed (not filter-dependent).
        const instEl = document.getElementById('kpi-institutions');
        if (instEl) {
            const hasBaseInstitutions = baseKpis && baseKpis.total_institutions !== undefined && baseKpis.total_institutions !== null;
            const value = hasBaseInstitutions ? baseKpis.total_institutions : kpis.totalInstitutions;
            instEl.textContent = formatNumber(value);
        }

        // Total Calculus Enrollment is a global KPI and must stay fixed (not filter-dependent).
        const calcEl = document.getElementById('kpi-calculus');
        if (calcEl) {
            const hasBaseTotal = baseKpis && baseKpis.total_enrollment !== undefined && baseKpis.total_enrollment !== null;
            const value = hasBaseTotal ? baseKpis.total_enrollment : kpis.totalEnrollment;
            calcEl.textContent = formatNumber(value);
        }

        // Total FTE Enrollment is a global KPI and must stay fixed (not filter-dependent).
        const fteEl = document.getElementById('kpi-fte');
        if (fteEl) {
            const value = getFixedFteTotal();
            fteEl.textContent = formatNumber(value);
        }

        // Update regions (count unique regions in filtered data)
        const regionsEl = document.getElementById('kpi-regions');
        if (regionsEl) {
            const filteredData = getFilteredData();
            regionsEl.textContent = calculateRegionsCovered(filteredData);
        }
    }

    // Notify iframes about filter changes
    function notifyIframes() {
        const filteredData = getFilteredData();
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(iframe => {
            try {
                iframe.contentWindow.postMessage({
                    type: 'BMGF_FILTER_UPDATE',
                    filters: currentFilters,
                    filteredData: filteredData
                }, '*');
            } catch (e) {
                console.warn('Could not post message to iframe:', e);
            }
        });
    }

    // Apply filters and update UI
    function applyFilters() {
        enforceInstitutionAllVisualState();

        const filteredData = getFilteredData();
        const kpis = calculateKPIs(filteredData);

        updateKPIDisplay(kpis);
        saveFilters(currentFilters);
        notifyIframes();

        // Dispatch custom event for other components
        window.dispatchEvent(new CustomEvent('bmgf:filtersChanged', {
            detail: { filters: currentFilters, data: filteredData, kpis: kpis }
        }));
    }

    // Get filter name from label text
    function getFilterNameFromLabel(labelText) {
        const mapping = {
            'course name': 'course',
            'course': 'course',
            'state': 'state',
            'sector': 'sector',
            'region': 'region',
            'publisher': 'publisher',
            'msi type': 'msiType',
            'price range': 'priceRange'
        };
        return mapping[labelText.toLowerCase()] || null;
    }

    // Initialize filter select elements
    function initializeFilters() {
        populateDynamicFilterOptions();

        const filterGroups = document.querySelectorAll('.filter-group');

        filterGroups.forEach(group => {
            const label = group.querySelector('.filter-label');
            const select = group.querySelector('.filter-select');

            if (!label || !select) return;

            const filterName = getFilterNameFromLabel(label.textContent.trim());
            if (!filterName) return;

            // Set initial value from saved state
            if (currentFilters[filterName] && currentFilters[filterName] !== 'All') {
                const options = select.querySelectorAll('option');
                options.forEach(opt => {
                    if (opt.textContent === currentFilters[filterName] || opt.value === currentFilters[filterName]) {
                        select.value = opt.value;
                    }
                });
            }

            // Add change listener
            select.addEventListener('change', function() {
                currentFilters[filterName] = this.value;
                applyFilters();

                // Update select styling based on selection
                if (this.value !== 'All') {
                    this.classList.add('active');
                } else {
                    this.classList.remove('active');
                }
            });

            // Set initial styling
            if (select.value !== 'All') {
                select.classList.add('active');
            }
        });
    }

    // Initialize Clear All button
    function initializeClearButton() {
        const clearBtn = document.querySelector('.btn-clear');
        if (!clearBtn) return;

        clearBtn.addEventListener('click', function() {
            // Reset all filters to defaults
            currentFilters = { ...defaultFilters };

            // Reset all select elements
            const selects = document.querySelectorAll('.filter-select');
            selects.forEach(select => {
                select.value = 'All';
                select.classList.remove('active');
            });

            // Reset institution checklist to "(All)" selected.
            const institutionList = document.querySelector('.institution-list');
            if (institutionList) {
                const allCheckbox = institutionList.querySelector('.institution-item:first-child input[type="checkbox"]');
                const otherCheckboxes = institutionList.querySelectorAll('.institution-item:not(:first-child) input[type="checkbox"]');
                otherCheckboxes.forEach(cb => { cb.checked = true; });
                if (allCheckbox) {
                    allCheckbox.checked = true;
                }
            }

            // Reset period buttons: none selected by default.
            const periodButtons = document.querySelectorAll('.btn-period');
            currentFilters.period = 'All';
            periodButtons.forEach((btn) => {
                btn.classList.remove('active');
                btn.classList.add('inactive');
            });

            applyFilters();

            // Add visual feedback for clear action
            clearBtn.style.transform = 'scale(0.95)';
            setTimeout(() => {
                clearBtn.style.transform = '';
            }, 150);
        });
    }

    // Initialize period buttons (if present)
    function initializePeriodButtons() {
        const periodButtons = document.querySelectorAll('.btn-period');
        if (!periodButtons || periodButtons.length === 0) return;

        function getPeriodValueFromButton(btn) {
            const attr = (btn.getAttribute('data-period') || '').trim();
            if (attr) return attr;

            const text = (btn.textContent || '').toLowerCase();
            if (text.includes('fall')) return 'Fall 2025';
            if (text.includes('spring')) return 'Spring 2025';
            if (text.includes('winter')) return 'Winter 2025';
            if (text.includes('summer')) return 'Summer 2025';
            return '';
        }

        periodButtons.forEach(btn => {
            const periodValue = getPeriodValueFromButton(btn);
            if (!periodValue) return;

            if (periodValue === currentFilters.period) {
                btn.classList.add('active');
                btn.classList.remove('inactive');
            } else {
                btn.classList.remove('active');
                btn.classList.add('inactive');
            }

            btn.addEventListener('click', function() {
                const wasActive = this.classList.contains('active');

                // Remove active from all buttons
                periodButtons.forEach(b => {
                    b.classList.remove('active');
                    b.classList.add('inactive');
                });

                if (wasActive) {
                    // Toggle off when clicking an already-active period button.
                    currentFilters.period = 'All';
                } else {
                    // Add active to clicked button
                    this.classList.remove('inactive');
                    this.classList.add('active');
                    currentFilters.period = periodValue;
                }

                applyFilters();

                // Dispatch event for period change
                window.dispatchEvent(new CustomEvent('bmgf:periodChanged', {
                    detail: { period: currentFilters.period }
                }));
            });
        });
    }

    // Initialize institution checkboxes (if present)
    function initializeInstitutionList() {
        const institutionList = document.querySelector('.institution-list');
        if (!institutionList) return;

        const dashboardFilters = getDashboardFilters();
        const institutions = uniqueCleanStrings(dashboardFilters.institutions)
            .filter(name => name.toLowerCase() !== '(blank)')
            .sort((a, b) => a.localeCompare(b));

        if (institutions.length > 0) {
            institutionList.innerHTML = '';
            const allLabel = document.createElement('label');
            allLabel.className = 'institution-item';
            const allInput = document.createElement('input');
            allInput.type = 'checkbox';
            allInput.checked = true;
            allLabel.appendChild(allInput);
            allLabel.appendChild(document.createTextNode(' (All)'));
            institutionList.appendChild(allLabel);

            institutions.forEach(name => {
                const label = document.createElement('label');
                label.className = 'institution-item';
                const input = document.createElement('input');
                input.type = 'checkbox';
                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + name));
                institutionList.appendChild(label);
            });
        }

        const allCheckbox = institutionList.querySelector('.institution-item:first-child input[type="checkbox"]');
        const otherCheckboxes = institutionList.querySelectorAll('.institution-item:not(:first-child) input[type="checkbox"]');

        function setAllInstitutionsSelected(selected) {
            otherCheckboxes.forEach(cb => {
                cb.checked = selected;
            });
            if (allCheckbox) {
                allCheckbox.checked = selected;
            }
        }

        function syncAllCheckboxFromIndividuals() {
            if (!allCheckbox) return;
            const allChecked = Array.from(otherCheckboxes).every(c => c.checked);
            allCheckbox.checked = allChecked;
        }

        // Default behavior: "(All)" means every institution is selected.
        if (allCheckbox) {
            setAllInstitutionsSelected(true);
        }

        if (allCheckbox) {
            allCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    // Select all institutions when "(All)" is checked.
                    setAllInstitutionsSelected(true);
                } else {
                    // If user unchecks "(All)", clear all institution selections.
                    setAllInstitutionsSelected(false);
                }
                applyFilters();
            });
        }

        otherCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                syncAllCheckboxFromIndividuals();
                applyFilters();
            });
        });
    }

    // Initialize search input functionality
    function initializeSearchInput() {
        const searchInput = document.getElementById('institution-search');
        if (!searchInput) return;

        const institutionList = document.querySelector('.institution-list');
        if (!institutionList) return;

        const items = institutionList.querySelectorAll('.institution-item');

        // Debounce function for performance
        let debounceTimer;
        function debounce(func, delay) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(func, delay);
        }

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            debounce(() => {
                let firstMatch = null;

                items.forEach((item, index) => {
                    // Skip the "(All)" option
                    if (index === 0) {
                        item.style.display = '';
                        item.style.backgroundColor = '';
                        return;
                    }

                    const text = item.textContent.toLowerCase();

                    if (searchTerm === '') {
                        // Show all items when search is empty
                        item.style.display = '';
                        item.style.backgroundColor = '';
                    } else if (text.includes(searchTerm)) {
                        // Show and highlight matching items
                        item.style.display = '';
                        item.style.backgroundColor = '#D3DEF6';
                        if (!firstMatch) {
                            firstMatch = item;
                        }
                    } else {
                        // Hide non-matching items
                        item.style.display = 'none';
                        item.style.backgroundColor = '';
                    }
                });

                // Scroll to first match
                if (firstMatch) {
                    firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 150);
        });

        // Clear highlights when input loses focus and is empty
        searchInput.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                items.forEach(item => {
                    item.style.display = '';
                    item.style.backgroundColor = '';
                });
            }
        });
    }

    // Main initialization
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    }

    function initAll() {
        initializeFilters();
        initializeClearButton();
        initializePeriodButtons();
        initializeInstitutionList();
        initializeSearchInput();
        enforceInstitutionAllVisualState();

        // Apply initial filters
        applyFilters();

        console.log('BMGF Filter Controller initialized');
    }

    // Listen for filter requests from iframes
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'BMGF_REQUEST_FILTERS') {
            // Send current filters to the requesting iframe
            if (event.source) {
                try {
                    event.source.postMessage({
                        type: 'BMGF_FILTER_UPDATE',
                        filters: currentFilters,
                        filteredData: getFilteredData()
                    }, '*');
                } catch (e) {
                    console.warn('Could not respond to filter request:', e);
                }
            }
        }
    });

    // Expose API for external use
    window.BMGFFilters = {
        getFilters: () => ({ ...currentFilters }),
        setFilter: (name, value) => {
            if (name in currentFilters) {
                currentFilters[name] = value;
                applyFilters();
            }
        },
        clearFilters: () => {
            currentFilters = { ...defaultFilters };
            applyFilters();
        },
        getFilteredData: getFilteredData,
        applyFilters: applyFilters
    };

    // Start initialization
    init();

})();
