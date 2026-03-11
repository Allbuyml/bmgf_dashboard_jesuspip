<?php
/**
 * BMGF Data Mapper
 * Maps parsed XLSX/CSV data to dashboard sections
 */

if (!defined('ABSPATH')) {
    exit;
}

class BMGF_Data_Mapper {

    /** Required columns for institutions file */
    public const INSTITUTION_REQUIRED = [
        'State', 'Region', 'Sector', 'School', 'FTE Enrollment',
        'Calc Level', 'Calc I Enrollment', 'Calc II Enrollment',
        ['Publisher_Norm', 'Publisher'], 'Avg_Price',
    ];

    /** Required columns for courses file */
    public const COURSE_REQUIRED = [
        'State', 'School', 'Period', 'Enrollments',
        'Book Title Normalized', 'Calc Level', 'Region',
        'Sector', 'Publisher_Normalized', 'Textbook_Price',
    ];

    /** Publisher colors for charts */
    private const PUBLISHER_COLORS = [
        '#008384', '#234A5D', '#4A81A8', '#7FBFC0', '#D3DEF6',
        '#92A4CF', '#2D8B75', '#5B4A8A', '#C17C3E', '#6B7280',
    ];

    /** State coordinates for map */
    private const STATE_COORDS = [
        'Alabama' => ['code' => 'AL', 'lat' => 32.806671, 'lon' => -86.79113],
        'Alaska' => ['code' => 'AK', 'lat' => 61.370716, 'lon' => -152.404419],
        'Arizona' => ['code' => 'AZ', 'lat' => 33.729759, 'lon' => -111.431221],
        'Arkansas' => ['code' => 'AR', 'lat' => 34.969704, 'lon' => -92.373123],
        'California' => ['code' => 'CA', 'lat' => 36.116203, 'lon' => -119.681564],
        'Colorado' => ['code' => 'CO', 'lat' => 39.059811, 'lon' => -105.311104],
        'Connecticut' => ['code' => 'CT', 'lat' => 41.597782, 'lon' => -72.755371],
        'Delaware' => ['code' => 'DE', 'lat' => 39.318523, 'lon' => -75.507141],
        'District of Columbia' => ['code' => 'DC', 'lat' => 38.897438, 'lon' => -77.026817],
        'Florida' => ['code' => 'FL', 'lat' => 27.766279, 'lon' => -81.686783],
        'Georgia' => ['code' => 'GA', 'lat' => 33.040619, 'lon' => -83.643074],
        'Hawaii' => ['code' => 'HI', 'lat' => 21.094318, 'lon' => -157.498337],
        'Idaho' => ['code' => 'ID', 'lat' => 44.240459, 'lon' => -114.478828],
        'Illinois' => ['code' => 'IL', 'lat' => 40.349457, 'lon' => -88.986137],
        'Indiana' => ['code' => 'IN', 'lat' => 39.849426, 'lon' => -86.258278],
        'Iowa' => ['code' => 'IA', 'lat' => 42.011539, 'lon' => -93.210526],
        'Kansas' => ['code' => 'KS', 'lat' => 38.5266, 'lon' => -96.726486],
        'Kentucky' => ['code' => 'KY', 'lat' => 37.66814, 'lon' => -84.670067],
        'Louisiana' => ['code' => 'LA', 'lat' => 31.169546, 'lon' => -91.867805],
        'Maine' => ['code' => 'ME', 'lat' => 44.693947, 'lon' => -69.381927],
        'Maryland' => ['code' => 'MD', 'lat' => 39.063946, 'lon' => -76.802101],
        'Massachusetts' => ['code' => 'MA', 'lat' => 42.230171, 'lon' => -71.530106],
        'Michigan' => ['code' => 'MI', 'lat' => 43.326618, 'lon' => -84.536095],
        'Minnesota' => ['code' => 'MN', 'lat' => 45.694454, 'lon' => -93.900192],
        'Mississippi' => ['code' => 'MS', 'lat' => 32.741646, 'lon' => -89.678696],
        'Missouri' => ['code' => 'MO', 'lat' => 38.456085, 'lon' => -92.288368],
        'Montana' => ['code' => 'MT', 'lat' => 46.921925, 'lon' => -110.454353],
        'Nebraska' => ['code' => 'NE', 'lat' => 41.12537, 'lon' => -98.268082],
        'Nevada' => ['code' => 'NV', 'lat' => 38.313515, 'lon' => -117.055374],
        'New Hampshire' => ['code' => 'NH', 'lat' => 43.452492, 'lon' => -71.563896],
        'New Jersey' => ['code' => 'NJ', 'lat' => 40.298904, 'lon' => -74.521011],
        'New Mexico' => ['code' => 'NM', 'lat' => 34.840515, 'lon' => -106.248482],
        'New York' => ['code' => 'NY', 'lat' => 42.165726, 'lon' => -74.948051],
        'North Carolina' => ['code' => 'NC', 'lat' => 35.630066, 'lon' => -79.806419],
        'North Dakota' => ['code' => 'ND', 'lat' => 47.528912, 'lon' => -99.784012],
        'Ohio' => ['code' => 'OH', 'lat' => 40.388783, 'lon' => -82.764915],
        'Oklahoma' => ['code' => 'OK', 'lat' => 35.565342, 'lon' => -96.928917],
        'Oregon' => ['code' => 'OR', 'lat' => 44.572021, 'lon' => -122.070938],
        'Pennsylvania' => ['code' => 'PA', 'lat' => 40.590752, 'lon' => -77.209755],
        'Puerto Rico' => ['code' => 'PR', 'lat' => 18.220833, 'lon' => -66.590149],
        'Rhode Island' => ['code' => 'RI', 'lat' => 41.680893, 'lon' => -71.51178],
        'South Carolina' => ['code' => 'SC', 'lat' => 33.856892, 'lon' => -80.945007],
        'South Dakota' => ['code' => 'SD', 'lat' => 44.299782, 'lon' => -99.438828],
        'Tennessee' => ['code' => 'TN', 'lat' => 35.747845, 'lon' => -86.692345],
        'Texas' => ['code' => 'TX', 'lat' => 31.054487, 'lon' => -97.563461],
        'Utah' => ['code' => 'UT', 'lat' => 40.150032, 'lon' => -111.862434],
        'Vermont' => ['code' => 'VT', 'lat' => 44.045876, 'lon' => -72.710686],
        'Virginia' => ['code' => 'VA', 'lat' => 37.769337, 'lon' => -78.169968],
        'Washington' => ['code' => 'WA', 'lat' => 47.400902, 'lon' => -121.490494],
        'West Virginia' => ['code' => 'WV', 'lat' => 38.491226, 'lon' => -80.954453],
        'Wisconsin' => ['code' => 'WI', 'lat' => 44.268543, 'lon' => -89.616508],
        'Wyoming' => ['code' => 'WY', 'lat' => 42.755966, 'lon' => -107.30249],
    ];

    /**
     * Compute all dashboard sections from parsed data
     *
     * @param array|null $institutions Parsed institutions data ['headers'=>[...], 'rows'=>[[...],...]]
     * @param array|null $courses Parsed courses data
     * @return array All dashboard sections
     */
    public static function compute_all(?array $institutions, ?array $courses): array {
        $inst_rows = $institutions ? self::rows_to_assoc($institutions) : [];
        $course_rows = $courses ? self::rows_to_assoc($courses) : [];

        $result = [];
        $result['kpis'] = self::compute_kpis($inst_rows, $course_rows);
        $result['regional_data'] = self::compute_regional($course_rows);
        $result['region_coverage'] = self::compute_region_coverage($inst_rows);
        $result['sector_data'] = self::compute_sectors($inst_rows);
        $result['publishers'] = self::compute_publishers($course_rows);
        $result['top_institutions'] = self::compute_top_institutions($inst_rows);
        $result['top_textbooks'] = self::compute_top_textbooks($course_rows);
        $result['period_data'] = self::compute_periods($course_rows);
        $result['institution_size_data'] = self::compute_institution_sizes($inst_rows);
        $result['filters'] = self::compute_filters($inst_rows, $course_rows);
        $result['state_data'] = self::compute_state_data($inst_rows, $course_rows);

        return $result;
    }

    /**
     * Generate a preview summary of computed data
     */
    public static function preview(array $computed): array {
        $preview = [];

        if (!empty($computed['kpis'])) {
            $k = $computed['kpis'];
            $preview['kpis'] = sprintf(
                '%s institutions, %s total enrollment, Calc I: %s (%s%%), Calc II: %s (%s%%)',
                number_format($k['total_institutions']),
                number_format($k['total_enrollment']),
                number_format($k['calc1_enrollment']),
                $k['calc1_share'],
                number_format($k['calc2_enrollment']),
                $k['calc2_share']
            );
        }

        if (!empty($computed['regional_data']['calc1'])) {
            $preview['regional_data'] = count($computed['regional_data']['calc1']) . ' regions';
        }

        if (!empty($computed['region_coverage'])) {
            $preview['region_coverage'] = count($computed['region_coverage']) . ' coverage regions';
        }

        if (!empty($computed['sector_data']['calc1'])) {
            $preview['sector_data'] = count($computed['sector_data']['calc1']) . ' sectors';
        }

        if (!empty($computed['publishers'])) {
            $names = array_column($computed['publishers'], 'name');
            $preview['publishers'] = implode(', ', array_slice($names, 0, 5));
            if (count($names) > 5) {
                $preview['publishers'] .= ' +' . (count($names) - 5) . ' more';
            }
        }

        if (!empty($computed['top_institutions'])) {
            $preview['top_institutions'] = count($computed['top_institutions']) . ' institutions (top: ' . $computed['top_institutions'][0]['name'] . ')';
        }

        if (!empty($computed['top_textbooks'])) {
            $preview['top_textbooks'] = count($computed['top_textbooks']) . ' textbooks (top: ' . $computed['top_textbooks'][0]['name'] . ')';
        }

        if (!empty($computed['period_data'])) {
            $periods = array_column($computed['period_data'], 'period');
            $preview['period_data'] = implode(', ', $periods);
        }

        if (!empty($computed['institution_size_data'])) {
            $preview['institution_size_data'] = count($computed['institution_size_data']) . ' size categories';
        }

        if (!empty($computed['state_data'])) {
            $preview['state_data'] = count($computed['state_data']) . ' states';
        }

        return $preview;
    }

    /**
     * Convert indexed rows to associative arrays using headers
     */
    private static function rows_to_assoc(array $parsed): array {
        $headers = $parsed['headers'];
        $result = [];

        foreach ($parsed['rows'] as $row) {
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = $row[$i] ?? '';
            }
            $result[] = $assoc;
        }

        return $result;
    }

    /**
     * Compute KPIs from both datasets
     */
    private static function compute_kpis(array $inst_rows, array $course_rows): array {
        $total_calc1 = 0;
        $total_calc2 = 0;
        $total_fte = 0;
        $total_textbooks = 0;
        $all_textbook_prices = [];
        $unique_institutions = [];
        $unique_states = [];
        $prices_calc1 = [];
        $prices_calc2 = [];

        foreach ($inst_rows as $row) {
            $total_fte += (int)($row['FTE Enrollment'] ?? 0);
            $calc1 = (int)($row['Calc I Enrollment'] ?? 0);
            $calc2 = (int)($row['Calc II Enrollment'] ?? 0);
            $total_calc1 += $calc1;
            $total_calc2 += $calc2;

            // Prefer stable institution identifiers when available.
            $inst_id = trim($row['IPED ID'] ?? '');
            $school = trim($row['School'] ?? '');
            $inst_key = $inst_id !== '' ? $inst_id : $school;
            if ($inst_key !== '') {
                $unique_institutions[$inst_key] = true;
            }

            $state = self::normalize_state(trim($row['State'] ?? ''));
            if ($state !== '') {
                $unique_states[$state] = true;
            }
        }

        // Prices from courses
        $oer_enrollment = 0;
        $commercial_enrollment = 0;
        $total_course_enrollment = 0;

        foreach ($course_rows as $row) {
            $enrollment = (int)($row['Enrollments'] ?? 0);
            $price = self::parse_price($row['Textbook_Price'] ?? '');
            $calc_level = strtoupper(trim($row['Calc Level'] ?? ''));
            $publisher = trim($row['Publisher_Normalized'] ?? '');
            $book_title = (string)($row['Book Title'] ?? ($row['Book Title Normalized'] ?? ''));
            $total_course_enrollment += $enrollment;
            $total_textbooks += self::count_textbooks_from_cell($book_title);

            $raw_price = (string)($row['Textbook_Price'] ?? '');
            $parsed_price = self::parse_price_nullable($raw_price);
            if ($parsed_price !== null) {
                $all_textbook_prices[] = $parsed_price;
            }

            if ($price > 0) {
                if (strpos($calc_level, 'I') !== false && strpos($calc_level, 'II') === false) {
                    $prices_calc1[] = $price;
                } elseif (strpos($calc_level, 'II') !== false) {
                    $prices_calc2[] = $price;
                }
            }

            // OER detection
            $is_oer = (strtolower($publisher) === 'openstax' || $price == 0);
            if ($is_oer) {
                $oer_enrollment += $enrollment;
            } else {
                $commercial_enrollment += $enrollment;
            }
        }

        $total_enrollment = $total_calc1 + $total_calc2;
        $calc1_share = $total_enrollment > 0 ? round($total_calc1 / $total_enrollment * 100, 1) : 0;
        $calc2_share = $total_enrollment > 0 ? round($total_calc2 / $total_enrollment * 100, 1) : 0;

        $avg_price_calc1 = !empty($prices_calc1) ? round(array_sum($prices_calc1) / count($prices_calc1), 2) : 0;
        $avg_price_calc2 = !empty($prices_calc2) ? round(array_sum($prices_calc2) / count($prices_calc2), 2) : 0;
        $avg_textbook_price = !empty($all_textbook_prices) ? round(array_sum($all_textbook_prices) / count($all_textbook_prices), 2) : 0;

        $commercial_share = $total_course_enrollment > 0 ? round($commercial_enrollment / $total_course_enrollment * 100) : 0;
        $oer_share = $total_course_enrollment > 0 ? round($oer_enrollment / $total_course_enrollment * 100) : 0;

        return [
            'total_institutions' => count($unique_institutions),
            'total_enrollment' => $total_enrollment,
            'calc1_enrollment' => $total_calc1,
            'calc1_share' => $calc1_share,
            'calc2_enrollment' => $total_calc2,
            'calc2_share' => $calc2_share,
            'total_textbooks' => $total_textbooks,
            'avg_textbook_price' => $avg_textbook_price,
            // Sum of institutional FTE across the institution dataset.
            'total_fte_enrollment' => $total_fte,
            'avg_price_calc1' => $avg_price_calc1,
            'avg_price_calc2' => $avg_price_calc2,
            'commercial_share' => $commercial_share,
            'oer_share' => $oer_share,
            'digital_share' => 85,
            'print_share' => 15,
        ];
    }

    /**
     * Count textbooks in Book Title cell.
     * Rules:
     * - Empty/placeholder values (e.g. "Unavailable", "*No Book Details*") count as 0
     * - Valid title counts as 1
     * - Multi-title cells separated by ";" "|" or line breaks count each valid title
     */
    private static function count_textbooks_from_cell(string $raw): int {
        $value = trim($raw);
        if ($value === '') {
            return 0;
        }

        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', (string)$normalized);
        $normalized = trim((string)$normalized, " \t\n\r\0\x0B*");

        $invalid = [
            'unavailable',
            'no book details',
            'no details',
            'n/a',
            'na',
            'none',
            'null',
        ];
        if (in_array($normalized, $invalid, true)) {
            return 0;
        }

        $parts = preg_split('/\s*(?:;|\||\r\n|\r|\n)\s*/', $value);
        if (!is_array($parts) || count($parts) === 0) {
            return 0;
        }

        $count = 0;
        foreach ($parts as $part) {
            $partNorm = strtolower(trim((string)$part));
            $partNorm = preg_replace('/\s+/', ' ', (string)$partNorm);
            $partNorm = trim((string)$partNorm, " \t\n\r\0\x0B*");
            if ($partNorm === '' || in_array($partNorm, $invalid, true)) {
                continue;
            }
            $count += 1;
        }

        return $count;
    }

    /**
     * Compute regional data from courses
     */
    private static function compute_regional(array $course_rows): array {
        $regions_calc1 = [];
        $regions_calc2 = [];

        foreach ($course_rows as $row) {
            $region_raw = trim($row['Region'] ?? '');
            // Extract region name before parenthesis: "Southeast (AL, AR, ...)" -> "Southeast"
            $region = preg_replace('/\s*\(.*\)$/', '', $region_raw);
            if ($region === '') continue;

            $enrollment = (int)($row['Enrollments'] ?? 0);
            $calc_level = strtoupper(trim($row['Calc Level'] ?? ''));

            if (strpos($calc_level, 'II') !== false) {
                $regions_calc2[$region] = ($regions_calc2[$region] ?? 0) + $enrollment;
            } else {
                $regions_calc1[$region] = ($regions_calc1[$region] ?? 0) + $enrollment;
            }
        }

        return [
            'calc1' => self::to_percentage_array($regions_calc1, 'name'),
            'calc2' => self::to_percentage_array($regions_calc2, 'name'),
        ];
    }

    /**
     * Compute region coverage from institutions (equivalent to Summary!A12:B20 matrix).
     * Uses Region labels with state abbreviations and enrollment = Calc I + Calc II.
     */
    private static function compute_region_coverage(array $inst_rows): array {
        $regions = []; // raw region label => enrollment

        foreach ($inst_rows as $row) {
            $region_label = trim($row['Region'] ?? '');
            if ($region_label === '') continue;

            $calc1 = (int)($row['Calc I Enrollment'] ?? 0);
            $calc2 = (int)($row['Calc II Enrollment'] ?? 0);
            $regions[$region_label] = ($regions[$region_label] ?? 0) + $calc1 + $calc2;
        }

        arsort($regions);

        $result = [];
        foreach ($regions as $label => $enrollment) {
            $result[] = [
                'name' => self::extract_region_name($label),
                'label' => $label,
                'states' => self::extract_region_state_codes($label),
                'enrollment' => (int)$enrollment,
            ];
        }

        return $result;
    }

    /**
     * Compute sector data from institutions
     */
    private static function compute_sectors(array $inst_rows): array {
        $sector_institutions = [];

        foreach ($inst_rows as $row) {
            // Use raw Sector value exactly as provided in All_Institutions column C.
            $sector = trim($row['Sector'] ?? '');
            if ($sector === '') continue;

            $inst_id = trim($row['IPED ID'] ?? '');
            $school = trim($row['School'] ?? '');
            $inst_key = $inst_id !== '' ? $inst_id : $school;
            if ($inst_key === '') continue;

            if (!isset($sector_institutions[$sector])) {
                $sector_institutions[$sector] = [];
            }
            $sector_institutions[$sector][$inst_key] = true;
        }

        $sector_counts = [];
        foreach ($sector_institutions as $sector => $inst_map) {
            $sector_counts[$sector] = count($inst_map);
        }

        $by_institutions = self::to_percentage_array($sector_counts, 'name');

        // Backward compatibility: keep legacy keys, but based on institution distribution.
        $legacy = array_map(static function(array $row): array {
            return [
                'name' => $row['name'],
                'percentage' => $row['percentage'],
                'value' => $row['value'],
            ];
        }, $by_institutions);

        return [
            'institutions' => $by_institutions,
            'calc1' => $legacy,
            'calc2' => $legacy,
        ];
    }

    /**
     * Compute publisher data from courses
     */
    private static function compute_publishers(array $course_rows): array {
        $publisher_data = []; // name => ['enrollment' => int, 'prices' => []]

        foreach ($course_rows as $row) {
            $publisher = trim($row['Publisher_Normalized'] ?? '');
            if ($publisher === '') continue;

            $enrollment = (int)($row['Enrollments'] ?? 0);
            $price = self::parse_price($row['Textbook_Price'] ?? '');

            if (!isset($publisher_data[$publisher])) {
                $publisher_data[$publisher] = ['enrollment' => 0, 'prices' => []];
            }
            $publisher_data[$publisher]['enrollment'] += $enrollment;
            if ($price > 0) {
                $publisher_data[$publisher]['prices'][] = $price;
            }
        }

        // Sort by enrollment descending
        uasort($publisher_data, fn($a, $b) => $b['enrollment'] - $a['enrollment']);

        // Top 5 + aggregate rest as "Other"
        $total_enrollment = array_sum(array_column($publisher_data, 'enrollment'));
        $result = [];
        $count = 0;
        $other_enrollment = 0;
        $other_prices = [];

        foreach ($publisher_data as $name => $data) {
            if ($count < 5) {
                $avg_price = !empty($data['prices']) ? round(array_sum($data['prices']) / count($data['prices']), 2) : 0;
                $share = $total_enrollment > 0 ? round($data['enrollment'] / $total_enrollment * 100) : 0;

                $result[] = [
                    'name' => $name,
                    'market_share' => $share,
                    'enrollment' => $data['enrollment'],
                    'avg_price' => $avg_price,
                    'color' => self::PUBLISHER_COLORS[$count] ?? '#6B7280',
                ];
                $count++;
            } else {
                $other_enrollment += $data['enrollment'];
                $other_prices = array_merge($other_prices, $data['prices']);
            }
        }

        if ($other_enrollment > 0) {
            $avg_price = !empty($other_prices) ? round(array_sum($other_prices) / count($other_prices), 2) : 0;
            $share = $total_enrollment > 0 ? round($other_enrollment / $total_enrollment * 100) : 0;

            $result[] = [
                'name' => 'Other',
                'market_share' => $share,
                'enrollment' => $other_enrollment,
                'avg_price' => $avg_price,
                'color' => self::PUBLISHER_COLORS[5] ?? '#92A4CF',
            ];
        }

        return $result;
    }

    /**
     * Compute top 10 institutions by total enrollment
     */
    private static function compute_top_institutions(array $inst_rows): array {
        $institutions = [];

        foreach ($inst_rows as $row) {
            $name = trim($row['School'] ?? '');
            if ($name === '') continue;

            $calc1 = (int)($row['Calc I Enrollment'] ?? 0);
            $calc2 = (int)($row['Calc II Enrollment'] ?? 0);
            $total = $calc1 + $calc2;

            // Accumulate for institutions appearing in multiple rows
            if (!isset($institutions[$name])) {
                $institutions[$name] = 0;
            }
            $institutions[$name] += $total;
        }

        arsort($institutions);
        $top = array_slice($institutions, 0, 10, true);

        $result = [];
        foreach ($top as $name => $enrollment) {
            $result[] = ['name' => $name, 'enrollment' => $enrollment];
        }

        return $result;
    }

    /**
     * Compute top 10 textbooks by enrollment
     */
    private static function compute_top_textbooks(array $course_rows): array {
        $textbooks = []; // title => ['enrollment' => int, 'publisher' => str, 'price' => float]

        foreach ($course_rows as $row) {
            $title = trim($row['Book Title Normalized'] ?? '');
            if ($title === '') continue;

            $enrollment = (int)($row['Enrollments'] ?? 0);
            $publisher = trim($row['Publisher_Normalized'] ?? '');
            $price = self::parse_price($row['Textbook_Price'] ?? '');

            if (!isset($textbooks[$title])) {
                $textbooks[$title] = ['enrollment' => 0, 'publisher' => $publisher, 'prices' => []];
            }
            $textbooks[$title]['enrollment'] += $enrollment;
            if ($price > 0) {
                $textbooks[$title]['prices'][] = $price;
            }
            // Keep the most common publisher
            if ($publisher !== '') {
                $textbooks[$title]['publisher'] = $publisher;
            }
        }

        uasort($textbooks, fn($a, $b) => $b['enrollment'] - $a['enrollment']);
        $top = array_slice($textbooks, 0, 10, true);

        $result = [];
        foreach ($top as $title => $data) {
            $avg_price = !empty($data['prices']) ? round(array_sum($data['prices']) / count($data['prices']), 2) : 0;
            $result[] = [
                'name' => $title,
                'publisher' => $data['publisher'],
                'enrollment' => $data['enrollment'],
            ];
        }

        return $result;
    }

    /**
     * Compute period data from courses
     */
    private static function compute_periods(array $course_rows): array {
        $periods = []; // period => ['calc1' => int, 'calc2' => int]

        foreach ($course_rows as $row) {
            $period = trim($row['Period'] ?? '');
            if ($period === '') continue;

            $enrollment = (int)($row['Enrollments'] ?? 0);
            $calc_level = strtoupper(trim($row['Calc Level'] ?? ''));

            if (!isset($periods[$period])) {
                $periods[$period] = ['calc1' => 0, 'calc2' => 0];
            }

            if (strpos($calc_level, 'II') !== false) {
                $periods[$period]['calc2'] += $enrollment;
            } else {
                $periods[$period]['calc1'] += $enrollment;
            }
        }

        // Sort by total enrollment descending
        uasort($periods, fn($a, $b) => ($b['calc1'] + $b['calc2']) - ($a['calc1'] + $a['calc2']));

        $result = [];
        foreach ($periods as $period => $data) {
            $result[] = [
                'period' => $period,
                'calc1' => $data['calc1'],
                'calc2' => $data['calc2'],
            ];
        }

        return $result;
    }

    /**
     * Compute institution size categories from institutions
     */
    private static function compute_institution_sizes(array $inst_rows): array {
        $sizes = [
            'Large (>20K)' => ['calc1' => 0, 'calc2' => 0],
            'Medium (5-20K)' => ['calc1' => 0, 'calc2' => 0],
            'Small (1-5K)' => ['calc1' => 0, 'calc2' => 0],
            'Very Small (<1K)' => ['calc1' => 0, 'calc2' => 0],
        ];

        foreach ($inst_rows as $row) {
            $fte = (int)($row['FTE Enrollment'] ?? 0);
            $calc1 = (int)($row['Calc I Enrollment'] ?? 0);
            $calc2 = (int)($row['Calc II Enrollment'] ?? 0);

            if ($fte > 20000) {
                $cat = 'Large (>20K)';
            } elseif ($fte >= 5000) {
                $cat = 'Medium (5-20K)';
            } elseif ($fte >= 1000) {
                $cat = 'Small (1-5K)';
            } else {
                $cat = 'Very Small (<1K)';
            }

            $sizes[$cat]['calc1'] += $calc1;
            $sizes[$cat]['calc2'] += $calc2;
        }

        $result = [];
        foreach ($sizes as $size => $data) {
            $result[] = [
                'size' => $size,
                'calc1' => $data['calc1'],
                'calc2' => $data['calc2'],
            ];
        }

        return $result;
    }

    /**
     * Compute filter lists from both datasets
     */
    private static function compute_filters(array $inst_rows, array $course_rows): array {
        $states = [];
        $regions = [];
        $sectors = [];
        $msi_types = [];
        $publishers = [];
        $periods = [];
        $institutions = [];

        foreach ($inst_rows as $row) {
            $state = self::normalize_state(trim($row['State'] ?? ''));
            if ($state !== '') $states[$state] = true;

            $region = preg_replace('/\s*\(.*\)$/', '', trim($row['Region'] ?? ''));
            if ($region !== '') $regions[$region] = true;

            $sector = trim($row['Sector'] ?? '');
            if ($sector !== '') $sectors[$sector] = true;

            $msi_type = trim($row['MSI Type'] ?? ($row['MSI TYPE'] ?? ''));
            if ($msi_type === '') $msi_type = 'Not MSI';
            $msi_types[$msi_type] = true;

            // Publisher filter in Institutions Analysis comes from All_Institutions column N (Publisher).
            $pub = trim($row['Publisher'] ?? ($row['Publisher_Norm'] ?? ($row['Publisher_Normalized'] ?? '')));
            if ($pub !== '') $publishers[$pub] = true;
        }

        foreach ($course_rows as $row) {
            $state = self::normalize_state(trim($row['State'] ?? ''));
            if ($state !== '') $states[$state] = true;

            $period = trim($row['Period'] ?? '');
            if ($period !== '') $periods[$period] = true;

            $school = trim($row['School'] ?? '');
            if ($school !== '' && strtolower($school) !== '(blank)') {
                $institutions[$school] = true;
            }
        }

        ksort($states);
        ksort($regions);
        ksort($sectors);
        ksort($msi_types);
        ksort($publishers);
        ksort($periods);
        ksort($institutions);

        return [
            'states' => array_keys($states),
            'regions' => array_keys($regions),
            'sectors' => array_keys($sectors),
            'msi_types' => array_keys($msi_types),
            'publishers' => array_keys($publishers),
            'periods' => array_keys($periods),
            'institutions' => array_keys($institutions),
            'courses' => ['Calculus I', 'Calculus II', 'Calculus I & II'],
            'price_ranges' => ['Free (OER)', '$1 - $50', '$51 - $100', '$101 - $150', '$151 - $200', '$200+'],
        ];
    }

    /**
     * Compute state-level data for the map
     */
    private static function compute_state_data(array $inst_rows, array $course_rows): array {
        // Aggregate institution data per state
        $state_inst = []; // state => ['calc1' => int, 'calc2' => int, 'fte' => int, 'institutions' => set]
        $state_publishers = []; // state => publisher => enrollment (from All_Institutions.Publisher)

        foreach ($inst_rows as $row) {
            $state = self::normalize_state(trim($row['State'] ?? ''));
            if ($state === '') continue;

            if (!isset($state_inst[$state])) {
                $state_inst[$state] = [
                    'calc1' => 0,
                    'calc2' => 0,
                    'fte' => 0,
                    'institutions' => [],
                    'institution_breakdown' => [],
                    'size_breakdown' => [],
                    'region_breakdown' => [],
                    'msi_breakdown' => [],
                    'sector_breakdown' => [],
                    'publisher_breakdown' => [],
                ];
            }

            $calc1 = (int)($row['Calc I Enrollment'] ?? 0);
            $calc2 = (int)($row['Calc II Enrollment'] ?? 0);
            $fte = (int)($row['FTE Enrollment'] ?? 0);

            $state_inst[$state]['calc1'] += $calc1;
            $state_inst[$state]['calc2'] += $calc2;
            $state_inst[$state]['fte'] += $fte;

            $inst_id = trim($row['IPED ID'] ?? '');
            $school = trim($row['School'] ?? '');
            $inst_key = $inst_id !== '' ? $inst_id : $school;
            if ($inst_key !== '') {
                $state_inst[$state]['institutions'][$inst_key] = true;
            }

            if ($school !== '') {
                if (!isset($state_inst[$state]['institution_breakdown'][$school])) {
                    $state_inst[$state]['institution_breakdown'][$school] = [
                        'calc_i' => 0,
                        'calc_ii' => 0,
                        'total' => 0,
                        'fte' => 0,
                    ];
                }
                $state_inst[$state]['institution_breakdown'][$school]['calc_i'] += $calc1;
                $state_inst[$state]['institution_breakdown'][$school]['calc_ii'] += $calc2;
                $state_inst[$state]['institution_breakdown'][$school]['total'] += ($calc1 + $calc2);
                $state_inst[$state]['institution_breakdown'][$school]['fte'] += $fte;
            }

            $size_category = '';
            if ($fte > 20000) {
                $size_category = 'Large (>20K)';
            } elseif ($fte >= 5000) {
                $size_category = 'Medium (5-20K)';
            } elseif ($fte >= 1000) {
                $size_category = 'Small (1-5K)';
            } else {
                $size_category = 'Very Small (<1K)';
            }

            if (!isset($state_inst[$state]['size_breakdown'][$size_category])) {
                $state_inst[$state]['size_breakdown'][$size_category] = [
                    'calc_i' => 0,
                    'calc_ii' => 0,
                    'total' => 0,
                    'fte' => 0,
                    'institutions' => [],
                ];
            }
            $state_inst[$state]['size_breakdown'][$size_category]['calc_i'] += $calc1;
            $state_inst[$state]['size_breakdown'][$size_category]['calc_ii'] += $calc2;
            $state_inst[$state]['size_breakdown'][$size_category]['total'] += ($calc1 + $calc2);
            $state_inst[$state]['size_breakdown'][$size_category]['fte'] += $fte;
            if ($inst_key !== '') {
                $state_inst[$state]['size_breakdown'][$size_category]['institutions'][$inst_key] = true;
            }

            $region = preg_replace('/\s*\(.*\)$/', '', trim($row['Region'] ?? ''));
            if ($region !== '') {
                if (!isset($state_inst[$state]['region_breakdown'][$region])) {
                    $state_inst[$state]['region_breakdown'][$region] = [
                        'calc_i' => 0,
                        'calc_ii' => 0,
                        'total' => 0,
                        'fte' => 0,
                        'institutions' => [],
                    ];
                }

                $state_inst[$state]['region_breakdown'][$region]['calc_i'] += $calc1;
                $state_inst[$state]['region_breakdown'][$region]['calc_ii'] += $calc2;
                $state_inst[$state]['region_breakdown'][$region]['total'] += ($calc1 + $calc2);
                $state_inst[$state]['region_breakdown'][$region]['fte'] += $fte;
                if ($inst_key !== '') {
                    $state_inst[$state]['region_breakdown'][$region]['institutions'][$inst_key] = true;
                }
            }

            $msi_type = trim($row['MSI Type'] ?? ($row['MSI TYPE'] ?? ''));
            if ($msi_type === '') {
                $msi_type = 'Not MSI';
            }

            if (!isset($state_inst[$state]['msi_breakdown'][$msi_type])) {
                $state_inst[$state]['msi_breakdown'][$msi_type] = [
                    'calc_i' => 0,
                    'calc_ii' => 0,
                    'total' => 0,
                    'fte' => 0,
                    'institutions' => [],
                ];
            }

            $state_inst[$state]['msi_breakdown'][$msi_type]['calc_i'] += $calc1;
            $state_inst[$state]['msi_breakdown'][$msi_type]['calc_ii'] += $calc2;
            $state_inst[$state]['msi_breakdown'][$msi_type]['total'] += ($calc1 + $calc2);
            $state_inst[$state]['msi_breakdown'][$msi_type]['fte'] += $fte;
            if ($inst_key !== '') {
                $state_inst[$state]['msi_breakdown'][$msi_type]['institutions'][$inst_key] = true;
            }

            $sector = trim($row['Sector'] ?? '');
            if ($sector !== '') {
                if (!isset($state_inst[$state]['sector_breakdown'][$sector])) {
                    $state_inst[$state]['sector_breakdown'][$sector] = [
                        'calc_i' => 0,
                        'calc_ii' => 0,
                        'total' => 0,
                        'fte' => 0,
                        'institutions' => [],
                    ];
                }

                $state_inst[$state]['sector_breakdown'][$sector]['calc_i'] += $calc1;
                $state_inst[$state]['sector_breakdown'][$sector]['calc_ii'] += $calc2;
                $state_inst[$state]['sector_breakdown'][$sector]['total'] += ($calc1 + $calc2);
                $state_inst[$state]['sector_breakdown'][$sector]['fte'] += $fte;
                if ($inst_key !== '') {
                    $state_inst[$state]['sector_breakdown'][$sector]['institutions'][$inst_key] = true;
                }
            }

            $publisher = trim($row['Publisher'] ?? ($row['Publisher_Norm'] ?? ($row['Publisher_Normalized'] ?? '')));
            if ($publisher !== '') {
                if (!isset($state_inst[$state]['publisher_breakdown'][$publisher])) {
                    $state_inst[$state]['publisher_breakdown'][$publisher] = [
                        'calc_i' => 0,
                        'calc_ii' => 0,
                        'total' => 0,
                        'fte' => 0,
                        'institutions' => [],
                    ];
                }

                $state_inst[$state]['publisher_breakdown'][$publisher]['calc_i'] += $calc1;
                $state_inst[$state]['publisher_breakdown'][$publisher]['calc_ii'] += $calc2;
                $state_inst[$state]['publisher_breakdown'][$publisher]['total'] += ($calc1 + $calc2);
                $state_inst[$state]['publisher_breakdown'][$publisher]['fte'] += $fte;
                if ($inst_key !== '') {
                    $state_inst[$state]['publisher_breakdown'][$publisher]['institutions'][$inst_key] = true;
                }

                if (!isset($state_publishers[$state])) {
                    $state_publishers[$state] = [];
                }
                $state_publishers[$state][$publisher] = ($state_publishers[$state][$publisher] ?? 0) + ($calc1 + $calc2);
            }
        }

        // Aggregate course count + period data per state from courses.
        $state_courses = []; // state => course-record count
        $state_periods = []; // state => period => ['total'=>int,'calc_i'=>int,'calc_ii'=>int,'courses'=>int]

        foreach ($course_rows as $row) {
            $state = self::normalize_state(trim($row['State'] ?? ''));
            $publisher = trim($row['Publisher_Normalized'] ?? '');
            $enrollment = (int)($row['Enrollments'] ?? 0);
            $period = trim($row['Period'] ?? '');
            $calc_level = strtoupper(trim($row['Calc Level'] ?? ''));

            if ($state === '') continue;

            $state_courses[$state] = ($state_courses[$state] ?? 0) + 1;

            if ($period !== '') {
                if (!isset($state_periods[$state])) {
                    $state_periods[$state] = [];
                }
                if (!isset($state_periods[$state][$period])) {
                    $state_periods[$state][$period] = ['total' => 0, 'calc_i' => 0, 'calc_ii' => 0, 'courses' => 0];
                }

                $state_periods[$state][$period]['total'] += $enrollment;
                $state_periods[$state][$period]['courses'] += 1;

                if ($calc_level === 'CALC_II') {
                    $state_periods[$state][$period]['calc_ii'] += $enrollment;
                } else {
                    $state_periods[$state][$period]['calc_i'] += $enrollment;
                }
            }
        }

        $result = [];

        foreach ($state_inst as $state => $data) {
            $coords = self::STATE_COORDS[$state] ?? null;
            if ($coords === null) continue;

            $total = $data['calc1'] + $data['calc2'];
            $inst_count = count($data['institutions']);

            // Format total
            if ($total >= 1000) {
                $total_fmt = round($total / 1000) . 'K';
            } else {
                $total_fmt = (string)$total;
            }

            $calc1_fmt = $data['calc1'] >= 1000 ? round($data['calc1'] / 1000) . 'K' : (string)$data['calc1'];
            $calc2_fmt = $data['calc2'] >= 1000 ? round($data['calc2'] / 1000) . 'K' : (string)$data['calc2'];

            // Top 3 publishers
            $pubs = $state_publishers[$state] ?? [];
            arsort($pubs);
            $top_pubs = array_slice($pubs, 0, 3, true);
            $pub_list = array_keys($top_pubs);
            $pub_enr = array_values($top_pubs);

            $total_full = $total >= 1000 ? number_format($total / 1000, 3) : (string)$total;
            $period_breakdown = $state_periods[$state] ?? [];
            if (!empty($period_breakdown)) {
                ksort($period_breakdown);
            }

            $region_breakdown = [];
            if (!empty($data['region_breakdown']) && is_array($data['region_breakdown'])) {
                foreach ($data['region_breakdown'] as $region_name => $region_data) {
                    $region_breakdown[$region_name] = [
                        'calc_i' => (int)($region_data['calc_i'] ?? 0),
                        'calc_ii' => (int)($region_data['calc_ii'] ?? 0),
                        'total' => (int)($region_data['total'] ?? 0),
                        'fte' => (int)($region_data['fte'] ?? 0),
                        'institutions' => isset($region_data['institutions']) && is_array($region_data['institutions'])
                            ? count($region_data['institutions'])
                            : 0,
                    ];
                }
                ksort($region_breakdown);
            }

            $institution_breakdown = [];
            if (!empty($data['institution_breakdown']) && is_array($data['institution_breakdown'])) {
                foreach ($data['institution_breakdown'] as $institution_name => $institution_data) {
                    $institution_breakdown[$institution_name] = [
                        'calc_i' => (int)($institution_data['calc_i'] ?? 0),
                        'calc_ii' => (int)($institution_data['calc_ii'] ?? 0),
                        'total' => (int)($institution_data['total'] ?? 0),
                        'fte' => (int)($institution_data['fte'] ?? 0),
                    ];
                }
                ksort($institution_breakdown);
            }

            $size_breakdown = [];
            if (!empty($data['size_breakdown']) && is_array($data['size_breakdown'])) {
                foreach ($data['size_breakdown'] as $size_name => $size_data) {
                    $size_breakdown[$size_name] = [
                        'calc_i' => (int)($size_data['calc_i'] ?? 0),
                        'calc_ii' => (int)($size_data['calc_ii'] ?? 0),
                        'total' => (int)($size_data['total'] ?? 0),
                        'fte' => (int)($size_data['fte'] ?? 0),
                        'institutions' => isset($size_data['institutions']) && is_array($size_data['institutions'])
                            ? count($size_data['institutions'])
                            : 0,
                    ];
                }
                ksort($size_breakdown);
            }

            $msi_breakdown = [];
            if (!empty($data['msi_breakdown']) && is_array($data['msi_breakdown'])) {
                foreach ($data['msi_breakdown'] as $msi_name => $msi_data) {
                    $msi_breakdown[$msi_name] = [
                        'calc_i' => (int)($msi_data['calc_i'] ?? 0),
                        'calc_ii' => (int)($msi_data['calc_ii'] ?? 0),
                        'total' => (int)($msi_data['total'] ?? 0),
                        'fte' => (int)($msi_data['fte'] ?? 0),
                        'institutions' => isset($msi_data['institutions']) && is_array($msi_data['institutions'])
                            ? count($msi_data['institutions'])
                            : 0,
                    ];
                }
                ksort($msi_breakdown);
            }

            $sector_breakdown = [];
            if (!empty($data['sector_breakdown']) && is_array($data['sector_breakdown'])) {
                foreach ($data['sector_breakdown'] as $sector_name => $sector_data) {
                    $sector_breakdown[$sector_name] = [
                        'calc_i' => (int)($sector_data['calc_i'] ?? 0),
                        'calc_ii' => (int)($sector_data['calc_ii'] ?? 0),
                        'total' => (int)($sector_data['total'] ?? 0),
                        'fte' => (int)($sector_data['fte'] ?? 0),
                        'institutions' => isset($sector_data['institutions']) && is_array($sector_data['institutions'])
                            ? count($sector_data['institutions'])
                            : 0,
                    ];
                }
                ksort($sector_breakdown);
            }

            $publisher_breakdown = [];
            if (!empty($data['publisher_breakdown']) && is_array($data['publisher_breakdown'])) {
                foreach ($data['publisher_breakdown'] as $publisher_name => $publisher_data) {
                    $publisher_breakdown[$publisher_name] = [
                        'calc_i' => (int)($publisher_data['calc_i'] ?? 0),
                        'calc_ii' => (int)($publisher_data['calc_ii'] ?? 0),
                        'total' => (int)($publisher_data['total'] ?? 0),
                        'fte' => (int)($publisher_data['fte'] ?? 0),
                        'institutions' => isset($publisher_data['institutions']) && is_array($publisher_data['institutions'])
                            ? count($publisher_data['institutions'])
                            : 0,
                    ];
                }
                ksort($publisher_breakdown);
            }

            $entry = [
                'state' => $state,
                'code' => $coords['code'],
                'lat' => $coords['lat'],
                'lon' => $coords['lon'],
                'lon_left' => $coords['lon'] - 0.65,
                'lon_right' => $coords['lon'] + 0.65,
                'total' => $total,
                'total_fmt' => $total_fmt,
                'total_full' => $total_full,
                'calc_i' => $data['calc1'],
                'calc_i_fmt' => $calc1_fmt,
                'calc_ii' => $data['calc2'],
                'calc_ii_fmt' => $calc2_fmt,
                'fte' => $data['fte'],
                'institutions' => $inst_count,
                'courses' => (int)($state_courses[$state] ?? 0),
                'pub1' => $pub_list[0] ?? '',
                'pub1_enr' => $pub_enr[0] ?? 0,
                'pub2' => $pub_list[1] ?? '',
                'pub2_enr' => $pub_enr[1] ?? 0,
                'pub3' => $pub_list[2] ?? '',
                'pub3_enr' => $pub_enr[2] ?? 0,
                'period_breakdown' => $period_breakdown,
                'institution_breakdown' => $institution_breakdown,
                'size_breakdown' => $size_breakdown,
                'region_breakdown' => $region_breakdown,
                'msi_breakdown' => $msi_breakdown,
                'sector_breakdown' => $sector_breakdown,
                'publisher_breakdown' => $publisher_breakdown,
            ];

            $result[] = $entry;
        }

        // Sort by total descending
        usort($result, fn($a, $b) => $b['total'] - $a['total']);

        return $result;
    }

    /**
     * Generate state_data_updated.js content from state_data
     */
    public static function generate_state_js(array $state_data): string {
        return 'const stateData = ' . json_encode($state_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ';' . "\n";
    }

    // ---- Helpers ----

    /**
     * Normalize sector names
     */
    private static function normalize_sector(string $raw): string {
        $raw = trim($raw);
        $lower = strtolower($raw);

        // Map known patterns
        if (strpos($lower, '4') !== false && strpos($lower, 'public') !== false) {
            return '4-Year Public';
        }
        if (strpos($lower, '2') !== false && strpos($lower, 'public') !== false) {
            return '2-Year Public';
        }
        if (strpos($lower, '4') !== false && (strpos($lower, 'private') !== false || strpos($lower, 'pnfp') !== false || strpos($lower, 'pfp') !== false)) {
            return '4-Year Private';
        }
        if (strpos($lower, '2') !== false && strpos($lower, 'private') !== false) {
            return '2-Year Private';
        }

        return $raw;
    }

    /**
     * Normalize state field to a display-friendly full name when given a 2-letter code.
     * Many source files use USPS codes (e.g., "CA"); the dashboard expects full names (e.g., "California").
     */
    private static function normalize_state(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $upper = strtoupper($raw);
        if (strlen($upper) === 2) {
            foreach (self::STATE_COORDS as $name => $coords) {
                if (($coords['code'] ?? '') === $upper) {
                    return $name;
                }
            }
        }

        return $raw;
    }

    /**
     * Extract clean region name from full label (e.g. "Southeast (AL, ...)" => "Southeast").
     */
    private static function extract_region_name(string $label): string {
        $name = preg_replace('/\s*\(.*\)\s*$/', '', trim($label));
        return is_string($name) ? trim($name) : trim($label);
    }

    /**
     * Extract state/jurisdiction codes from region label parentheses.
     */
    private static function extract_region_state_codes(string $label): array {
        if (!preg_match('/\(([^)]*)\)/', $label, $matches)) {
            return [];
        }

        $parts = array_map(static fn($v) => strtoupper(trim($v)), explode(',', $matches[1]));
        $parts = array_filter($parts, static fn($v) => $v !== '' && preg_match('/^[A-Z]{2}$/', $v));
        return array_values(array_unique($parts));
    }

    /**
     * Parse price value from string
     */
    private static function parse_price(string $value): float {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'n/a' || strtolower($value) === 'null') {
            return 0;
        }
        // Remove $ and commas
        $value = preg_replace('/[$,]/', '', $value);
        return (float)$value;
    }

    /**
     * Parse a price cell returning null when the value is not a price.
     * Keeps explicit 0/free values as valid numeric prices.
     */
    private static function parse_price_nullable(string $value): ?float {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $normalized = strtolower($raw);
        if (in_array($normalized, ['n/a', 'na', 'null', 'none', 'unavailable', '*no book details*', 'no book details'], true)) {
            return null;
        }

        if (in_array($normalized, ['free', 'oer'], true)) {
            return 0.0;
        }

        $clean = preg_replace('/[$,\s]/', '', $raw);
        if ($clean === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', (string)$clean)) {
            return null;
        }

        return (float)$clean;
    }

    /**
     * Convert associative count array to sorted percentage array
     */
    private static function to_percentage_array(array $data, string $name_key): array {
        $total = array_sum($data);
        arsort($data);

        $result = [];
        foreach ($data as $name => $value) {
            $pct = $total > 0 ? round($value / $total * 100) : 0;
            $result[] = [
                $name_key => $name,
                'percentage' => $pct,
                'value' => $value,
            ];
        }

        return $result;
    }
}
