<?php
/**
 * BMGF Data Manager
 * Manages all dashboard data with defaults and custom DB table storage
 */

if (!defined('ABSPATH')) {
    exit;
}

class BMGF_Data_Manager {

    private static ?BMGF_Data_Manager $instance = null;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function get_all_data(): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bmgf_dashboard_data';
        $saved = [];

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $results = $wpdb->get_results("SELECT section_key, section_data FROM $table_name", ARRAY_A);
            if ($results) {
                foreach ($results as $row) {
                    $saved[$row['section_key']] = json_decode($row['section_data'], true);
                }
            }
        } else {
            $saved = get_option('bmgf_dashboard_data', []);
            if (!is_array($saved)) {
                $saved = [];
            }
        }

        $defaults = $this->get_defaults();
        return $this->merge_deep($defaults, $saved);
    }

    public function get_section(string $section): array {
        $all_data = $this->get_all_data();
        return $all_data[$section] ?? [];
    }

    public function save_section(string $section, array $data): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bmgf_dashboard_data';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $all_data = get_option('bmgf_dashboard_data', []);
            if (!is_array($all_data)) $all_data = [];
            
            $all_data[$section] = $data;
            update_option('bmgf_dashboard_data', $all_data);
            return true;
        }

        $encoded = wp_json_encode($data);

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO $table_name (section_key, section_data) VALUES (%s, %s) 
                 ON DUPLICATE KEY UPDATE section_data = VALUES(section_data)",
                $section,
                $encoded
            )
        );

        return true;
    }

    public function save_all(array $data): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bmgf_dashboard_data';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            update_option('bmgf_dashboard_data', $data);
            return true;
        }

        foreach ($data as $section => $section_data) {
            $encoded = wp_json_encode($section_data);
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO $table_name (section_key, section_data) VALUES (%s, %s) 
                     ON DUPLICATE KEY UPDATE section_data = VALUES(section_data)",
                    $section,
                    $encoded
                )
            );
        }

        return true;
    }

    public function reset_to_defaults(): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bmgf_dashboard_data';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
        
        delete_option('bmgf_dashboard_data');
        return true;
    }

    private function merge_deep(array $defaults, array $saved): array {
        $result = $defaults;

        foreach ($saved as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->merge_deep($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function get_defaults(): array {
        return [
            'branding' => $this->get_default_branding(),
            'kpis' => $this->get_default_kpis(),
            'regional_data' => $this->get_default_regional_data(),
            'sector_data' => $this->get_default_sector_data(),
            'publishers' => $this->get_default_publishers(),
            'top_institutions' => $this->get_default_top_institutions(),
            'top_textbooks' => $this->get_default_top_textbooks(),
            'period_data' => $this->get_default_period_data(),
            'institution_size_data' => $this->get_default_institution_size_data(),
            'region_coverage' => $this->get_default_region_coverage(),
            'filters' => $this->get_default_filters(),
            'state_data' => $this->get_default_state_data(),
        ];
    }

    private function get_default_branding(): array {
        $base_url = defined('BMGF_DASHBOARD_URL') ? BMGF_DASHBOARD_URL : plugin_dir_url(dirname(__FILE__));
        
        return [
            'company_name' => 'Partner In Publishing',
            'dashboard_title_line1' => 'Math Education',
            'dashboard_title_line2_highlight' => 'Market',
            'dashboard_title_line2_normal' => 'Analysis',
            'logo_url' => $base_url . 'assets/Learnvia - principal logo.png',
            'footer_logo_url' => '', // Empty means it will inherit the main logo
            'primary_color' => '#008384',
            'secondary_color' => '#234A5D',
            'accent_color' => '#7FBFC0',
            'tertiary_color' => '#4A81A8',
            'quaternary_color' => '#92A4CF',
            'light_accent_color' => '#D3DEF6',
            'font_family' => 'Inter Tight',
        ];
    }

    private function get_default_kpis(): array {
        return [
            'total_institutions' => 933,
            'total_enrollment' => 1817722,
            'calc1_enrollment' => 1202783,
            'calc1_share' => 66.2,
            'calc2_enrollment' => 614939,
            'calc2_share' => 33.8,
            'total_textbooks' => 824,
            'avg_textbook_price' => 143,
            'total_fte_enrollment' => 10703763,
            'avg_price_calc1' => 125.50,
            'avg_price_calc2' => 118.75,
            'commercial_share' => 78,
            'oer_share' => 22,
            'digital_share' => 85,
            'print_share' => 15,
        ];
    }

    private function get_default_state_data(): array { return []; }

    private function get_default_regional_data(): array {
        return [
            'calc1' => [
                ['name' => 'Southeast', 'percentage' => 29, 'value' => 346181],
                ['name' => 'Far West', 'percentage' => 19, 'value' => 229591],
                ['name' => 'Mid East', 'percentage' => 16, 'value' => 193514],
                ['name' => 'Great Lakes', 'percentage' => 13, 'value' => 152706],
                ['name' => 'Southwest', 'percentage' => 12, 'value' => 138181],
                ['name' => 'Rocky Mountains', 'percentage' => 6, 'value' => 65814],
                ['name' => 'Plains', 'percentage' => 3, 'value' => 36024],
                ['name' => 'New England', 'percentage' => 2, 'value' => 25083],
                ['name' => 'Outlying Areas', 'percentage' => 0, 'value' => 825],
            ],
            'calc2' => [
                ['name' => 'Southeast', 'percentage' => 28, 'value' => 172183],
                ['name' => 'Far West', 'percentage' => 20, 'value' => 122988],
                ['name' => 'Mid East', 'percentage' => 17, 'value' => 104540],
                ['name' => 'Great Lakes', 'percentage' => 13, 'value' => 79942],
                ['name' => 'Southwest', 'percentage' => 11, 'value' => 67643],
                ['name' => 'Rocky Mountains', 'percentage' => 5, 'value' => 30747],
                ['name' => 'Plains', 'percentage' => 3, 'value' => 18448],
                ['name' => 'New England', 'percentage' => 2, 'value' => 12299],
                ['name' => 'Outlying Areas', 'percentage' => 1, 'value' => 6149],
            ],
        ];
    }

    private function get_default_sector_data(): array {
        return [
            'calc1' => [
                ['name' => '4-Year Public', 'percentage' => 70, 'value' => 836560],
                ['name' => '2-Year Public', 'percentage' => 17, 'value' => 201591],
                ['name' => '4-Year Private', 'percentage' => 12, 'value' => 149768],
            ],
            'calc2' => [
                ['name' => '4-Year Public', 'percentage' => 72, 'value' => 442756],
                ['name' => '2-Year Public', 'percentage' => 14, 'value' => 86091],
                ['name' => '4-Year Private', 'percentage' => 14, 'value' => 86091],
            ],
        ];
    }

    private function get_default_publishers(): array {
        return [
            ['name' => 'Cengage', 'market_share' => 39, 'enrollment' => 697000, 'avg_price' => 142.50, 'color' => '#008384'],
            ['name' => 'Pearson', 'market_share' => 23, 'enrollment' => 410000, 'avg_price' => 155.00, 'color' => '#234A5D'],
            ['name' => 'Other', 'market_share' => 18, 'enrollment' => 331000, 'avg_price' => 95.00, 'color' => '#92A4CF'],
            ['name' => 'Wiley', 'market_share' => 9, 'enrollment' => 158000, 'avg_price' => 148.00, 'color' => '#4A81A8'],
            ['name' => 'OpenStax', 'market_share' => 5, 'enrollment' => 84000, 'avg_price' => 0, 'color' => '#7FBFC0'],
            ['name' => 'Macmillan', 'market_share' => 3, 'enrollment' => 62000, 'avg_price' => 135.00, 'color' => '#D3DEF6'],
        ];
    }

    private function get_default_top_institutions(): array {
        return [
            ['name' => 'Univ of Michigan', 'enrollment' => 98000],
            ['name' => 'Univ of Florida', 'enrollment' => 92000],
            ['name' => 'Florida State Univ', 'enrollment' => 89000],
            ['name' => 'UCF', 'enrollment' => 67000],
            ['name' => 'UT Dallas', 'enrollment' => 57000],
            ['name' => 'Rutgers', 'enrollment' => 46000],
            ['name' => 'Lone Star College', 'enrollment' => 25000],
            ['name' => 'Oregon State', 'enrollment' => 24000],
            ['name' => 'CU Boulder', 'enrollment' => 24000],
            ['name' => 'Univ of Mississippi', 'enrollment' => 23000],
        ];
    }

    private function get_default_top_textbooks(): array {
        return [
            ['name' => 'MyLab Math Calculus', 'publisher' => 'Pearson', 'enrollment' => 133310],
            ['name' => 'Calculus Single-Variable', 'publisher' => 'Wiley', 'enrollment' => 98775],
            ['name' => 'Thomas Calculus', 'publisher' => 'Pearson', 'enrollment' => 84617],
            ['name' => 'WebAssign Calculus', 'publisher' => 'Cengage', 'enrollment' => 63039],
            ['name' => 'Calculus Single+Multi', 'publisher' => 'Wiley', 'enrollment' => 61642],
            ['name' => 'Knewton Alta Calculus', 'publisher' => 'Other', 'enrollment' => 56726],
            ['name' => 'Calculus OpenStax', 'publisher' => 'OpenStax', 'enrollment' => 52834],
            ['name' => 'Stewart Calculus', 'publisher' => 'Cengage', 'enrollment' => 48521],
            ['name' => 'Larson Calculus', 'publisher' => 'Cengage', 'enrollment' => 42156],
            ['name' => 'Calculus Business', 'publisher' => 'Pearson', 'enrollment' => 38420],
        ];
    }

    private function get_default_period_data(): array {
        return [
            ['period' => 'Fall 2025', 'calc1' => 547, 'calc2' => 269],
            ['period' => 'Spring 2025', 'calc1' => 472, 'calc2' => 233],
            ['period' => 'Winter 2025', 'calc1' => 174, 'calc2' => 85],
            ['period' => 'Summer 2025', 'calc1' => 105, 'calc2' => 51],
        ];
    }

    private function get_default_institution_size_data(): array {
        return [
            ['size' => 'Large (>20K)', 'calc1' => 485, 'calc2' => 240],
            ['size' => 'Medium (5-20K)', 'calc1' => 412, 'calc2' => 203],
            ['size' => 'Small (1-5K)', 'calc1' => 298, 'calc2' => 147],
            ['size' => 'Very Small (<1K)', 'calc1' => 121, 'calc2' => 62],
        ];
    }

    private function get_default_region_coverage(): array {
        return [
            ['name' => 'Southeast', 'label' => 'Southeast (AL, AR, FL, GA, KY, LA, MS, NC, SC, TN, VA, WV)', 'states' => ['AL','AR','FL','GA','KY','LA','MS','NC','SC','TN','VA','WV'], 'enrollment' => 325348],
            ['name' => 'Far West', 'label' => 'Far West (AK, CA, HI, NV, OR, WA)', 'states' => ['AK','CA','HI','NV','OR','WA'], 'enrollment' => 265312],
            ['name' => 'Mid East', 'label' => 'Mid East (DE, DC, MD, NJ, NY, PA)', 'states' => ['DE','DC','MD','NJ','NY','PA'], 'enrollment' => 202959],
            ['name' => 'Southwest', 'label' => 'Southwest (AZ, NM, OK, TX)', 'states' => ['AZ','NM','OK','TX'], 'enrollment' => 174852],
            ['name' => 'Great Lakes', 'label' => 'Great Lakes (IL, IN, MI, OH, WI)', 'states' => ['IL','IN','MI','OH','WI'], 'enrollment' => 169795],
            ['name' => 'Plains', 'label' => 'Plains (IA, KS, MN, MO, NE, ND, SD)', 'states' => ['IA','KS','MN','MO','NE','ND','SD'], 'enrollment' => 78306],
            ['name' => 'Rocky Mountains', 'label' => 'Rocky Mountains (CO, ID, MT, UT, WY)', 'states' => ['CO','ID','MT','UT','WY'], 'enrollment' => 70171],
            ['name' => 'New England', 'label' => 'New England (CT, ME, MA, NH, RI, VT)', 'states' => ['CT','ME','MA','NH','RI','VT'], 'enrollment' => 60348],
            ['name' => 'Other U.S. jurisdictions', 'label' => 'Other U.S. jurisdictions (AS, FM, GU, MH, MP, PR, PW, VI)', 'states' => ['AS','FM','GU','MH','MP','PR','PW','VI'], 'enrollment' => 8084],
        ];
    }

    private function get_default_filters(): array {
        return [
            'states' => [
                'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California',
                'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia',
                'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa',
                'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland',
                'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri',
                'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey',
                'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio',
                'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina',
                'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont',
                'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
            ],
            'regions' => ['Southeast', 'Far West', 'Mid East', 'Great Lakes', 'Southwest', 'Rocky Mountains', 'Plains', 'New England', 'Outlying Areas'],
            'sectors' => ['2, Public', '4, Public', '4, PNFP', '4, PFP'],
            'msi_types' => ['Not MSI', 'HBCU', 'HSI', 'AANAPISI', 'ANNH', 'PBI', 'TCU'],
            'publishers' => ['Cengage', 'Pearson', 'Wiley', 'OpenStax', 'Macmillan', 'Other'],
            'periods' => ['Fall 2025', 'Spring 2025', 'Winter 2025', 'Summer 2025'],
            'institutions' => ['Arizona State University Campus Immersion', 'Pennsylvania State University-Main Campus', 'University of Michigan-Ann Arbor', 'Purdue University-Main Campus', 'University of Washington-Seattle Campus'],
            'courses' => ['Calculus I', 'Calculus II', 'Calculus I & II'],
            'price_ranges' => ['Free (OER)', '$1 - $50', '$51 - $100', '$101 - $150', '$151 - $200', '$200+'],
        ];
    }

    public function get_js_data(): array {
        $data = $this->get_all_data();

        return [
            'branding' => $data['branding'] ?? [],
            'kpis' => $data['kpis'],
            'regional' => $data['regional_data'],
            'sectors' => $data['sector_data'],
            'publishers' => $data['publishers'],
            'topInstitutions' => $data['top_institutions'],
            'topTextbooks' => $data['top_textbooks'],
            'periods' => $data['period_data'],
            'institutionSizes' => $data['institution_size_data'],
            'regionCoverage' => $data['region_coverage'] ?? [],
            'filters' => $data['filters'],
            'state_data' => $data['state_data'] ?? [],
        ];
    }
}