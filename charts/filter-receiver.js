/**
 * Filter Receiver for BMGF Calculus Dashboard Charts
 * Include this in iframe charts to receive filter updates from the parent page
 */

(function() {
    'use strict';

    // Store current filters
    window.BMGF_CURRENT_FILTERS = {
        course: 'All',
        period: 'All',
        state: 'All',
        sector: 'All',
        region: 'All',
        publisher: 'All',
        msiType: 'All',
        priceRange: 'All'
    };

    // Store filtered data from parent
    window.BMGF_FILTERED_DATA = null;

    // Region mapping (same as controller)
    window.BMGF_STATE_TO_REGION = {
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

    // Listen for filter updates from parent
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'BMGF_FILTER_UPDATE') {
            window.BMGF_CURRENT_FILTERS = event.data.filters;
            window.BMGF_FILTERED_DATA = event.data.filteredData || null;

            // Dispatch event for chart to handle
            window.dispatchEvent(new CustomEvent('bmgf:filtersReceived', {
                detail: {
                    filters: event.data.filters,
                    filteredData: event.data.filteredData || null
                }
            }));

            // If a global filter handler is defined, call it
            if (typeof window.handleFilterUpdate === 'function') {
                window.handleFilterUpdate(event.data.filters, event.data.filteredData || null);
            }
        }
    });

    // Request initial filters from parent on load
    if (window.parent && window.parent !== window) {
        try {
            window.parent.postMessage({ type: 'BMGF_REQUEST_FILTERS' }, '*');
        } catch (e) {
            console.warn('Could not request filters from parent:', e);
        }
    }

    console.log('BMGF Filter Receiver initialized');
})();
