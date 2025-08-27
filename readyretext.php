<?php
/**
 * Plugin Name:       Ready ReText
 * Description:       یک افزونه پیشرفته برای جستجو و جایگزینی متن در تمام بخش‌های سایت وردپرس با پنل تنظیمات کامل.
 * Version:           1.1.0
 * Author:            Ready Studio & Gemini
 * Author URI:        https://readystudio.ir/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       readyretext
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class ReadyReText {

    private $options;

    /**
     * Constructor: Hooks into WordPress.
     */
    public function __construct() {
        // Load options from the database with defaults
        $this->options = get_option('readyretext_settings', [
            'scope' => 'frontend',
            'rules' => [],
        ]);

        // Add settings page to the admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);

        /**
     * Enqueues admin styles for the settings page.
     */
    public function enqueue_admin_styles($hook) {
        // Only load on our plugin's settings page
        if ('settings_page_readyretext' !== $hook) {
            return;
        }
        $plugin_url = plugin_dir_url(__FILE__);
        wp_enqueue_style(
            'readyretext-admin-styles',
            $plugin_url . 'assets/admin-styles.css',
            [],
            '1.1.0' // Plugin version
        );
    }
        // Execute the replacement logic based on the selected scope
        $this->init_replacement_hooks();
    }

    /**
     * Adds the settings page to the WordPress admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            'تنظیمات جایگزینی متن',      // Page Title
            'Ready ReText',             // Menu Title
            'manage_options',           // Capability
            'readyretext',              // Menu Slug
            [$this, 'render_settings_page'] // Callback function
        );
    }

    /**
     * Renders the HTML for the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>در این صفحه می‌توانید قوانین جایگزینی متن در سایت را مدیریت کنید.</p>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('readyretext_settings_group');
                do_settings_sections('readyretext');
                submit_button('ذخیره تغییرات');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registers the settings, sections, and fields for the settings page.
     */
    public function register_settings() {
        register_setting('readyretext_settings_group', 'readyretext_settings', [$this, 'sanitize_settings']);

        // General Settings Section
        add_settings_section(
            'readyretext_general_section', 'تنظیمات عمومی', null, 'readyretext'
        );

        add_settings_field(
            'readyretext_scope_field', 'محدوده اعمال', [$this, 'render_scope_field'], 'readyretext', 'readyretext_general_section'
        );

        // Rules Section
        add_settings_section(
            'readyretext_rules_section', 'قوانین جایگزینی',
            function() { echo '<p>قوانین مورد نظر برای جایگزینی متن را در اینجا وارد کنید. برای الگوهای پیچیده، گزینه "Regex" را فعال کنید.</p>'; },
            'readyretext'
        );

        add_settings_field(
            'readyretext_rules_field', 'لیست قوانین', [$this, 'render_rules_field'], 'readyretext', 'readyretext_rules_section'
        );
    }

    /**
     * Sanitizes the settings data before saving.
     */
    public function sanitize_settings($input) {
        $new_input = [];

        $allowed_scopes = ['all', 'frontend', 'admin'];
        $new_input['scope'] = in_array($input['scope'], $allowed_scopes) ? $input['scope'] : 'frontend';

        if (isset($input['rules']) && is_array($input['rules'])) {
            $new_input['rules'] = array_values(array_filter(array_map(function($rule) {
                if (empty(trim($rule['find']))) {
                    return null;
                }
                return [
                    'find'             => sanitize_text_field(stripslashes($rule['find'])),
                    'replace'          => wp_kses_post(stripslashes($rule['replace'])), // Allow safe HTML
                    'is_regex'         => isset($rule['is_regex']),
                    'case_insensitive' => isset($rule['case_insensitive']),
                ];
            }, $input['rules'])));
        } else {
            $new_input['rules'] = [];
        }

        return $new_input;
    }

    /**
     * Renders the scope selection field.
     */
    public function render_scope_field() {
        $scope = $this->options['scope'] ?? 'frontend';
        ?>
        <fieldset>
            <label><input type="radio" name="readyretext_settings[scope]" value="frontend" <?php checked($scope, 'frontend'); ?>> فقط بخش کاربری (Frontend)</label><br>
            <label><input type="radio" name="readyretext_settings[scope]" value="admin" <?php checked($scope, 'admin'); ?>> فقط بخش مدیریت (Admin)</label><br>
            <label><input type="radio" name="readyretext_settings[scope]" value="all" <?php checked($scope, 'all'); ?>> کل سایت (Frontend + Admin)</label>
        </fieldset>
        <?php
    }

    /**
     * Renders the dynamic rules fields.
     */
    public function render_rules_field() {
        $rules = $this->options['rules'] ?? [];
        ?>
        <div id="readyretext-rules-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:35%;">متن اصلی (یا الگوی Regex)</th>
                        <th style="width:35%;">متن جایگزین (HTML مجاز است)</th>
                        <th style="width:10%; text-align:center;">Regex</th>
                        <th style="width:10%; text-align:center;">حساس به حروف</th>
                        <th style="width:10%;"></th>
                    </tr>
                </thead>
                <tbody id="readyretext-rules-container">
                    <?php if (empty($rules)) : ?>
                        <tr class="readyretext-rule-row no-rules-row"><td colspan="5">هیچ قانونی تعریف نشده است.</td></tr>
                    <?php else : ?>
                        <?php foreach ($rules as $index => $rule) : ?>
                            <tr class="readyretext-rule-row">
                                <td><input type="text" class="large-text" name="readyretext_settings[rules][<?php echo $index; ?>][find]" value="<?php echo esc_attr($rule['find']); ?>"></td>
                                <td><input type="text" class="large-text" name="readyretext_settings[rules][<?php echo $index; ?>][replace]" value="<?php echo esc_attr($rule['replace']); ?>"></td>
                                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][<?php echo $index; ?>][is_regex]" <?php checked($rule['is_regex'] ?? false, true); ?>></td>
                                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][<?php echo $index; ?>][case_insensitive]" <?php checked($rule['case_insensitive'] ?? false, true); ?>></td>
                                <td><button type="button" class="button button-secondary readyretext-remove-rule">حذف</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p style="margin-top: 15px;"><button type="button" class="button button-primary" id="readyretext-add-rule">افزودن قانون جدید</button></p>
        </div>

        <script type="text/template" id="readyretext-rule-template">
            <tr class="readyretext-rule-row">
                <td><input type="text" class="large-text" name="readyretext_settings[rules][{index}][find]"></td>
                <td><input type="text" class="large-text" name="readyretext_settings[rules][{index}][replace]"></td>
                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][{index}][is_regex]"></td>
                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][{index}][case_insensitive]" checked></td>
                <td><button type="button" class="button button-secondary readyretext-remove-rule">حذف</button></td>
            </tr>
        </script>
        
        <script>
        jQuery(document).ready(function($) {
            let ruleIndex = <?php echo count($rules); ?>;
            $('#readyretext-add-rule').on('click', function() {
                $('.no-rules-row').remove();
                let template = $('#readyretext-rule-template').html().replace(/{index}/g, ruleIndex++);
                $('#readyretext-rules-container').append(template);
            });
            $('#readyretext-rules-wrapper').on('click', '.readyretext-remove-rule', function() {
                $(this).closest('.readyretext-rule-row').remove();
                if ($('.readyretext-rule-row').length === 0) {
                     $('#readyretext-rules-container').append('<tr class="readyretext-rule-row no-rules-row"><td colspan="5">هیچ قانونی تعریف نشده است.</td></tr>');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Adds a "Settings" link to the plugin's action links.
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=readyretext">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Initializes the replacement logic based on scope.
     */
    private function init_replacement_hooks() {
        $scope = $this->options['scope'] ?? 'frontend';
        $rules = $this->options['rules'] ?? [];

        if (empty($rules) || ($scope === 'frontend' && is_admin()) || ($scope === 'admin' && !is_admin())) {
            return;
        }

        // Prepare patterns and replacements intelligently
        $patterns = [];
        $replacements = [];
        foreach ($rules as $rule) {
            $flags = 'u'; // Always use unicode support
            if (!empty($rule['case_insensitive'])) {
                $flags .= 'i';
            }

            if (!empty($rule['is_regex'])) {
                // User provided a full regex pattern
                $patterns[] = $rule['find'];
            } else {
                // Build a safe regex for a simple word
                $patterns[] = '/\b' . preg_quote($rule['find'], '/') . '\b/' . $flags;
            }
            $replacements[] = $rule['replace'];
        }
        
        if (empty($patterns)) return;

        $is_urlish = fn($str) => is_string($str) && $str !== '' && preg_match('~(^[a-z]+://|://|www\.|/|\\\|\.php\b|\.html\b|\.htm\b|^#)~i', $str);
        
        $replace_plain = fn($text) => (!is_string($text) || $text === '' || $is_urlish($text)) ? $text : preg_replace($patterns, $replacements, $text);

        $replace_html_textnodes = function ($html) use ($patterns, $replacements) {
            if (!is_string($html) || trim($html) === '') return $html;
            if (strip_tags($html) === $html) return preg_replace($patterns, $replacements, $html);

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            if (!$loaded) return $html;

            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') as $node) {
                if (trim($node->nodeValue) === '') continue;
                $newNodeValue = preg_replace($patterns, $replacements, $node->nodeValue);
                if ($newNodeValue !== $node->nodeValue) $node->nodeValue = $newNodeValue;
            }
            
            $output = $dom->saveHTML();
            return preg_replace('/^<\?xml.*?\?>/i', '', $output);
        };

        // ===== WordPress Filters =====
        add_filter('gettext', $replace_plain, 20);
        add_filter('ngettext', fn($s, $p, $n) => (1 != $n ? $replace_plain($p) : $replace_plain($s)), 20, 3);

        $html_filters = [
            'the_content', 'the_excerpt', 'comment_text', 'widget_text', 'widget_block_content',
            'the_tags', 'the_category', 'the_author', 'wp_nav_menu_items', 'wp_list_pages',
            'the_archive_title', 'the_archive_description',
            'woocommerce_product_title', 'woocommerce_short_description',
        ];
        foreach ($html_filters as $hook) add_filter($hook, $replace_html_textnodes, 20);

        add_filter('the_title', $replace_plain, 20);
        add_filter('document_title_parts', fn($p) => is_array($p) ? array_map($replace_plain, $p) : $p, 20);
        add_filter('nav_menu_item_title', $replace_plain, 20);
        
        add_filter('bloginfo', function ($output, $show) use ($replace_plain) {
            $cache_key = 'rrt_bloginfo_' . md5($show . serialize($this->options['rules']));
            if (false === ($cached = get_transient($cache_key))) {
                $cached = $replace_plain($output);
                set_transient($cache_key, $cached, HOUR_IN_SECONDS);
            }
            return $cached;
        }, 20, 2);
        
        if (function_exists('acf_add_filter')) {
            add_filter('acf/format_value', fn($v) => is_string($v) ? (($v !== strip_tags($v)) ? $replace_html_textnodes($v) : $replace_plain($v)) : $v, 20);
        }
    }
}

new ReadyReText();
