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
                .kpi-number { color: var(--deep-insight) ; }
                .kpi-card { background: var(--white) ; border-color: var(--lavender); }
                .kpi-card.dark { background: var(--dark-blue) ; color: white ; }
                .kpi-card.dark .kpi-number, .kpi-card.dark .kpi-label { color: white ; }
                .kpi-card.highlight { background: var(--lavender) ; }
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
                    font-family: '<?php echo esc_attr($branding['font_family'] ?? 'Raleway'); ?>', sans-serif !important;
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
                    justify-content: space-around;
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
                    fill:black;
                }

                .active svg{
                    fill:white;
                }

                .bmgf-hero-section {
                    position: absolute;
                    width: 1209px;
                    height: 252px;
                    left: 36px;
                    top: 138px;
                    background: radial-gradient(46.73% 153.31% at 97.68% 123.41%, rgba(0, 128, 130, 0.11) 0%, #FFFFFF 100%);
                    border-radius: 23px;
                    background-image: url("<?php echo esc_url($plugin_url . 'assets/bg-hero.png'); ?>");
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
                    font-weight: 300;
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
                    font-family: 'Raleway', serif !important;
                    font-weight: 800;
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

                .bmgf-kpi-card.lavender, .kpi-card.kpi-3 { background: var(--lavender) !important; color: var(--text-dark); }
                .bmgf-kpi-card.white , .kpi-card.kpi-2{ background: #FFFFFF !important; color: var(--text-dark); }
                .bmgf-kpi-card.dark { background: var(--sky-logic) !important; color: white; }
                .bmgf-kpi-card.teal, .kpi-card.kpi-1 { background: var(--coastal-clarity) !important; color: var(--text-dark); }

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

                .left .bmgf-chart-card-header{
                    background:  var(--sky-logic);
                }
                .right .bmgf-chart-card-header{
                    background: var(--coastal-clarity);
                }

                .bmgf-chart-card-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 50px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    font-size: 16px;
                    color: black;
                    background: white;
                }

                .bmgf-chart-card-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: white;
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
                
                /* Loader Styles */
                .bmgf-loader-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: var(--background);
                    z-index: 9999;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    border-radius: 30px;
                }
                .bmgf-loader-spinner {
                    border: 8px solid var(--lavender);
                    border-top: 8px solid var(--deep-insight);
                    border-radius: 50%;
                    margin-top: calc(20%);
                    width: 60px;
                    height: 60px;
                    animation: bmgf-spin 1s linear infinite;
                }
                @keyframes bmgf-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .bmgf-loader-text {
                    margin-top: 20px;
                    font-size: 18px;
                    font-weight: 600;
                    color: var(--scholar-blue);
                }
            </style>

            <div class="bmgf-frame">
                <div id="bmgf-loader" class="bmgf-loader-overlay">
                    <div class="bmgf-loader-spinner"></div>
                    <div class="bmgf-loader-text">Loading Dashboard Data...</div>
                </div>

                <header class="bmgf-header">
                    <button class="bmgf-nav-tab active" data-tab="cover" onclick="bmgfSwitchTab('cover')">
                        <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16.7969 5.80469C16.7969 6.28672 17.1734 6.66406 17.6562 6.66406H21.8906V6.42969C21.8906 5.80469 21.6484 5.21094 21.2031 4.77344L18.6797 2.25C18.2422 1.80469 17.6484 1.5625 17.0234 1.5625H16.7969V5.80469Z" fill="white"/>
                        <path d="M17.6563 8.07031C16.4063 8.07031 15.3906 7.05469 15.3906 5.80469V1.5625H5.45312C4.15625 1.5625 3.10938 2.60938 3.10938 3.90625V21.0938C3.10938 22.3906 4.15625 23.4375 5.45312 23.4375H19.5469C20.8438 23.4375 21.8906 22.3906 21.8906 21.1016V8.07031H17.6563ZM17.4516 19.9383H15.0703C14.682 19.9383 14.3672 19.6234 14.3672 19.2352C14.3672 18.8469 14.682 18.532 15.0703 18.532H17.4516C17.8398 18.532 18.1547 18.8469 18.1547 19.2352C18.1547 19.6234 17.8398 19.9383 17.4516 19.9383ZM17.4516 15.1898H7.54844C7.16016 15.1898 6.84531 14.875 6.84531 14.4867C6.84531 14.0984 7.16016 13.7836 7.54844 13.7836H17.4516C17.8398 13.7836 18.1547 14.0984 18.1547 14.4867C18.1547 14.875 17.8398 15.1898 17.4516 15.1898ZM17.4516 12.2758H7.54844C7.16016 12.2758 6.84531 11.9609 6.84531 11.5727C6.84531 11.1844 7.16016 10.8695 7.54844 10.8695H17.4516C17.8398 10.8695 18.1547 11.1844 18.1547 11.5727C18.1547 11.9609 17.8398 12.2758 17.4516 12.2758Z" />
                        </svg>
                        Cover Page
                    </button>
                    <button class="bmgf-nav-tab" data-tab="enrollment" onclick="bmgfSwitchTab('enrollment')">
                        <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.9792 12.7604C15.1433 12.7604 17.7083 10.1954 17.7083 7.03125C17.7083 3.86712 15.1433 1.30208 11.9792 1.30208C8.81504 1.30208 6.25 3.86712 6.25 7.03125C6.25 10.1954 8.81504 12.7604 11.9792 12.7604Z" />
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M13.4698 22.6687C12.3833 21.5062 11.7188 19.9448 11.7188 18.2292C11.7188 16.4781 12.4115 14.8885 13.5365 13.7177C13.0292 13.6792 12.5083 13.6583 11.9792 13.6583C8.51876 13.6583 5.45522 14.524 3.55314 15.8208C2.10209 16.8104 1.30209 18.0677 1.30209 19.3875V20.899C1.30209 21.3677 1.48855 21.8187 1.82084 22.151C2.15314 22.4823 2.60314 22.6698 3.07293 22.6698L13.4698 22.6687Z" />
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M18.2292 12.7604C15.2104 12.7604 12.7604 15.2104 12.7604 18.2292C12.7604 21.2479 15.2104 23.6979 18.2292 23.6979C21.2479 23.6979 23.6979 21.2479 23.6979 18.2292C23.6979 15.2104 21.2479 12.7604 18.2292 12.7604ZM19.0104 17.4479V16.4063C19.0104 15.975 18.6604 15.625 18.2292 15.625C17.7979 15.625 17.4479 15.975 17.4479 16.4063V17.4479H16.4063C15.975 17.4479 15.625 17.7979 15.625 18.2292C15.625 18.6604 15.975 19.0104 16.4063 19.0104H17.4479V20.0521C17.4479 20.4833 17.7979 20.8333 18.2292 20.8333C18.6604 20.8333 19.0104 20.4833 19.0104 20.0521V19.0104H20.0521C20.4834 19.0104 20.8334 18.6604 20.8334 18.2292C20.8334 17.7979 20.4834 17.4479 20.0521 17.4479H19.0104Z" />
                        </svg>
                        Student Enrollment Analysis
                    </button>
                    <button class="bmgf-nav-tab" data-tab="institutions" onclick="bmgfSwitchTab('institutions')">
                        <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g clip-path="url(#clip0_120_167)">
                                <path
                                    d="M17.0555 0.351549H13.0956C12.91 0.0195176 12.495 -0.0976698 12.1678 0.087877C11.953 0.205065 11.8163 0.434557 11.8163 0.68358V4.58495L7.82214 6.94823C7.49011 7.14354 7.28503 7.50487 7.28503 7.89061V22.4072H9.35535V16.0791C9.35535 15.3027 9.98523 14.6728 10.7665 14.6728H14.243C15.0194 14.6728 15.6493 15.3027 15.6493 16.0791V22.4072H17.7196V7.89061C17.7196 7.50487 17.5145 7.14354 17.1825 6.94823L13.1835 4.58495V3.72069H17.0555C17.4218 3.72069 17.7147 3.42772 17.7147 3.06151V1.01073C17.7147 0.649401 17.4218 0.351549 17.0555 0.351549ZM14.326 8.99901C14.326 10.0049 13.5106 10.8252 12.4999 10.8252C11.4891 10.8252 10.6737 10.0098 10.6737 8.99901C10.6737 7.98827 11.4891 7.17284 12.4999 7.17284C13.5106 7.17284 14.326 7.99315 14.326 8.99901Z"
                                     />
                                <path
                                    d="M0.7323 14.6045V23.9014C0.7323 24.5068 1.22546 25 1.83093 25H6.47937V12.2217L1.54773 13.5498C1.06433 13.6767 0.7323 14.1113 0.7323 14.6045ZM2.44128 15.6592C2.44128 15.3711 2.67566 15.1367 2.96375 15.1367H4.67273C4.96082 15.1367 5.19519 15.3711 5.19519 15.6592V17.373C5.19519 17.6611 4.96082 17.8955 4.67273 17.8955H2.96375C2.67566 17.8955 2.44128 17.6611 2.44128 17.373V15.6592ZM2.44128 20.2978C2.44128 20.0098 2.67566 19.7754 2.96375 19.7754H4.67273C4.96082 19.7754 5.19519 20.0098 5.19519 20.2978V22.0117C5.19519 22.2998 4.96082 22.5342 4.67273 22.5342H2.96375C2.67566 22.5342 2.44128 22.2998 2.44128 22.0117C2.44128 22.0117 2.44128 22.0117 2.44128 22.0068V20.2978Z"
                                     />
                                <path
                                    d="M23.457 13.5449L18.5253 12.2168V25H23.1738C23.7792 25 24.2675 24.5068 24.2675 23.9014V14.6045C24.2675 14.1113 23.9355 13.6767 23.457 13.5449ZM22.5585 22.0068C22.5585 22.2949 22.3242 22.5293 22.0361 22.5293H20.3271C20.039 22.5293 19.8046 22.2949 19.8046 22.0068V20.293C19.8046 20.0049 20.039 19.7705 20.3271 19.7705H22.0361C22.3242 19.7705 22.5585 20.0049 22.5585 20.293V22.0068ZM22.5585 17.3682C22.5585 17.6562 22.3242 17.8906 22.0361 17.8906H20.3271C20.039 17.8906 19.8046 17.6562 19.8046 17.3682V15.6592C19.8046 15.3711 20.039 15.1367 20.3271 15.1367H22.0361C22.3242 15.1367 22.5585 15.3711 22.5585 15.6592V17.3682Z"
                                     />
                                <path d="M7.28503 23.2129H17.7196V25H7.28503V23.2129Z"  />
                                <path
                                    d="M14.2382 15.4785H10.7617C10.4296 15.4785 10.1611 15.7471 10.1611 16.0791V22.4072H14.8437V16.0791C14.8388 15.7471 14.5703 15.4785 14.2382 15.4785Z"
                                     />
                            </g>
                            <defs>
                                <clipPath id="clip0_120_167">
                                    <rect width="25" height="25" />
                                </clipPath>
                            </defs>
                        </svg>
                        Institutions Analysis
                    </button>
                    <button class="bmgf-nav-tab" data-tab="textbooks" onclick="bmgfSwitchTab('textbooks')">
                        <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.85129 4.57127C5.80221 4.31673 4.79163 5.11142 4.79163 6.19094V15.8161C4.79163 16.5531 5.27573 17.2026 5.98202 17.4133L12.0833 19.2326V5.84075L6.85129 4.57127ZM7.17717 8.52565C6.9559 8.46242 6.82777 8.23181 6.891 8.01054C6.95421 7.78927 7.18483 7.66115 7.40608 7.72438L10.3228 8.55771C10.544 8.62092 10.6721 8.85154 10.6089 9.07281C10.5457 9.29406 10.3151 9.42219 10.0938 9.35898L7.17717 8.52565ZM6.891 10.9272C6.82777 11.1485 6.9559 11.3791 7.17717 11.4423L10.0938 12.2756C10.3151 12.3389 10.5457 12.2107 10.6089 11.9895C10.6721 11.7682 10.544 11.5376 10.3228 11.4744L7.40608 10.641C7.18483 10.5778 6.95421 10.7059 6.891 10.9272ZM7.17717 14.359C6.9559 14.2958 6.82777 14.0651 6.891 13.8439C6.95421 13.6226 7.18483 13.4945 7.40608 13.5577L10.3228 14.391C10.544 14.4543 10.6721 14.6849 10.6089 14.9061C10.5457 15.1274 10.3151 15.2555 10.0938 15.1923L7.17717 14.359Z" />
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12.9166 5.84075V19.2326L19.0179 17.4133C19.7242 17.2026 20.2083 16.5531 20.2083 15.8161V6.19094C20.2083 5.11142 19.1977 4.31673 18.1486 4.57127L12.9166 5.84075ZM18.1089 8.01054C18.1721 8.23181 18.044 8.46242 17.8228 8.52565L14.9061 9.35898C14.6848 9.42219 14.4542 9.29406 14.391 9.07281C14.3278 8.85154 14.4559 8.62092 14.6772 8.55771L17.5938 7.72438C17.8151 7.66115 18.0457 7.78927 18.1089 8.01054ZM17.8228 11.4423C18.044 11.3791 18.1721 11.1485 18.1089 10.9272C18.0457 10.7059 17.8151 10.5778 17.5938 10.641L14.6772 11.4744C14.4559 11.5376 14.3278 11.7682 14.391 11.9895C14.4542 12.2107 14.6848 12.3389 14.9061 12.2756L17.8228 11.4423ZM18.1089 13.8439C18.1721 14.0651 18.044 14.2958 17.8228 14.359L14.9061 15.1923C14.6848 15.2555 14.4542 15.1274 14.391 14.9061C14.3278 14.6849 14.4559 14.4543 14.6772 14.391L17.5938 13.5577C17.8151 13.4945 18.0457 13.6226 18.1089 13.8439Z" />
                            <path d="M5.74392 18.2119L12.0833 20.1022V20.7786L3.6169 19.7313C2.73894 19.6227 2.08331 18.882 2.08331 18.0076V8.61238C2.08331 7.60104 2.94973 6.80675 3.95831 6.87971V15.8161C3.95831 16.9216 4.68446 17.8959 5.74392 18.2119Z" />
                            <path d="M21.3831 19.7313L12.9166 20.7786V20.1022L19.256 18.2119C20.3155 17.8959 21.0416 16.9216 21.0416 15.8161V6.87971C22.0502 6.80675 22.9166 7.60104 22.9166 8.61238V18.0076C22.9166 18.882 22.261 19.6227 21.3831 19.7313Z" />
                        </svg>
                        Textbook Analysis
                    </button>
                </header>

                <iframe id="bmgf-iframe-enrollment" class="bmgf-tab-iframe" src="<?php echo esc_url($charts_url . 'tab2_enrollment_analysis.html' . $charts_version_query); ?>" style="display:none;" title="Student Enrollment Analysis"></iframe>
                <iframe id="bmgf-iframe-institutions" class="bmgf-tab-iframe" src="<?php echo esc_url($charts_url . 'tab3_institutions_analysis.html' . $charts_version_query); ?>" style="display:none;" title="Institutions Analysis"></iframe>
                <iframe id="bmgf-iframe-textbooks" class="bmgf-tab-iframe" src="<?php echo esc_url($charts_url . 'tab4_textbook_analysis.html' . $charts_version_query); ?>" style="display:none;" title="Textbook Analysis"></iframe>

                <div id="bmgf-cover-content">
                <section class="bmgf-hero-section">
                    <img class="bmgf-logo" src="<?php echo esc_url($main_logo_url); ?>" alt="<?php echo esc_attr($branding['company_name'] ?? 'Company Logo'); ?>">
                    <h1 class="bmgf-hero-title">
                        <span class="normal"><?php echo esc_html($branding['dashboard_title_line1'] ?? 'Math Education'); ?></span><br>
                        <span class="highlight"><?php echo esc_html($branding['dashboard_title_line2_highlight'] ?? 'Market'); ?></span> <span class="normal"><?php echo esc_html($branding['dashboard_title_line2_normal'] ?? 'Analysis'); ?></span>
                    </h1>
                    <div class="bmgf-laptop-container" style="display:none;">
                        <img class="bmgf-laptop-image" src="<?php echo esc_url($plugin_url . 'assets/ordenador_cover_page.png'); ?>" alt="Math Education Analysis">
                    </div>
                </section>

                <div class="bmgf-kpi-row">
                    <div class="bmgf-kpi-card lavender">
                        <div class="bmgf-kpi-number"><?php echo esc_html(number_format($kpis['total_institutions'])); ?></div>
                        <div class="bmgf-kpi-label">Total Institutions</div>
                    </div>
                    <div class="bmgf-kpi-card white">
                        <div class="bmgf-kpi-number"><?php echo esc_html(number_format($kpis['total_enrollment'])); ?></div>
                        <div class="bmgf-kpi-label">Total Calculus Enrollment</div>
                    </div>
                    <div class="bmgf-kpi-card dark">
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
                var iframes = {
                    'enrollment': document.getElementById('bmgf-iframe-enrollment'),
                    'institutions': document.getElementById('bmgf-iframe-institutions'),
                    'textbooks': document.getElementById('bmgf-iframe-textbooks')
                };
                
                var coverIframes = document.querySelectorAll('#bmgf-cover-content iframe');
                var loader = document.getElementById('bmgf-loader');
                var frame = document.querySelector('.bmgf-frame');
                var iframeTopOffset = 125;
                var frameBottomPadding = 30;
                var coverFrameHeight = 1880;
                
                // Track load progress to dismiss loader
                var totalFrames = 3 + coverIframes.length;
                var loadedFrames = 0;

                function frameLoaded() {
                    loadedFrames++;
                    if (loadedFrames >= totalFrames) {
                        loader.style.opacity = '0';
                        setTimeout(function() { loader.style.display = 'none'; }, 300); // fade out effect
                    }
                }

                // Attach load events
                Object.values(iframes).forEach(function(iframe) {
                    if (iframe) {
                        iframe.addEventListener('load', function() {
                            bmgfResizeTabIframe(iframe);
                            frameLoaded();
                            // Retry resizing in case internal content renders slightly later
                            setTimeout(function(){ bmgfResizeTabIframe(iframe); }, 150);
                            setTimeout(function(){ bmgfResizeTabIframe(iframe); }, 500);
                            setTimeout(function(){ bmgfResizeTabIframe(iframe); }, 1200);
                        });
                    }
                });

                coverIframes.forEach(function(iframe) {
                    iframe.addEventListener('load', frameLoaded);
                });

                // Safety fallback: dismiss loader anyway after 8 seconds 
                setTimeout(function() {
                    if (loader) {
                        loader.style.opacity = '0';
                        setTimeout(function() { loader.style.display = 'none'; }, 300);
                    }
                }, 8000);

                function bmgfResizeTabIframe(targetIframe) {
                    if (!targetIframe || targetIframe.style.display === 'none') {
                        return;
                    }
                    try {
                        var iframeDoc = targetIframe.contentDocument || (targetIframe.contentWindow && targetIframe.contentWindow.document);
                        if (!iframeDoc) return;
                        var body = iframeDoc.body;
                        var html = iframeDoc.documentElement;
                        if (!body || !html) return;
                        var contentHeight = Math.max(
                            body.scrollHeight,
                            body.offsetHeight,
                            html.clientHeight,
                            html.scrollHeight,
                            html.offsetHeight
                        );
                        if (contentHeight > 0) {
                            targetIframe.style.height = contentHeight + 'px';
                            if (frame) {
                                frame.style.height = (iframeTopOffset + contentHeight + frameBottomPadding) + 'px';
                            }
                        }
                    } catch (e) { }
                }

                window.addEventListener('resize', function() {
                    var activeTab = document.querySelector('.bmgf-nav-tab.active').getAttribute('data-tab');
                    if (activeTab !== 'cover' && iframes[activeTab]) {
                        bmgfResizeTabIframe(iframes[activeTab]);
                    }
                });

                window.bmgfSwitchTab = function(tab) {
                    var coverContent = document.getElementById('bmgf-cover-content');
                    var allTabs = document.querySelectorAll('.bmgf-nav-tab');

                    allTabs.forEach(function(t) {
                        t.classList.remove('active');
                        if (t.getAttribute('data-tab') === tab) {
                            t.classList.add('active');
                        }
                    });

                    // Hide all iframes
                    Object.values(iframes).forEach(function(iframe) {
                        if (iframe) iframe.style.display = 'none';
                    });

                    if (tab === 'cover') {
                        coverContent.style.display = 'block';
                        if (frame) {
                            frame.style.height = coverFrameHeight + 'px';
                        }
                    } else {
                        coverContent.style.display = 'none';
                        if (iframes[tab]) {
                            iframes[tab].style.display = 'block';
                            // Give browser a tick to render display:block before resizing
                            setTimeout(function() {
                                bmgfResizeTabIframe(iframes[tab]);
                            }, 50);
                        }
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
                .bmgf-loader-overlay {
                    transition: opacity 0.3s ease;
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