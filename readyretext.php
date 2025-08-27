<?php
/**
 * Plugin Name:       Ready ReText
 * Description:       یک افزونه پیشرفته برای جستجو و جایگزینی متن در تمام بخش‌های سایت وردپرس با پنل تنظیمات کامل.
 * Version:           3.2.0
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

    public function __construct() {
        $this->options = get_option('readyretext_settings', ['rules' => []]);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        $this->init_replacement_hooks();
    }

    public function enqueue_admin_styles($hook) {
        if ('settings_page_readyretext' !== $hook) return;
        wp_enqueue_style('readyretext-admin-styles', plugin_dir_url(__FILE__) . 'assets/admin-styles.css', [], '3.2.0');
    }

    public function add_admin_menu() {
        add_options_page(
            esc_html__('تنظیمات جایگزینی متن', 'readyretext'),
            esc_html__('Ready ReText', 'readyretext'),
            'manage_options',
            'readyretext',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap readyretext-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('readyretext_settings_group');
                echo '<div class="readyretext-card">';
                do_settings_sections('readyretext_rules');
                echo '</div>';
                submit_button(esc_html__('ذخیره تغییرات', 'readyretext'), 'readyretext-btn readyretext-btn-outlined');
                ?>
            </form>

            <div class="readyretext-footer">
                <p>
                    <a href="https://readystudio.ir/retext-plugin" target="_blank"><?php esc_html_e('آموزش کار با افزونه » راهنمای افزونه', 'readyretext'); ?></a>
                </p>
                <p>
                    <?php esc_html_e('ساخته شده توسط', 'readyretext'); ?>
                    <a href="https://readystudio.ir/" target="_blank"><?php esc_html_e('ردی استودیو', 'readyretext'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('readyretext_settings_group', 'readyretext_settings', [$this, 'sanitize_settings']);
        add_settings_section('readyretext_rules_section', esc_html__('قوانین جایگزینی', 'readyretext'), function() {
            echo '<p>' . esc_html__('برای هر قانون، محدوده اعمال آن را به صورت مجزا مشخص کنید.', 'readyretext') . '</p>';
        }, 'readyretext_rules');
        add_settings_field('readyretext_rules_field', esc_html__('لیست قوانین', 'readyretext'), [$this, 'render_rules_field'], 'readyretext_rules', 'readyretext_rules_section');
    }

    public function sanitize_settings($input) {
        $new_input = [];
        if (isset($input['rules']) && is_array($input['rules'])) {
            $new_input['rules'] = array_values(array_filter(array_map(function($rule) {
                if (empty(trim($rule['find']))) return null;
                $allowed_scopes = ['all', 'frontend', 'admin'];
                return [
                    'find'             => sanitize_text_field(stripslashes($rule['find'])),
                    'replace'          => wp_kses_post(stripslashes($rule['replace'])),
                    'is_regex'         => isset($rule['is_regex']),
                    'case_insensitive' => isset($rule['case_insensitive']),
                    'scope'            => in_array($rule['scope'] ?? 'all', $allowed_scopes) ? $rule['scope'] : 'all',
                ];
            }, $input['rules'])));
        } else {
            $new_input['rules'] = [];
        }
        return $new_input;
    }

    public function render_rules_field() {
        $rules = $this->options['rules'] ?? [];
        ?>
        <div id="readyretext-rules-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="col-find"><?php esc_html_e('متن اصلی', 'readyretext'); ?></th>
                        <th class="col-replace"><?php esc_html_e('متن جایگزین', 'readyretext'); ?></th>
                        <th class="col-scope"><?php esc_html_e('محدوده اعمال', 'readyretext'); ?></th>
                        <th class="col-option"><?php esc_html_e('Regex', 'readyretext'); ?></th>
                        <th class="col-option"><?php esc_html_e('عدم حساسیت', 'readyretext'); ?></th>
                        <th class="col-action"></th>
                    </tr>
                </thead>
                <tbody id="readyretext-rules-container">
                    <?php if (empty($rules)) : ?>
                        <tr class="readyretext-rule-row no-rules-row"><td colspan="6"><?php esc_html_e('هیچ قانونی تعریف نشده است.', 'readyretext'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($rules as $index => $rule) : ?>
                            <tr class="readyretext-rule-row">
                                <td><input type="text" class="large-text" name="readyretext_settings[rules][<?php echo $index; ?>][find]" value="<?php echo esc_attr($rule['find']); ?>"></td>
                                <td><input type="text" class="large-text" name="readyretext_settings[rules][<?php echo $index; ?>][replace]" value="<?php echo esc_attr($rule['replace']); ?>"></td>
                                <td>
                                    <select name="readyretext_settings[rules][<?php echo $index; ?>][scope]">
                                        <option value="all" <?php selected($rule['scope'] ?? 'all', 'all'); ?>><?php esc_html_e('کل سایت', 'readyretext'); ?></option>
                                        <option value="frontend" <?php selected($rule['scope'] ?? 'all', 'frontend'); ?>><?php esc_html_e('فقط فرانت', 'readyretext'); ?></option>
                                        <option value="admin" <?php selected($rule['scope'] ?? 'all', 'admin'); ?>><?php esc_html_e('فقط ادمین', 'readyretext'); ?></option>
                                    </select>
                                </td>
                                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][<?php echo $index; ?>][is_regex]" <?php checked($rule['is_regex'] ?? false, true); ?>></td>
                                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][<?php echo $index; ?>][case_insensitive]" <?php checked($rule['case_insensitive'] ?? false, true); ?>></td>
                                <td class="action-cell">
                                    <button type="button" class="readyretext-btn readyretext-btn-icon readyretext-duplicate-rule" title="<?php esc_attr_e('کپی کردن قانون', 'readyretext'); ?>"><span class="dashicons dashicons-admin-page"></span></button>
                                    <button type="button" class="readyretext-btn readyretext-btn-icon readyretext-btn-danger readyretext-remove-rule" title="<?php esc_attr_e('حذف قانون', 'readyretext'); ?>"><span class="dashicons dashicons-trash"></span></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p style="margin-top: 24px;">
                <button type="button" class="readyretext-btn readyretext-btn-primary" id="readyretext-add-rule"><?php esc_html_e('افزودن قانون جدید', 'readyretext'); ?></button>
            </p>
        </div>
        <script type="text/template" id="readyretext-rule-template">
            <tr class="readyretext-rule-row">
                <td><input type="text" class="large-text" name="readyretext_settings[rules][{index}][find]"></td>
                <td><input type="text" class="large-text" name="readyretext_settings[rules][{index}][replace]"></td>
                <td>
                    <select name="readyretext_settings[rules][{index}][scope]">
                        <option value="all" selected><?php esc_html_e('کل سایت', 'readyretext'); ?></option>
                        <option value="frontend"><?php esc_html_e('فقط فرانت', 'readyretext'); ?></option>
                        <option value="admin"><?php esc_html_e('فقط ادمین', 'readyretext'); ?></option>
                    </select>
                </td>
                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][{index}][is_regex]"></td>
                <td style="text-align:center;"><input type="checkbox" name="readyretext_settings[rules][{index}][case_insensitive]" checked></td>
                <td class="action-cell">
                    <button type="button" class="readyretext-btn readyretext-btn-icon readyretext-duplicate-rule" title="<?php esc_attr_e('کپی کردن قانون', 'readyretext'); ?>"><span class="dashicons dashicons-admin-page"></span></button>
                    <button type="button" class="readyretext-btn readyretext-btn-icon readyretext-btn-danger readyretext-remove-rule" title="<?php esc_attr_e('حذف قانون', 'readyretext'); ?>"><span class="dashicons dashicons-trash"></span></button>
                </td>
            </tr>
        </script>
        <script>
        jQuery(document).ready(function($) {
            let ruleIndex = <?php echo count($rules); ?>;

            function addNewRow() {
                $('.no-rules-row').remove();
                let template = $('#readyretext-rule-template').html().replace(/{index}/g, ruleIndex++);
                $('#readyretext-rules-container').append(template);
            }

            $('#readyretext-add-rule').on('click', addNewRow);

            $('#readyretext-rules-wrapper').on('click', '.readyretext-remove-rule', function() {
                $(this).closest('.readyretext-rule-row').remove();
                if ($('#readyretext-rules-container .readyretext-rule-row').length === 0) {
                     $('#readyretext-rules-container').append('<tr class="readyretext-rule-row no-rules-row"><td colspan="6"><?php esc_html_e('هیچ قانونی تعریف نشده است.', 'readyretext'); ?></td></tr>');
                }
            });

            $('#readyretext-rules-wrapper').on('click', '.readyretext-duplicate-rule', function() {
                const rowToClone = $(this).closest('.readyretext-rule-row');
                
                const findVal = rowToClone.find('input[name*="[find]"]').val();
                const replaceVal = rowToClone.find('input[name*="[replace]"]').val();
                const scopeVal = rowToClone.find('select[name*="[scope]"]').val();
                const isRegexChecked = rowToClone.find('input[name*="[is_regex]"]').is(':checked');
                const isCaseInsensitiveChecked = rowToClone.find('input[name*="[case_insensitive]"]').is(':checked');

                addNewRow();
                const newRow = $('#readyretext-rules-container .readyretext-rule-row:last');

                newRow.find('input[name*="[find]"]').val(findVal);
                newRow.find('input[name*="[replace]"]').val(replaceVal);
                newRow.find('select[name*="[scope]"]').val(scopeVal);
                newRow.find('input[name*="[is_regex]"]').prop('checked', isRegexChecked);
                newRow.find('input[name*="[case_insensitive]"]').prop('checked', isCaseInsensitiveChecked);
            });
        });
        </script>
        <?php
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=readyretext">' . esc_html__('تنظیمات', 'readyretext') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    private function init_replacement_hooks() {
        $all_rules = $this->options['rules'] ?? [];
        if (empty($all_rules)) return;
        
        $context = is_admin() ? 'admin' : 'frontend';
        
        $active_rules = array_filter($all_rules, function($rule) use ($context) {
            $scope = $rule['scope'] ?? 'all';
            return ($scope === 'all' || $scope === $context);
        });

        if (empty($active_rules)) return;

        $patterns = [];
        $replacements = [];
        foreach ($active_rules as $rule) {
            $flags = 'u';
            if (!empty($rule['case_insensitive'])) $flags .= 'i';
            if (!empty($rule['is_regex'])) {
                $patterns[] = $rule['find'];
            } else {
                $patterns[] = '/\b' . preg_quote($rule['find'], '/') . '\b/' . $flags;
            }
            $replacements[] = $rule['replace'];
        }
        
        if (empty($patterns)) return;

        $is_urlish = function($str) { return is_string($str) && $str !== '' && preg_match('~(^[a-z]+://|://|www\.|/|\\\|\.php\b|\.html\b|\.htm\b|^#)~i', $str); };
        $replace_plain = function($text) use ($patterns, $replacements, $is_urlish) {
            return (!is_string($text) || $text === '' || $is_urlish($text)) ? $text : preg_replace($patterns, $replacements, $text);
        };
        $replace_html_textnodes = function ($html) use ($patterns, $replacements) {
            if (!is_string($html) || trim($html) === '') return $html;
            if (strip_tags($html) === $html) return preg_replace($patterns, $replacements, $html);
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
                libxml_clear_errors(); return $html;
            }
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') as $node) {
                if (trim($node->nodeValue) === '') continue;
                $newNodeValue = preg_replace($patterns, $replacements, $node->nodeValue);
                if ($newNodeValue !== $node->nodeValue) $node->nodeValue = $newNodeValue;
            }
            $output = $dom->saveHTML();
            return preg_replace('/^<\?xml.*?\?>/i', '', $output);
        };

        add_filter('gettext', $replace_plain, 20);
        add_filter('ngettext', function($s, $p, $n) use ($replace_plain) { return (1 != $n ? $replace_plain($p) : $replace_plain($s)); }, 20, 3);
        $html_filters = ['the_content', 'the_excerpt', 'comment_text', 'widget_text', 'widget_block_content', 'the_tags', 'the_category', 'the_author', 'wp_nav_menu_items', 'wp_list_pages', 'the_archive_title', 'the_archive_description', 'woocommerce_product_title', 'woocommerce_short_description'];
        foreach ($html_filters as $hook) add_filter($hook, $replace_html_textnodes, 20);
        add_filter('the_title', $replace_plain, 20);
        add_filter('document_title_parts', function($p) use ($replace_plain) { return is_array($p) ? array_map($replace_plain, $p) : $p; }, 20);
        add_filter('nav_menu_item_title', $replace_plain, 20);
        add_filter('bloginfo', function ($output, $show) use ($replace_plain, $active_rules) {
            $cache_key = 'rrt_bloginfo_' . md5($show . serialize($active_rules));
            if (false === ($cached = get_transient($cache_key))) {
                $cached = $replace_plain($output);
                set_transient($cache_key, $cached, HOUR_IN_SECONDS);
            }
            return $cached;
        }, 20, 2);
        if (function_exists('acf_add_filter')) {
            add_filter('acf/format_value', function($v) use ($replace_html_textnodes, $replace_plain) {
                return is_string($v) ? (($v !== strip_tags($v)) ? $replace_html_textnodes($v) : $replace_plain($v)) : $v;
            }, 20);
        }
    }
}
new ReadyReText();
