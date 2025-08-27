<?php
/*
Plugin Name: Ready ReText
Plugin URI: https://example.com/readyretext
Description: Advanced text replacement plugin for WordPress. Replace texts sitewide without affecting URLs, paths, or HTML attributes. Features advanced settings for multiple rules, regex support, case sensitivity, and scope selection (all site, frontend only, or admin only).
Version: 1.0.0
Author: Grok (built by xAI)
Author URI: https://x.ai
License: GPL-2.0-or-later
Text Domain: readyretext
*/

if (!defined('ABSPATH')) exit;

class ReadyReText {
    private $option_name = 'readyretext_settings';
    private $defaults = [
        'scope' => 'all', // 'all', 'frontend', 'admin'
        'replacements' => [], // array of [ 'from' => '', 'to' => '', 'is_regex' => false, 'case_insensitive' => true ]
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // Apply filters only if enabled
        add_action('init', [$this, 'apply_replacements']);
    }

    public function add_settings_page() {
        add_options_page(
            'Ready ReText Settings',
            'Ready ReText',
            'manage_options',
            'readyretext',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name, [$this, 'validate_settings']);
        add_settings_section('general', 'General Settings', [$this, 'general_section_callback'], 'readyretext');
        add_settings_field('scope', 'Apply To', [$this, 'scope_field_callback'], 'readyretext', 'general');
        add_settings_field('replacements', 'Replacement Rules', [$this, 'replacements_field_callback'], 'readyretext', 'general');
    }

    public function general_section_callback() {
        echo '<p>Configure text replacements across your WordPress site. Add multiple rules, use regex for advanced patterns, and control where replacements apply.</p>';
    }

    public function scope_field_callback() {
        $options = $this->get_options();
        $scope = $options['scope'];
        ?>
        <select name="<?php echo $this->option_name; ?>[scope]">
            <option value="all" <?php selected($scope, 'all'); ?>>All Site (Frontend & Admin)</option>
            <option value="frontend" <?php selected($scope, 'frontend'); ?>>Frontend Only</option>
            <option value="admin" <?php selected($scope, 'admin'); ?>>Admin Only</option>
        </select>
        <p class="description">Choose where the replacements should be applied.</p>
        <?php
    }

    public function replacements_field_callback() {
        $options = $this->get_options();
        $replacements = $options['replacements'];
        $index = count($replacements); // For JS to start from here
        ?>
        <table id="replacements-table" class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>From (Text or Regex)</th>
                    <th>To</th>
                    <th>Regex?</th>
                    <th>Case Insensitive?</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($replacements)): ?>
                    <?php foreach ($replacements as $idx => $rule): ?>
                        <tr>
                            <td><input type="text" name="<?php echo $this->option_name; ?>[replacements][<?php echo $idx; ?>][from]" value="<?php echo esc_attr($rule['from']); ?>" class="widefat" /></td>
                            <td><input type="text" name="<?php echo $this->option_name; ?>[replacements][<?php echo $idx; ?>][to]" value="<?php echo esc_attr($rule['to']); ?>" class="widefat" /></td>
                            <td><input type="checkbox" name="<?php echo $this->option_name; ?>[replacements][<?php echo $idx; ?>][is_regex]" <?php checked($rule['is_regex'], true); ?> /></td>
                            <td><input type="checkbox" name="<?php echo $this->option_name; ?>[replacements][<?php echo $idx; ?>][case_insensitive]" <?php checked($rule['case_insensitive'], true); ?> /></td>
                            <td><button type="button" class="button button-secondary remove-row">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <button type="button" id="add-replacement-row" class="button button-primary">Add Rule</button>
        <p class="description">Add rules for text replacement. For regex, enable the checkbox and enter a valid PHP regex pattern (e.g., /\bword\b/i). Replacements won't affect URLs, paths, or HTML attributes.</p>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var table = $('#replacements-table tbody');
            var index = <?php echo $index; ?>;

            $('#add-replacement-row').on('click', function() {
                var row = '<tr>' +
                    '<td><input type="text" name="<?php echo $this->option_name; ?>[replacements][' + index + '][from]" class="widefat" /></td>' +
                    '<td><input type="text" name="<?php echo $this->option_name; ?>[replacements][' + index + '][to]" class="widefat" /></td>' +
                    '<td><input type="checkbox" name="<?php echo $this->option_name; ?>[replacements][' + index + '][is_regex]" /></td>' +
                    '<td><input type="checkbox" name="<?php echo $this->option_name; ?>[replacements][' + index + '][case_insensitive]" checked /></td>' +
                    '<td><button type="button" class="button button-secondary remove-row">Remove</button></td>' +
                '</tr>';
                table.append(row);
                index++;
            });

            table.on('click', '.remove-row', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Ready ReText Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('readyretext');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function validate_settings($input) {
        $output = [];
        $output['scope'] = in_array($input['scope'] ?? 'all', ['all', 'frontend', 'admin']) ? $input['scope'] : 'all';

        $output['replacements'] = [];
        if (isset($input['replacements']) && is_array($input['replacements'])) {
            foreach ($input['replacements'] as $rule) {
                if (!empty($rule['from']) && isset($rule['to'])) {
                    $output['replacements'][] = [
                        'from' => sanitize_text_field($rule['from']),
                        'to' => wp_kses_post($rule['to']), // Allow HTML in 'to' if needed
                        'is_regex' => isset($rule['is_regex']),
                        'case_insensitive' => isset($rule['case_insensitive']),
                    ];
                }
            }
        }
        return $output;
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'settings_page_readyretext') return;
        // No separate JS file needed since it's inline
    }

    private function get_options() {
        return wp_parse_args(get_option($this->option_name, []), $this->defaults);
    }

    public function apply_replacements() {
        $options = $this->get_options();
        $scope = $options['scope'];
        $replacements = $options['replacements'];

        if (empty($replacements)) return;

        // Check scope
        $apply = false;
        if ($scope === 'all') $apply = true;
        elseif ($scope === 'frontend' && !is_admin()) $apply = true;
        elseif ($scope === 'admin' && is_admin()) $apply = true;

        if (!$apply) return;

        // Build patterns/replacements
        $patterns = [];
        $replaces = [];
        foreach ($replacements as $rule) {
            $from = $rule['from'];
            $to = $rule['to'];
            $flags = 'u'; // Unicode
            if ($rule['case_insensitive']) $flags .= 'i';

            if ($rule['is_regex']) {
                $pattern = $from; // User provides full pattern
            } else {
                $pattern = '/\b' . preg_quote($from, '/') . '\b/' . $flags;
            }

            $patterns[] = $pattern;
            $replaces[] = $to;
        }

        // URL detection
        $is_urlish = function ($str) {
            if (!is_string($str) || $str === '') return false;
            return (bool) preg_match('~(^[a-z]+://|://|www\.|/|\\\|\.php\b|\.html\b|\.htm\b|^#)~i', $str);
        };

        // Plain replace
        $replace_plain = function ($text) use ($patterns, $replaces, $is_urlish) {
            if (!is_string($text) || $text === '' || $is_urlish($text)) return $text;
            return preg_replace($patterns, $replaces, $text);
        };

        // HTML replace
        $replace_html_textnodes = function ($html) use ($patterns, $replaces) {
            if (!is_string($html) || $html === '') return $html;

            if (strip_tags($html) === $html) {
                return preg_replace($patterns, $replaces, $html);
            }

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            if (!$loaded) return $html;

            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//text()') as $textNode) {
                $t = $textNode->nodeValue;
                if ($t === '') continue;
                $newT = preg_replace($patterns, $replaces, $t);
                if ($newT !== $t) $textNode->nodeValue = $newT;
            }

            $out = $dom->saveHTML();
            $out = preg_replace('/^<\?xml.*?\?>/i', '', $out);
            return $out;
        };

        // Apply filters
        add_filter('gettext', function ($translated, $text, $domain) use ($replace_plain) {
            return $replace_plain($translated);
        }, 20, 3);

        add_filter('ngettext', function ($single, $plural, $number, $domain) use ($replace_plain) {
            return $number != 1 ? $replace_plain($plural) : $replace_plain($single);
        }, 20, 4);

        $html_filters = [
            'the_content', 'the_excerpt', 'comment_text', 'widget_text', 'widget_block_content',
            'the_tags', 'the_category', 'the_author', 'wp_nav_menu_items', 'wp_list_pages',
            'the_archive_title', 'the_archive_description',
        ];
        foreach ($html_filters as $hook) {
            add_filter($hook, $replace_html_textnodes, 20);
        }

        add_filter('the_title', $replace_plain, 20);
        add_filter('document_title_parts', function ($parts) use ($replace_plain) {
            return is_array($parts) ? array_map($replace_plain, $parts) : $parts;
        }, 20);
        add_filter('nav_menu_item_title', $replace_plain, 20, 2);
        add_filter('bloginfo', $replace_plain, 20, 2);

        if (function_exists('acf_add_filter')) {
            add_filter('acf/format_value', function ($value, $post_id, $field) use ($replace_plain, $replace_html_textnodes) {
                if (!is_string($value)) return $value;
                return $value !== strip_tags($value) ? $replace_html_textnodes($value) : $replace_plain($value);
            }, 20, 3);
        }
    }
}

new ReadyReText();