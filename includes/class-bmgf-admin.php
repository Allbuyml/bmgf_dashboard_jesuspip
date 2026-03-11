<?php
/**
 * BMGF Admin Panel
 * Provides WordPress admin interface for editing dashboard data
 */

if (!defined('ABSPATH')) {
    exit;
}

class BMGF_Admin {

    private static ?BMGF_Admin $instance = null;
    private BMGF_Data_Manager $data_manager;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->data_manager = BMGF_Data_Manager::get_instance();

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_bmgf_save_section', [$this, 'ajax_save_section']);
        add_action('wp_ajax_bmgf_reset_defaults', [$this, 'ajax_reset_defaults']);
        add_action('wp_ajax_bmgf_upload_file', [$this, 'ajax_upload_file']);
        add_action('wp_ajax_bmgf_apply_upload', [$this, 'ajax_apply_upload']);
    }

    public function add_admin_menu(): void {
        add_menu_page(
            'BMGF Dashboard Settings',
            'BMGF Dashboard',
            'manage_options',
            'bmgf-dashboard',
            [$this, 'render_admin_page'],
            'dashicons-chart-area',
            30
        );
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'toplevel_page_bmgf-dashboard') {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style('bmgf-admin-style', BMGF_DASHBOARD_URL . 'admin/css/bmgf-admin.css', [], BMGF_DASHBOARD_VERSION);
        wp_enqueue_script('bmgf-admin-script', BMGF_DASHBOARD_URL . 'admin/js/bmgf-admin.js', ['jquery'], BMGF_DASHBOARD_VERSION, true);
        wp_enqueue_script('bmgf-upload-script', BMGF_DASHBOARD_URL . 'admin/js/bmgf-upload.js', ['jquery', 'bmgf-admin-script'], BMGF_DASHBOARD_VERSION, true);

        wp_localize_script('bmgf-admin-script', 'bmgfAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bmgf_admin_nonce'),
            'data' => $this->data_manager->get_all_data(),
        ]);
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        include BMGF_DASHBOARD_PATH . 'admin/partials/admin-page.php';
    }

    public function ajax_save_section(): void {
        check_ajax_referer('bmgf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $section = sanitize_text_field($_POST['section'] ?? '');
        $data = isset($_POST['data']) ? $this->sanitize_section_data($section, wp_unslash($_POST['data'])) : [];

        if (empty($section)) {
            wp_send_json_error(['message' => 'Invalid section']);
            return;
        }

        $this->data_manager->save_section($section, $data);
        wp_send_json_success(['message' => 'Section saved successfully']);
    }

    public function ajax_reset_defaults(): void {
        check_ajax_referer('bmgf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $result = $this->data_manager->reset_to_defaults();
        $defaults = $this->data_manager->get_defaults();

        wp_send_json_success([
            'message' => 'Reset to defaults successfully',
            'data' => $defaults,
        ]);
    }

    private function get_upload_temp_dir(): string {
        $dir = BMGF_DASHBOARD_PATH . 'tmp';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            file_put_contents($dir . '/.htaccess', 'Deny from all');
            file_put_contents($dir . '/index.php', '<?php // Silence is golden.');
        }
        return $dir;
    }

    private function save_parsed_temp(string $file_type, array $parsed): string {
        $dir = $this->get_upload_temp_dir();
        $filename = 'bmgf_' . $file_type . '_' . get_current_user_id() . '_' . time() . '.json';
        $filepath = $dir . '/' . $filename;
        file_put_contents($filepath, json_encode($parsed));
        return $filepath;
    }

    private function load_parsed_temp(string $filepath): ?array {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return null;
        }
        $real = realpath($filepath);
        $dir = realpath($this->get_upload_temp_dir());
        if ($real === false || $dir === false || strpos($real, $dir) !== 0) {
            return null;
        }
        $data = json_decode(file_get_contents($filepath), true);
        return is_array($data) ? $data : null;
    }

    public function ajax_upload_file(): void {
        check_ajax_referer('bmgf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        $file_type = sanitize_text_field($_POST['file_type'] ?? '');
        if (!in_array($file_type, ['institutions', 'courses'], true)) {
            wp_send_json_error(['message' => 'Invalid file type parameter.']);
            return;
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'No file uploaded.']);
            return;
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'Upload error code: ' . $file['error']]);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            wp_send_json_error(['message' => 'Invalid file type. Only .xlsx and .csv.']);
            return;
        }

        $parser_build = BMGF_XLSX_Parser::build_id();

        try {
            $preferred_sheet = $file_type === 'institutions' ? 'All_Institutions' : 'All_Courses';
            $parsed = BMGF_XLSX_Parser::parse($file['tmp_name'], $ext, $preferred_sheet);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Parse error: ' . $e->getMessage()]);
            return;
        }

        if (empty($parsed['rows'])) {
            wp_send_json_error(['message' => 'File was parsed but contains no data rows.']);
            return;
        }

        $required = $file_type === 'institutions'
            ? BMGF_Data_Mapper::INSTITUTION_REQUIRED
            : BMGF_Data_Mapper::COURSE_REQUIRED;

        $missing = BMGF_XLSX_Parser::validate_columns($parsed['headers'], $required);
        if (!empty($missing)) {
            wp_send_json_error(['message' => 'Missing required columns: ' . implode(', ', $missing)]);
            return;
        }

        try {
            $temp_path = $this->save_parsed_temp($file_type, $parsed);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Failed to store parsed data: ' . $e->getMessage()]);
            return;
        }

        wp_send_json_success([
            'message' => ucfirst($file_type) . ' file parsed successfully.',
            'transient_key' => $temp_path,
            'row_count' => count($parsed['rows']),
            'columns' => $parsed['headers'],
        ]);
    }

    public function ajax_apply_upload(): void {
        check_ajax_referer('bmgf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        @ini_set('memory_limit', '256M');
        @set_time_limit(300);

        $inst_key = sanitize_text_field($_POST['institutions_key'] ?? '');
        $courses_key = sanitize_text_field($_POST['courses_key'] ?? '');
        $preview_only = intval($_POST['preview_only'] ?? 0);

        $institutions = null;
        $courses = null;

        if ($inst_key !== '') {
            $institutions = $this->load_parsed_temp($inst_key);
            if ($institutions === null) {
                wp_send_json_error(['message' => 'Institutions data expired. Re-upload.']);
                return;
            }
        }

        if ($courses_key !== '') {
            $courses = $this->load_parsed_temp($courses_key);
            if ($courses === null) {
                wp_send_json_error(['message' => 'Courses data expired. Re-upload.']);
                return;
            }
        }

        if ($institutions === null && $courses === null) {
            wp_send_json_error(['message' => 'No uploaded data found.']);
            return;
        }

        try {
            $computed = BMGF_Data_Mapper::compute_all($institutions, $courses);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Computation error: ' . $e->getMessage()]);
            return;
        }

        $has_institutions_upload = ($institutions !== null);
        $has_courses_upload = ($courses !== null);
        $has_full_upload = ($has_institutions_upload && $has_courses_upload);

        $current_data = $this->data_manager->get_all_data();
        $sections_to_save = [];

        if ($has_full_upload) {
            $sections_to_save = $computed;
        } else {
            if ($has_institutions_upload) {
                foreach (['sector_data', 'top_institutions', 'institution_size_data'] as $section) {
                    $sections_to_save[$section] = $computed[$section] ?? [];
                }
                if (isset($computed['state_data']) && is_array($computed['state_data'])) {
                    $sections_to_save['state_data'] = $computed['state_data'];
                }
            }

            if ($has_courses_upload) {
                foreach (['regional_data', 'publishers', 'top_textbooks', 'period_data'] as $section) {
                    $sections_to_save[$section] = $computed[$section] ?? [];
                }
            }

            $kpis = $current_data['kpis'] ?? [];
            if ($has_institutions_upload) {
                foreach (['total_institutions','total_enrollment','calc1_enrollment','calc1_share','calc2_enrollment','calc2_share','total_fte_enrollment'] as $field) {
                    if (isset($computed['kpis'][$field])) $kpis[$field] = $computed['kpis'][$field];
                }
            }
            if ($has_courses_upload) {
                foreach (['total_textbooks','avg_textbook_price','avg_price_calc1','avg_price_calc2','commercial_share','oer_share'] as $field) {
                    if (isset($computed['kpis'][$field])) $kpis[$field] = $computed['kpis'][$field];
                }
            }
            $sections_to_save['kpis'] = $kpis;

            $filters = $current_data['filters'] ?? [];
            $filter_keys = ['states', 'regions', 'sectors'];
            if ($has_courses_upload) $filter_keys = array_merge($filter_keys, ['publishers', 'courses', 'price_ranges']);
            foreach (array_unique($filter_keys) as $key) {
                if (!empty($computed['filters'][$key]) && is_array($computed['filters'][$key])) {
                    $filters[$key] = $computed['filters'][$key];
                }
            }
            $sections_to_save['filters'] = $filters;
        }

        if ($preview_only) {
            $preview_source = $has_full_upload ? $computed : array_merge($current_data, $sections_to_save);
            $preview = BMGF_Data_Mapper::preview($preview_source);
            wp_send_json_success(['preview' => $preview, 'mode' => $has_full_upload ? 'full' : 'partial']);
            return;
        }

        foreach ($sections_to_save as $section => $data) {
            $this->data_manager->save_section($section, $data);
        }

        if ($inst_key !== '' && file_exists($inst_key)) @unlink($inst_key);
        if ($courses_key !== '' && file_exists($courses_key)) @unlink($courses_key);

        wp_send_json_success([
            'message' => $has_full_upload ? 'All data updated.' : 'Uploaded data applied.',
            'computed' => $sections_to_save,
            'updated_sections' => array_keys($sections_to_save),
            'mode' => $has_full_upload ? 'full' : 'partial',
        ]);
    }

    private function sanitize_section_data(string $section, mixed $data): array {
        if (!is_array($data)) return [];

        switch ($section) {
            case 'branding':
                return $this->sanitize_branding($data);
            case 'kpis':
                return $this->sanitize_kpis($data);
            case 'regional_data':
                return $this->sanitize_regional_data($data);
            case 'sector_data':
                return $this->sanitize_sector_data($data);
            case 'publishers':
                return $this->sanitize_publishers($data);
            case 'top_institutions':
                return $this->sanitize_top_items($data, ['name', 'enrollment']);
            case 'top_textbooks':
                return $this->sanitize_top_items($data, ['name', 'publisher', 'enrollment']);
            case 'period_data':
                return $this->sanitize_period_data($data);
            case 'institution_size_data':
                return $this->sanitize_size_data($data);
            case 'filters':
                return $this->sanitize_filters($data);
            default:
                return [];
        }
    }

    private function sanitize_branding(array $data): array {
        $defaults = $this->data_manager->get_defaults()['branding'];
        
        return [
            'company_name' => sanitize_text_field($data['company_name'] ?? '') ?: $defaults['company_name'],
            'dashboard_title_line1' => sanitize_text_field($data['dashboard_title_line1'] ?? '') ?: $defaults['dashboard_title_line1'],
            'dashboard_title_line2_highlight' => sanitize_text_field($data['dashboard_title_line2_highlight'] ?? '') ?: $defaults['dashboard_title_line2_highlight'],
            'dashboard_title_line2_normal' => sanitize_text_field($data['dashboard_title_line2_normal'] ?? '') ?: $defaults['dashboard_title_line2_normal'],
            'logo_url' => esc_url_raw($data['logo_url'] ?? '') ?: $defaults['logo_url'],
            // Permitimos el footer_logo vacío porque significa que debe heredar
            'footer_logo_url' => esc_url_raw($data['footer_logo_url'] ?? ''), 
            'primary_color' => sanitize_hex_color($data['primary_color'] ?? '') ?: $defaults['primary_color'],
            'secondary_color' => sanitize_hex_color($data['secondary_color'] ?? '') ?: $defaults['secondary_color'],
            'accent_color' => sanitize_hex_color($data['accent_color'] ?? '') ?: $defaults['accent_color'],
            'tertiary_color' => sanitize_hex_color($data['tertiary_color'] ?? '') ?: $defaults['tertiary_color'],
            'quaternary_color' => sanitize_hex_color($data['quaternary_color'] ?? '') ?: $defaults['quaternary_color'],
            'light_accent_color' => sanitize_hex_color($data['light_accent_color'] ?? '') ?: $defaults['light_accent_color'],
            'font_family' => sanitize_text_field($data['font_family'] ?? '') ?: $defaults['font_family'],
        ];
    }

    private function sanitize_kpis(array $data): array {
        $sanitized = [];
        $numeric_fields = ['total_institutions', 'total_enrollment', 'calc1_enrollment', 'calc1_share', 'calc2_enrollment', 'calc2_share', 'total_textbooks', 'avg_textbook_price', 'total_fte_enrollment', 'avg_price_calc1', 'avg_price_calc2', 'commercial_share', 'oer_share', 'digital_share', 'print_share'];
        foreach ($numeric_fields as $field) {
            if (isset($data[$field])) $sanitized[$field] = floatval($data[$field]);
        }
        return $sanitized;
    }

    private function sanitize_regional_data(array $data): array {
        $sanitized = [];
        foreach (['calc1', 'calc2'] as $calc) {
            if (isset($data[$calc]) && is_array($data[$calc])) {
                $sanitized[$calc] = [];
                foreach ($data[$calc] as $item) {
                    $sanitized[$calc][] = ['name' => sanitize_text_field($item['name'] ?? ''), 'percentage' => intval($item['percentage'] ?? 0), 'value' => intval($item['value'] ?? 0)];
                }
            }
        }
        return $sanitized;
    }

    private function sanitize_sector_data(array $data): array {
        $sanitized = [];
        foreach (['calc1', 'calc2'] as $calc) {
            if (isset($data[$calc]) && is_array($data[$calc])) {
                $sanitized[$calc] = [];
                foreach ($data[$calc] as $item) {
                    $sanitized[$calc][] = ['name' => sanitize_text_field($item['name'] ?? ''), 'percentage' => intval($item['percentage'] ?? 0), 'value' => intval($item['value'] ?? 0)];
                }
            }
        }
        return $sanitized;
    }

    private function sanitize_publishers(array $data): array {
        $sanitized = [];
        foreach ($data as $item) {
            $sanitized[] = ['name' => sanitize_text_field($item['name'] ?? ''), 'market_share' => intval($item['market_share'] ?? 0), 'enrollment' => intval($item['enrollment'] ?? 0), 'avg_price' => floatval($item['avg_price'] ?? 0), 'color' => sanitize_hex_color($item['color'] ?? '#000000') ?: '#000000'];
        }
        return $sanitized;
    }

    private function sanitize_top_items(array $data, array $fields): array {
        $sanitized = [];
        foreach ($data as $item) {
            $sanitized_item = [];
            foreach ($fields as $field) {
                if ($field === 'enrollment') {
                    $sanitized_item[$field] = intval($item[$field] ?? 0);
                } else {
                    $sanitized_item[$field] = sanitize_text_field($item[$field] ?? '');
                }
            }
            $sanitized[] = $sanitized_item;
        }
        return $sanitized;
    }

    private function sanitize_period_data(array $data): array {
        $sanitized = [];
        foreach ($data as $item) {
            $sanitized[] = ['period' => sanitize_text_field($item['period'] ?? ''), 'calc1' => intval($item['calc1'] ?? 0), 'calc2' => intval($item['calc2'] ?? 0)];
        }
        return $sanitized;
    }

    private function sanitize_size_data(array $data): array {
        $sanitized = [];
        foreach ($data as $item) {
            $sanitized[] = ['size' => sanitize_text_field($item['size'] ?? ''), 'calc1' => intval($item['calc1'] ?? 0), 'calc2' => intval($item['calc2'] ?? 0)];
        }
        return $sanitized;
    }

    private function sanitize_filters(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $values) {
            if (is_array($values)) {
                $sanitized[sanitize_key($key)] = array_map('sanitize_text_field', $values);
            }
        }
        return $sanitized;
    }
}