<?php
/**
 * Plugin Name: BMGF Calculus Market Dashboard
 * Plugin URI: https://partnerinpublishing.com
 * Description: Interactive dashboard for Math Education Market Analysis - Calculus textbook market data visualization.
 * Version: 32.0.1
 * Author: Team Dev PIP
 * Author URI: https://partnerinpublishing.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bmgf-calculus-dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BMGF_DASHBOARD_VERSION', '2.1.2');
define('BMGF_DASHBOARD_PATH', plugin_dir_path(__FILE__));
define('BMGF_DASHBOARD_URL', plugin_dir_url(__FILE__));

// Load required classes
require_once BMGF_DASHBOARD_PATH . 'includes/class-bmgf-data-manager.php';
require_once BMGF_DASHBOARD_PATH . 'includes/class-bmgf-xlsx-parser.php';
require_once BMGF_DASHBOARD_PATH . 'includes/class-bmgf-data-mapper.php';
require_once BMGF_DASHBOARD_PATH . 'includes/class-bmgf-admin.php';

/**
 * Main plugin class
 */
class BMGF_Calculus_Dashboard {

    private static $instance = null;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_chart_requests']);
        add_filter('query_vars', [$this, 'add_query_vars']);

        // Initialize admin panel
        if (is_admin()) {
            BMGF_Admin::get_instance();
        }
    }

    public function register_shortcodes(): void {
        add_shortcode('bmgf_dashboard', [$this, 'render_dashboard']);
        add_shortcode('bmgf_dashboard_page', [$this, 'render_dashboard_page']);
        add_shortcode('bmgf_dashboard_review', [$this, 'render_review_dashboard']);
    }

    public function enqueue_assets(): void {
        if (has_shortcode(get_post()->post_content ?? '', 'bmgf_dashboard') ||
            has_shortcode(get_post()->post_content ?? '', 'bmgf_dashboard_page') ||
            has_shortcode(get_post()->post_content ?? '', 'bmgf_dashboard_review')) {

            wp_enqueue_style(
                'bmgf-dashboard-style',
                BMGF_DASHBOARD_URL . 'assets/css/dashboard.css',
                [],
                BMGF_DASHBOARD_VERSION
            );

            wp_enqueue_style(
                'bmgf-google-fonts',
                'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;1,400&family=Roboto:wght@400;500;700&family=Open+Sans:wght@400;600;700&family=Montserrat:wght@400;500;600;700&family=Lato:wght@400;700&family=Poppins:wght@400;500;600;700&display=swap',
                [],
                null
            );
        }
    }

    public function register_rewrite_rules(): void {
        add_rewrite_rule(
            '^bmgf-charts/([^/]+)/?$',
            'index.php?bmgf_chart=$matches[1]',
            'top'
        );
    }

    public function add_query_vars(array $vars): array {
        $vars[] = 'bmgf_chart';
        $vars[] = 'bmgf_annotate';
        return $vars;
    }

    public function handle_chart_requests(): void {
        $chart = get_query_var('bmgf_chart');
        $annotation_mode = isset($_GET['annotate']) && $_GET['annotate'] === '1';

        if (!empty($chart)) {
            $chart = sanitize_file_name($chart);
            $chart_path = BMGF_DASHBOARD_PATH . 'charts/' . $chart;

            if (file_exists($chart_path) && pathinfo($chart_path, PATHINFO_EXTENSION) === 'html') {
                $content = file_get_contents($chart_path);

                // Update asset paths AND globally inject Logos + CSS branding variables
                $content = $this->update_asset_paths($content, $annotation_mode);

                // Inject dynamic data from admin panel
                $content = $this->inject_dynamic_data($content);

                // Inject source-notes layer for review mode only.
                $annotatable_pages = [
                    'cover_page.html',
                    'tab2_enrollment_analysis.html',
                    'tab3_institutions_analysis.html',
                    'tab4_textbook_analysis.html',
                ];
                if ($annotation_mode && in_array($chart, $annotatable_pages, true)) {
                    $content = $this->inject_annotation_tools($content);
                }

                header('Content-Type: text/html; charset=utf-8');
                echo $content;
                exit;
            }
        }
    }

    private function inject_dynamic_data(string $content): string {
        $data_manager = BMGF_Data_Manager::get_instance();
        $js_data = $data_manager->get_js_data();

        $script = '<script>window.BMGF_DATA = ' . json_encode($js_data) . ';</script>';

        if (strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', $script . "\n</head>", $content);
        } elseif (strpos($content, '<body') !== false) {
            $content = preg_replace('/(<body[^>]*>)/', '$1' . "\n" . $script, $content);
        }

        return $content;
    }

    private function update_asset_paths(string $content, bool $annotation_mode = false): string {
        $plugin_url = BMGF_DASHBOARD_URL;
        $charts_url = home_url('/bmgf-charts/');
        $asset_version = rawurlencode(BMGF_DASHBOARD_VERSION);
        $annotation_suffix = $annotation_mode ? '&annotate=1' : '';

        $data_manager = BMGF_Data_Manager::get_instance();
        $branding = $data_manager->get_section('branding');

        // 1. Logic for Logo Replacement (Applying inheritance)
        $main_logo_url = !empty($branding['logo_url']) ? $branding['logo_url'] : $plugin_url . 'assets/Learnvia - principal logo.png';
        $footer_logo_url = !empty($branding['footer_logo_url']) ? $branding['footer_logo_url'] : $main_logo_url;

        // Replace classic static paths found in HTML charts with new Dynamic Logos
        $content = str_replace('../assets/Learnvia - principal logo.png', $main_logo_url, $content);
        $content = str_replace('../assets/logo_pip.png', $footer_logo_url, $content);
        $content = str_replace('assets/Learnvia - principal logo.png', $main_logo_url, $content);
        $content = str_replace('assets/logo_pip.png', $footer_logo_url, $content);

        // Update other relative paths to assets
        $content = str_replace('../assets/', $plugin_url . 'assets/', $content);
        $content = str_replace('src="assets/', 'src="' . $plugin_url . 'assets/', $content);

        // Update JavaScript file sources
        $content = preg_replace(
            '/src="([a-zA-Z0-9_-]+\.js)"/',
            'src="' . $plugin_url . 'charts/$1?v=' . $asset_version . '"',
            $content
        );

        // Update chart iframe sources
        $content = preg_replace(
            '/src="([^"]+\.html)"/',
            'src="' . $charts_url . '$1?v=' . $asset_version . '"',
            $content
        );

        // Update navigation hrefs
        $content = preg_replace(
            "/window\.location\.href='([^']+\.html)'/",
            "window.location.href='" . $charts_url . "$1?v=" . $asset_version . $annotation_suffix . "'",
            $content
        );

        // 2. Globally inject Custom Brand Colors and Fonts to ALL charts and map iframes!
        $css_vars = sprintf(
            '<style id="bmgf-dynamic-branding">
                :root {
                    --deep-insight: %s !important;
                    --scholar-blue: %s !important;
                    --coastal-clarity: %s !important;
                    --warm-thoughts: %s !important;
                    --sky-logic: %s !important;
                    --soft-lecture: %s !important;
                    --lavender: %s !important;
                    --white: #FFFFFF !important;
                    --background: #F6F6F6 !important;
                    --text-dark: %s !important;
                    --dark-blue: %s !important;
                }
                body, text, tspan, div, span, p, h1, h2, h3, h4, h5, h6, a, button, input, select, textarea {
                    font-family: "%s", sans-serif !important;
                }
                .kpi-number { color: var(--deep-insight) !important; }
                .kpi-card { background: var(--white) !important; border-color: var(--lavender) !important; }
                .kpi-card.dark { background: var(--dark-blue) !important; color: white !important; }
                .kpi-card.dark .kpi-number, .kpi-card.dark .kpi-label { color: white !important; }
                .kpi-card.highlight { background: var(--lavender) !important; }
            </style>',
            esc_attr($branding['primary_color'] ?? '#008384'),     // --deep-insight
            esc_attr($branding['secondary_color'] ?? '#234A5D'),   // --scholar-blue
            esc_attr($branding['accent_color'] ?? '#7FBFC0'),      // --coastal-clarity
            esc_attr($branding['tertiary_color'] ?? '#4A81A8'),    // --warm-thoughts
            esc_attr($branding['tertiary_color'] ?? '#4A81A8'),    // --sky-logic
            esc_attr($branding['quaternary_color'] ?? '#92A4CF'),  // --soft-lecture
            esc_attr($branding['light_accent_color'] ?? '#D3DEF6'),// --lavender
            esc_attr($branding['secondary_color'] ?? '#234A5D'),   // --text-dark
            esc_attr($branding['secondary_color'] ?? '#244B5E'),   // --dark-blue
            esc_attr($branding['font_family'] ?? 'Inter Tight')    // font-family
        );

        if (strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', $css_vars . "\n</head>", $content);
        }

        return $content;
    }

    private function inject_annotation_tools(string $content): string {
        $injected = <<<'HTML'
<style id="bmgf-annotation-style">
    #bmgf-annotation-toggle {
        position: fixed;
        right: 18px;
        top: 18px;
        z-index: 99998;
        border: none;
        border-radius: 999px;
        padding: 10px 14px;
        font: 600 13px/1 'Inter Tight', sans-serif;
        background: #234A5D;
        color: #fff;
        cursor: pointer;
        box-shadow: 0 6px 16px rgba(0,0,0,.2);
    }
    #bmgf-annotation-panel {
        position: fixed;
        right: 18px;
        top: 62px;
        width: 360px;
        max-height: calc(100vh - 84px);
        overflow: auto;
        background: #fff;
        border: 1px solid #d7dde4;
        border-radius: 14px;
        box-shadow: 0 12px 28px rgba(0,0,0,.18);
        z-index: 99997;
        padding: 12px;
        font-family: 'Inter Tight', sans-serif;
    }
    #bmgf-annotation-panel[hidden] { display: none; }
    .bmgf-annotation-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .bmgf-annotation-title {
        font-size: 14px;
        font-weight: 700;
        color: #234A5D;
    }
    .bmgf-annotation-actions button {
        border: 1px solid #cfd8e3;
        background: #f8fafc;
        border-radius: 8px;
        padding: 6px 8px;
        font-size: 11px;
        cursor: pointer;
        color: #234A5D;
    }
    .bmgf-annotation-empty {
        font-size: 12px;
        color: #6b7280;
        padding: 10px 2px;
    }
    .bmg-annotation-item {
        border-top: 1px solid #edf0f3;
        padding-top: 10px;
        margin-top: 10px;
    }
    .bmg-annotation-label {
        font-size: 12px;
        font-weight: 600;
        color: #234A5D;
        margin-bottom: 6px;
    }
    .bmg-annotation-item textarea {
        width: 100%;
        min-height: 64px;
        resize: vertical;
        border: 1px solid #cfd8e3;
        border-radius: 8px;
        padding: 8px;
        font-size: 12px;
        font-family: 'Inter Tight', sans-serif;
    }
    .bmg-annotation-meta {
        margin-top: 4px;
        color: #94a3b8;
        font-size: 10px;
    }
</style>
<script id="bmgf-annotation-script">
(function() {
    if (window.__BMGF_ANNOTATION_LOADED__) return;
    window.__BMGF_ANNOTATION_LOADED__ = true;

    const REVIEW_SELECTORS = [
        '.kpi-label',
        '.chart-title-top',
        '.chart-title-bottom',
        '.bmgf-kpi-label',
        '.bmgf-chart-card-title'
    ];

    const pageId = (location.pathname.split('/').pop() || 'dashboard').toLowerCase();
    const storagePrefix = 'bmgf_metric_source_notes::' + pageId + '::';

    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function slugify(value) {
        return normalizeText(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'item';
    }

    function annotationKey(id) {
        return storagePrefix + id;
    }

    function customItemsKey() {
        return storagePrefix + 'custom_items';
    }

    function loadCustomItems() {
        try {
            const raw = localStorage.getItem(customItemsKey());
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function saveCustomItems(items) {
        localStorage.setItem(customItemsKey(), JSON.stringify(items || []));
    }

    function getTargets() {
        const nodes = [];
        REVIEW_SELECTORS.forEach(selector => {
            document.querySelectorAll(selector).forEach(el => nodes.push(el));
        });

        const out = [];
        const seen = new Map();
        nodes.forEach((el, idx) => {
            const label = normalizeText(el.textContent || '');
            if (!label) return;
            const slug = slugify(label);
            const count = (seen.get(slug) || 0) + 1;
            seen.set(slug, count);
            const id = count > 1 ? (slug + '-' + count) : slug;
            out.push({ id, label, element: el, index: idx });
        });
        return out;
    }

    function createPanel(targets) {
        const toggle = document.createElement('button');
        toggle.id = 'bmgf-annotation-toggle';
        toggle.type = 'button';
        toggle.textContent = 'Metric Notes';

        const panel = document.createElement('aside');
        panel.id = 'bmgf-annotation-panel';
        panel.hidden = false;

        const head = document.createElement('div');
        head.className = 'bmgf-annotation-head';
        head.innerHTML = '<div class="bmgf-annotation-title">Metric Source Notes</div>';

        const actions = document.createElement('div');
        actions.className = 'bmgf-annotation-actions';
        const downloadBtn = document.createElement('button');
        downloadBtn.type = 'button';
        downloadBtn.textContent = 'Download JSON';
        actions.appendChild(downloadBtn);

        const addOtherBtn = document.createElement('button');
        addOtherBtn.type = 'button';
        addOtherBtn.textContent = 'Add Other';
        actions.appendChild(addOtherBtn);
        head.appendChild(actions);
        panel.appendChild(head);

        if (!targets.length) {
            const empty = document.createElement('div');
            empty.className = 'bmgf-annotation-empty';
            empty.textContent = 'No metrics found on this page.';
            panel.appendChild(empty);
        }

        targets.forEach(target => {
            const wrap = document.createElement('div');
            wrap.className = 'bmg-annotation-item';

            const label = document.createElement('div');
            label.className = 'bmg-annotation-label';
            label.textContent = target.label;

            const textarea = document.createElement('textarea');
            textarea.placeholder = 'Ej: Esta métrica viene de la suma de la columna X, página Y, archivo Z.';
            textarea.value = localStorage.getItem(annotationKey(target.id)) || '';
            textarea.addEventListener('input', () => {
                localStorage.setItem(annotationKey(target.id), textarea.value);
            });

            const meta = document.createElement('div');
            meta.className = 'bmg-annotation-meta';
            meta.textContent = 'id: ' + target.id;

            wrap.appendChild(label);
            wrap.appendChild(textarea);
            wrap.appendChild(meta);
            panel.appendChild(wrap);
        });

        const customSection = document.createElement('div');
        customSection.className = 'bmg-annotation-item';
        const customTitle = document.createElement('div');
        customTitle.className = 'bmg-annotation-label';
        customTitle.textContent = 'Otros';
        customSection.appendChild(customTitle);
        panel.appendChild(customSection);

        const customList = document.createElement('div');
        customSection.appendChild(customList);

        function renderCustomItems() {
            customList.innerHTML = '';
            const items = loadCustomItems();
            items.forEach((item, idx) => {
                const wrap = document.createElement('div');
                wrap.style.marginTop = '8px';

                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.placeholder = 'Nombre de métrica o filtro';
                nameInput.value = item.name || '';
                nameInput.style.width = '100%';
                nameInput.style.border = '1px solid #cfd8e3';
                nameInput.style.borderRadius = '8px';
                nameInput.style.padding = '8px';
                nameInput.style.fontSize = '12px';
                nameInput.style.fontFamily = "'Inter Tight', sans-serif";

                const noteInput = document.createElement('textarea');
                noteInput.placeholder = 'Descripción del origen de datos';
                noteInput.value = item.note || '';
                noteInput.style.marginTop = '6px';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.textContent = 'Remove';
                removeBtn.style.marginTop = '6px';

                nameInput.addEventListener('input', () => {
                    const all = loadCustomItems();
                    all[idx] = { ...(all[idx] || {}), name: nameInput.value, note: noteInput.value };
                    saveCustomItems(all);
                });

                noteInput.addEventListener('input', () => {
                    const all = loadCustomItems();
                    all[idx] = { ...(all[idx] || {}), name: nameInput.value, note: noteInput.value };
                    saveCustomItems(all);
                });

                removeBtn.addEventListener('click', () => {
                    const all = loadCustomItems();
                    all.splice(idx, 1);
                    saveCustomItems(all);
                    renderCustomItems();
                });

                wrap.appendChild(nameInput);
                wrap.appendChild(noteInput);
                wrap.appendChild(removeBtn);
                customList.appendChild(wrap);
            });
        }

        addOtherBtn.addEventListener('click', () => {
            const items = loadCustomItems();
            items.push({ name: '', note: '' });
            saveCustomItems(items);
            renderCustomItems();
        });

        renderCustomItems();

        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
        });

        function buildPayload() {
            const otherItems = loadCustomItems()
                .map((item, idx) => ({
                    id: 'other-' + (idx + 1),
                    metric: String(item && item.name ? item.name : '').trim(),
                    note: String(item && item.note ? item.note : '').trim()
                }))
                .filter(item => item.metric !== '' || item.note !== '');

            return {
                page: pageId,
                generated_at: new Date().toISOString(),
                notes: targets.map(target => ({
                    id: target.id,
                    metric: target.label,
                    note: localStorage.getItem(annotationKey(target.id)) || ''
                })),
                other_notes: otherItems
            };
        }

        downloadBtn.addEventListener('click', () => {
            const payload = buildPayload();
            const text = JSON.stringify(payload, null, 2);
            const blob = new Blob([text], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'metric-notes-' + pageId + '.json';
            a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        });

        document.body.appendChild(toggle);
        document.body.appendChild(panel);
    }

    function propagateAnnotateToLinks() {
        document.querySelectorAll('a[href$=".html"], a[href*=".html?"]').forEach(a => {
            try {
                const href = a.getAttribute('href');
                if (!href || /^https?:\/\//i.test(href)) return;
                const u = new URL(href, window.location.href);
                if (!u.pathname.endsWith('.html')) return;
                u.searchParams.set('annotate', '1');
                a.setAttribute('href', u.toString());
            } catch (e) {}
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        // Review mode must keep top navigation visible even inside shortcode iframe.
        document.documentElement.classList.remove('in-iframe');
        propagateAnnotateToLinks();
        createPanel(getTargets());
    });
})();
</script>
HTML;

        if (strpos($content, '</body>') !== false) {
            return str_replace('</body>', $injected . "\n</body>", $content);
        }

        return $content . $injected;
    }

    public function render_dashboard(array $atts = []): string {
        $atts = shortcode_atts([
            'height' => '1900px',
            'page' => 'cover'
        ], $atts);

        $page_map = [
            'cover' => 'index.html',
            'enrollment' => 'tab2_enrollment_analysis.html',
            'institutions' => 'tab3_institutions_analysis.html',
            'textbooks' => 'tab4_textbook_analysis.html'
        ];

        $chart_file = $page_map[$atts['page']] ?? 'index.html';

        if ($atts['page'] === 'cover') {
            return $this->render_cover_page($atts['height']);
        }

        $chart_url = home_url('/bmgf-charts/' . $chart_file . '?v=' . rawurlencode(BMGF_DASHBOARD_VERSION));

        return sprintf(
            '<div class="bmgf-dashboard-wrapper" style="width:100%%;max-width:1280px;margin:0 auto;">
                <iframe src="%s" style="width:100%%;height:%s;border:none;display:block;" title="BMGF Calculus Dashboard"></iframe>
            </div>',
            esc_url($chart_url),
            esc_attr($atts['height'])
        );
    }

    public function render_dashboard_page(array $atts = []): string {
        return $this->render_cover_page('auto');
    }

    public function render_review_dashboard(array $atts = []): string {
        $atts = shortcode_atts([
            'height' => '1900px',
            'page' => 'cover'
        ], $atts);

        $page_map = [
            'cover' => 'cover_page.html',
            'enrollment' => 'tab2_enrollment_analysis.html',
            'institutions' => 'tab3_institutions_analysis.html',
            'textbooks' => 'tab4_textbook_analysis.html'
        ];

        $chart_file = $page_map[$atts['page']] ?? 'cover_page.html';
        $chart_url = home_url('/bmgf-charts/' . $chart_file . '?v=' . rawurlencode(BMGF_DASHBOARD_VERSION) . '&annotate=1');

        return sprintf(
            '<div class="bmgf-dashboard-wrapper bmgf-dashboard-review" style="width:100%%;max-width:1280px;margin:0 auto;">
                <iframe src="%s" style="width:100%%;height:%s;border:none;display:block;" title="BMGF Calculus Dashboard Review"></iframe>
            </div>',
            esc_url($chart_url),
            esc_attr($atts['height'])
        );
    }

    private function render_cover_page(string $height): string {
        $plugin_url = BMGF_DASHBOARD_URL;
        $charts_url = home_url('/bmgf-charts/');
        $charts_version_query = '?v=' . rawurlencode(BMGF_DASHBOARD_VERSION);

        $data_manager = BMGF_Data_Manager::get_instance();
        $kpis = $data_manager->get_section('kpis');
        $branding = $data_manager->get_section('branding');

        // Logic for Logo Inheritance on Cover Page
        $main_logo_url = !empty($branding['logo_url']) ? $branding['logo_url'] : $plugin_url . 'assets/Learnvia - principal logo.png';
        $footer_logo_url = !empty($branding['footer_logo_url']) ? $branding['footer_logo_url'] : $main_logo_url;

        ob_start();
        ?>
        <div class="bmgf-dashboard-container">
            <style>
                .bmgf-dashboard-container {
                    --deep-insight: <?php echo esc_attr($branding['primary_color'] ?? '#008384'); ?>;
                    --scholar-blue: <?php echo esc_attr($branding['secondary_color'] ?? '#234A5D'); ?>;
                    --coastal-clarity: <?php echo esc_attr($branding['accent_color'] ?? '#7FBFC0'); ?>;
                    --warm-thoughts: <?php echo esc_attr($branding['tertiary_color'] ?? '#4A81A8'); ?>;
                    --sky-logic: <?php echo esc_attr($branding['tertiary_color'] ?? '#4A81A8'); ?>;
                    --soft-lecture: <?php echo esc_attr($branding['quaternary_color'] ?? '#92A4CF'); ?>;
                    --lavender: <?php echo esc_attr($branding['light_accent_color'] ?? '#D3DEF6'); ?>;
                    --white: #FFFFFF;
                    --background: #F6F6F6;
                    --text-dark: <?php echo esc_attr($branding['secondary_color'] ?? '#234A5D'); ?>;
                    --dark-blue: <?php echo esc_attr($branding['secondary_color'] ?? '#244B5E'); ?>;
                    font-family: '<?php echo esc_attr($branding['font_family'] ?? 'Inter Tight'); ?>', sans-serif !important;
                    background: var(--background);
                    color: var(--text-dark);
                }

                .bmgf-dashboard-container * {
                    box-sizing: border-box;
                    font-family: inherit;
                }

                .bmgf-frame {
                    width: 1280px;
                    height: 1880px;
                    margin: 0 auto;
                    position: relative;
                    background: var(--background);
                    padding-bottom: 30px;
                }

                .bmgf-header {
                    position: absolute;
                    width: 1210px;
                    height: 79px;
                    left: 35px;
                    top: 23px;
                    background: var(--white);
                    box-shadow: 0px 0px 60px rgba(0, 131, 132, 0.23);
                    border-radius: 100px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 20px;
                    z-index: 100;
                }

                .bmgf-nav-tab {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 19.37px;
                    padding: 8.72px 20px;
                    height: 53.27px;
                    border-radius: 96.86px;
                    font-weight: 600;
                    font-size: 18px;
                    line-height: 25px;
                    color: var(--scholar-blue);
                    background: transparent;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-decoration: none;
                }

                .bmgf-nav-tab:hover {
                    background: rgba(0, 131, 132, 0.1);
                }

                .bmgf-nav-tab.active {
                    background: var(--deep-insight);
                    color: white;
                }

                .bmgf-nav-tab svg {
                    width: 25px;
                    height: 25px;
                }

                .bmgf-hero-section {
                    position: absolute;
                    width: 1209px;
                    height: 252px;
                    left: 36px;
                    top: 138px;
                    background: radial-gradient(46.73% 153.31% at 97.68% 123.41%, rgba(0, 128, 130, 0.11) 0%, #FFFFFF 100%);
                    border-radius: 23px;
                    overflow: visible;
                }

                .bmgf-logo {
                    position: absolute;
                    width: 140px;
                    height: 43.24px;
                    left: 58px;
                    top: 28px;
                    object-fit: contain;
                    z-index: 2;
                }

                .bmgf-hero-title {
                    position: absolute;
                    width: 575px;
                    left: 58px;
                    top: 85px;
                    font-weight: 700;
                    font-size: 55px;
                    line-height: 55px;
                    letter-spacing: 0;
                    color: var(--sky-logic);
                    z-index: 2;
                    margin: 0;
                    padding: 0;
                }

                .bmgf-hero-title .normal {
                    color: var(--scholar-blue);
                }

                .bmgf-hero-title .highlight {
                    font-family: 'Playfair Display', serif !important;
                    font-weight: 400;
                    font-style: italic;
                    color: var(--sky-logic);
                }

                .bmgf-laptop-container {
                    position: absolute;
                    width: 492px;
                    height: 328px;
                    right: -35px;
                    top: -36px;
                    z-index: 1;
                }

                .bmgf-laptop-image {
                    width: 100%;
                    height: 100%;
                    object-fit: contain;
                }

                .bmgf-kpi-row {
                    position: absolute;
                    left: 35px;
                    top: 417px;
                    display: flex;
                    flex-direction: row;
                    gap: 14px;
                }

                .bmgf-kpi-card {
                    width: 189.63px;
                    height: 110px;
                    border-radius: 20px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }

                .bmgf-kpi-card:hover {
                    transform: translateY(-4px) scale(1.02);
                    box-shadow: 0 12px 24px rgba(35, 74, 93, 0.15);
                }

                .bmgf-kpi-number {
                    font-weight: 700;
                    font-size: 30px;
                    line-height: 55px;
                    text-align: center;
                }

                .bmgf-kpi-label {
                    font-weight: 400;
                    font-size: 16px;
                    line-height: 24px;
                    text-align: center;
                }

                .bmgf-kpi-card.lavender { background: var(--lavender); color: var(--text-dark); }
                .bmgf-kpi-card.white { background: #FFFFFF; color: var(--text-dark); }
                .bmgf-kpi-card.dark { background: var(--dark-blue); color: white; }
                .bmgf-kpi-card.teal { background: var(--coastal-clarity); color: var(--text-dark); }

                .bmgf-chart-card {
                    position: absolute;
                    width: 589px;
                    height: 550px;
                    background: var(--white);
                    border-radius: 31.95px;
                    overflow: hidden;
                }

                .bmgf-chart-card.left {
                    left: 35px;
                    top: 560px;
                }

                .bmgf-chart-card.right {
                    left: 656px;
                    top: 560px;
                }

                .bmgf-chart-card-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 20px 24px;
                    border-bottom: 2px solid var(--lavender);
                }

                .bmgf-chart-card-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    font-size: 16px;
                    color: white;
                }

                .bmgf-chart-card-icon.calc1 { background: var(--deep-insight); }
                .bmgf-chart-card-icon.calc2 { background: var(--sky-logic); }

                .bmgf-chart-card-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: var(--scholar-blue);
                }

                .bmgf-chart-iframe {
                    width: 100%;
                    height: calc(100% - 70px);
                    border: none;
                }

                .bmgf-map-section {
                    position: absolute;
                    width: 1210px;
                    height: 620px;
                    left: 35px;
                    top: 1140px;
                    border-radius: 30px;
                    overflow: hidden;
                    box-shadow: 0 4px 20px rgba(35, 74, 93, 0.1);
                }

                .bmgf-map-iframe {
                    width: 100%;
                    height: 100%;
                    border: none;
                }

                .bmgf-footer {
                    position: absolute;
                    width: 1210px;
                    height: 70px;
                    left: 35px;
                    top: 1780px;
                    display: flex;
                    align-items: center;
                }

                .bmgf-footer-text {
                    font-weight: 400;
                    font-size: 13px;
                    line-height: 21px;
                    color: var(--scholar-blue);
                }

                .bmgf-footer-line {
                    flex: 1;
                    height: 1px;
                    background: var(--scholar-blue);
                    margin: 0 15px 0 20px;
                }

                .bmgf-footer-logo {
                    width: 180px;
                    height: 60px;
                    object-fit: contain;
                    margin-right: 0;
                }

                @media (max-width: 1300px) {
                    .bmgf-frame {
                        transform: scale(0.9);
                        transform-origin: top center;
                    }
                }

                @media (max-width: 1100px) {
                    .bmgf-frame {
                        transform: scale(0.75);
                        transform-origin: top center;
                    }
                }
            </style>

            <div class="bmgf-frame">
                <header class="bmgf-header">
                    <button class="bmgf-nav-tab active" data-tab="cover" onclick="bmgfSwitchTab('cover')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.02 2.84L3.63 7.04c-.78.62-1.28 1.93-1.11 2.91l1.33 7.96c.24 1.42 1.6 2.57 3.04 2.57h11.22c1.43 0 2.8-1.16 3.04-2.57l1.33-7.96c.16-.98-.34-2.29-1.11-2.91l-5.39-4.2c-1.08-.84-2.84-.84-3.96.01z"/>
                            <path d="M12 15.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                        </svg>
                        Cover Page
                    </button>
                    <button class="bmgf-nav-tab" data-tab="enrollment" onclick="bmgfSwitchTab('enrollment')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.16 10.87c-.1-.01-.22-.01-.33 0a4.42 4.42 0 01-4.27-4.43C4.56 3.99 6.54 2 9 2a4.435 4.435 0 01.16 8.87zM16.41 4c1.94 0 3.5 1.57 3.5 3.5 0 1.89-1.5 3.43-3.37 3.5a1.13 1.13 0 00-.26 0M4.16 14.56c-2.42 1.62-2.42 4.26 0 5.87 2.75 1.84 7.26 1.84 10.01 0 2.42-1.62 2.42-4.26 0-5.87-2.74-1.83-7.25-1.83-10.01 0zM18.34 20c.72-.15 1.4-.44 1.96-.87 1.56-1.17 1.56-3.1 0-4.27-.55-.42-1.22-.7-1.93-.86"/>
                        </svg>
                        Student Enrollment Analysis
                    </button>
                    <button class="bmgf-nav-tab" data-tab="institutions" onclick="bmgfSwitchTab('institutions')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16"/>
                        </svg>
                        Institutions Analysis
                    </button>
                    <button class="bmgf-nav-tab" data-tab="textbooks" onclick="bmgfSwitchTab('textbooks')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.74V4.67c0-1.2-.98-2.09-2.17-1.99h-.06c-2.1.18-5.29 1.25-7.07 2.37l-.17.11c-.29.18-.77.18-1.06 0l-.25-.15C9.44 3.9 6.26 2.84 4.16 2.67 2.97 2.57 2 3.47 2 4.66v12.08c0 .96.78 1.86 1.74 1.98l.29.04c2.17.29 5.52 1.39 7.44 2.44l.04.02c.27.15.7.15.96 0 1.92-1.06 5.28-2.17 7.46-2.46l.33-.04c.96-.12 1.74-1.02 1.74-1.98zM12 5.49v15M7.75 8.49H5.5M8.5 11.49h-3"/>
                        </svg>
                        Textbook Analysis
                    </button>
                </header>

                <iframe id="bmgf-tab-iframe" class="bmgf-tab-iframe" style="display:none;" title="Dashboard Content"></iframe>

                <div id="bmgf-cover-content">
                <section class="bmgf-hero-section">
                    <img class="bmgf-logo" src="<?php echo esc_url($main_logo_url); ?>" alt="<?php echo esc_attr($branding['company_name'] ?? 'Company Logo'); ?>">
                    <h1 class="bmgf-hero-title">
                        <span class="normal"><?php echo esc_html($branding['dashboard_title_line1'] ?? 'Math Education'); ?></span><br>
                        <span class="highlight"><?php echo esc_html($branding['dashboard_title_line2_highlight'] ?? 'Market'); ?></span> <span class="normal"><?php echo esc_html($branding['dashboard_title_line2_normal'] ?? 'Analysis'); ?></span>
                    </h1>
                    <div class="bmgf-laptop-container">
                        <img class="bmgf-laptop-image" src="<?php echo esc_url($plugin_url . 'assets/ordenador_cover_page.png'); ?>" alt="Math Education Analysis">
                    </div>
                </section>

                <div class="bmgf-kpi-row">
                    <div class="bmgf-kpi-card white">
                        <div class="bmgf-kpi-number"><?php echo esc_html(number_format($kpis['total_institutions'])); ?></div>
                        <div class="bmgf-kpi-label">Total Institutions</div>
                    </div>
                    <div class="bmgf-kpi-card dark">
                        <div class="bmgf-kpi-number"><?php echo esc_html(number_format($kpis['total_enrollment'])); ?></div>
                        <div class="bmgf-kpi-label">Total Calculus Enrollment</div>
                    </div>
                    <div class="bmgf-kpi-card lavender">
                        <div class="bmgf-kpi-number"><?php echo esc_html(number_format($kpis['calc1_enrollment'])); ?></div>
                        <div class="bmgf-kpi-label">Calculus I Enrollment</div>
                    </div>
                    <div class="bmgf-kpi-card white">
                        <div class="bmgf-kpi-number"><?php echo esc_html($kpis['calc1_share']); ?>%</div>
                        <div class="bmgf-kpi-label">Calc I Share</div>
                    </div>
                    <div class="bmgf-kpi-card teal">
                        <div class="bmgf-kpi-number"><?php echo esc_html(number_format($kpis['calc2_enrollment'])); ?></div>
                        <div class="bmgf-kpi-label">Calculus II Enrollment</div>
                    </div>
                    <div class="bmgf-kpi-card white">
                        <div class="bmgf-kpi-number"><?php echo esc_html($kpis['calc2_share']); ?>%</div>
                        <div class="bmgf-kpi-label">Calc II Share</div>
                    </div>
                </div>

                <div class="bmgf-chart-card left">
                    <div class="bmgf-chart-card-header">
                        <div class="bmgf-chart-card-icon calc1">I</div>
                        <div class="bmgf-chart-card-title">Calculus I Distribution</div>
                    </div>
                    <iframe class="bmgf-chart-iframe" src="<?php echo esc_url($charts_url . 'cover_calc1_enrollment.html' . $charts_version_query); ?>" title="Calculus I Distribution"></iframe>
                </div>

                <div class="bmgf-chart-card right">
                    <div class="bmgf-chart-card-header">
                        <div class="bmgf-chart-card-icon calc2">II</div>
                        <div class="bmgf-chart-card-title">Calculus II Distribution</div>
                    </div>
                    <iframe class="bmgf-chart-iframe" src="<?php echo esc_url($charts_url . 'cover_calc2_enrollment.html' . $charts_version_query); ?>" title="Calculus II Distribution"></iframe>
                </div>

                <section class="bmgf-map-section">
                    <iframe class="bmgf-map-iframe" src="<?php echo esc_url($charts_url . 'g1_premium_map_v3.html' . $charts_version_query); ?>" title="Interactive Map"></iframe>
                </section>

                <footer class="bmgf-footer">
                    <div class="bmgf-footer-text">Developed by <?php echo esc_html($branding['company_name'] ?? 'Partner In Publishing'); ?></div>
                    <div class="bmgf-footer-line"></div>
                    <img class="bmgf-footer-logo" src="<?php echo esc_url($footer_logo_url); ?>" alt="<?php echo esc_attr($branding['company_name'] ?? 'Company Logo'); ?>">
                </footer>
                </div>
            </div>

            <script>
            (function() {
                var chartsUrl = <?php echo json_encode($charts_url); ?>;
                var chartsVersionQuery = <?php echo json_encode($charts_version_query); ?>;
                var tabUrls = {
                    'cover': null,
                    'enrollment': chartsUrl + 'tab2_enrollment_analysis.html' + chartsVersionQuery,
                    'institutions': chartsUrl + 'tab3_institutions_analysis.html' + chartsVersionQuery,
                    'textbooks': chartsUrl + 'tab4_textbook_analysis.html' + chartsVersionQuery
                };
                var tabIframe = document.getElementById('bmgf-tab-iframe');
                var frame = document.querySelector('.bmgf-frame');
                var iframeTopOffset = 125;
                var frameBottomPadding = 30;
                var coverFrameHeight = 1880;

                function bmgfResizeTabIframe() {
                    if (!tabIframe || tabIframe.style.display === 'none') {
                        return;
                    }
                    try {
                        var iframeDoc = tabIframe.contentDocument || (tabIframe.contentWindow && tabIframe.contentWindow.document);
                        if (!iframeDoc) {
                            return;
                        }
                        var body = iframeDoc.body;
                        var html = iframeDoc.documentElement;
                        if (!body || !html) {
                            return;
                        }
                        var contentHeight = Math.max(
                            body.scrollHeight,
                            body.offsetHeight,
                            html.clientHeight,
                            html.scrollHeight,
                            html.offsetHeight
                        );
                        if (contentHeight > 0) {
                            tabIframe.style.height = contentHeight + 'px';
                            if (frame) {
                                frame.style.height = (iframeTopOffset + contentHeight + frameBottomPadding) + 'px';
                            }
                        }
                    } catch (e) { }
                }

                if (tabIframe) {
                    tabIframe.addEventListener('load', function() {
                        bmgfResizeTabIframe();
                        setTimeout(bmgfResizeTabIframe, 150);
                        setTimeout(bmgfResizeTabIframe, 500);
                        setTimeout(bmgfResizeTabIframe, 1200);
                    });
                }
                window.addEventListener('resize', bmgfResizeTabIframe);

                window.bmgfSwitchTab = function(tab) {
                    var coverContent = document.getElementById('bmgf-cover-content');
                    var allTabs = document.querySelectorAll('.bmgf-nav-tab');

                    allTabs.forEach(function(t) {
                        t.classList.remove('active');
                        if (t.getAttribute('data-tab') === tab) {
                            t.classList.add('active');
                        }
                    });

                    if (tab === 'cover') {
                        coverContent.style.display = 'block';
                        tabIframe.style.display = 'none';
                        tabIframe.src = '';
                        if (frame) {
                            frame.style.height = coverFrameHeight + 'px';
                        }
                    } else {
                        coverContent.style.display = 'none';
                        tabIframe.src = tabUrls[tab];
                        tabIframe.style.display = 'block';
                        tabIframe.style.height = '0';
                    }
                };
            })();
            </script>

            <style>
                #bmgf-cover-content { display: block; }
                .bmgf-tab-iframe {
                    position: absolute;
                    left: 0;
                    top: 125px;
                    width: 100%;
                    height: 0;
                    border: none;
                    display: block;
                    margin: 0 auto;
                    background: var(--background);
                    overflow: hidden;
                }
            </style>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function activate(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bmgf_dashboard_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            section_key varchar(50) NOT NULL,
            section_data longtext NOT NULL,
            PRIMARY KEY  (section_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $existing_data = get_option('bmgf_dashboard_data');
        if ($existing_data && is_array($existing_data)) {
            foreach ($existing_data as $key => $data) {
                $wpdb->replace(
                    $table_name,
                    [
                        'section_key' => $key,
                        'section_data' => wp_json_encode($data)
                    ],
                    ['%s', '%s']
                );
            }
            delete_option('bmgf_dashboard_data');
        }

        $instance = self::get_instance();
        $instance->register_rewrite_rules();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}

add_action('plugins_loaded', function() {
    BMGF_Calculus_Dashboard::get_instance();
});

register_activation_hook(__FILE__, ['BMGF_Calculus_Dashboard', 'activate']);
register_deactivation_hook(__FILE__, ['BMGF_Calculus_Dashboard', 'deactivate']);