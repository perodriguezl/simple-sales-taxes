<?php
/**
 * Plugin Name: Simple Sales Taxes
 * Plugin URI: https://github.com/perodriguezl/simple-sales-taxes
 * Description: Dynamically sets WooCommerce tax rate based on customer ZIP code using a RapidAPI sales tax endpoint.
 * Version: 0.2.2
 * Author: Pedro Rodriguez
 * Author URI: https://rapidapi.com/perodriguezl/api/u-s-a-sales-taxes-per-zip-code
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-sales-taxes
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    class Simple_Sales_Taxes {
        const VERSION    = '0.2.2';
        const OPTION_KEY = 'sst_settings';
        const SECTION_ID = 'simple_sales_taxes';
        const CACHED_ZIPS_OPTION = 'sst_cached_zips';

        public function __construct() {
            // Settings UI under WooCommerce > Settings > Tax
            add_filter('woocommerce_get_sections_tax', [$this, 'add_tax_section']);
            add_filter('woocommerce_get_settings_tax', [$this, 'add_tax_settings'], 10, 2);
            add_action('woocommerce_update_options_tax_' . self::SECTION_ID, [$this, 'save_settings']);

            // Custom field renderers
            add_action('woocommerce_admin_field_sst_test_connection', [$this, 'render_test_connection_field']);
            add_action('woocommerce_admin_field_sst_external_services', [$this, 'render_external_services_field']);

            // Admin assets for Test Connection button
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // AJAX endpoint for Test Connection
            add_action('wp_ajax_sst_test_connection', [$this, 'ajax_test_connection']);

            // Override matched tax rates at runtime
            add_filter('woocommerce_matched_tax_rates', [$this, 'override_matched_tax_rates'], 20, 6);

            // Admin notice if enabled but missing key
            add_action('admin_notices', [$this, 'admin_notice_missing_key']);
        }

        private function get_settings(): array {
            $defaults = [
                'enabled'             => 'no',
                'rapidapi_key'        => '',
                'rapidapi_host'       => 'u-s-a-sales-taxes-per-zip-code.p.rapidapi.com',
                'endpoint_path'       => '/{zip}', // replace {zip}
                'tax_label'           => 'Sales Tax',
                'tax_shipping'        => 'yes',
                'cache_ttl_minutes'   => '720',
                'debug_log'           => 'no',
                'api_listing_url'     => 'https://rapidapi.com/perodriguezl/api/u-s-a-sales-taxes-per-zip-code',
                'privacy_policy_url'  => '',
            ];

            $saved = get_option(self::OPTION_KEY, []);
            return array_merge($defaults, is_array($saved) ? $saved : []);
        }

        private function update_settings(array $new): void {
            update_option(self::OPTION_KEY, $new);
        }

        public function add_tax_section($sections) {
            $sections[self::SECTION_ID] = esc_html__('Simple Sales Taxes', 'simple-sales-taxes');
            return $sections;
        }

        public function add_tax_settings($settings, $current_section) {
            if ($current_section !== self::SECTION_ID) return $settings;

            $s = $this->get_settings();

            return [
                [
                    'title' => esc_html__('Simple Sales Taxes', 'simple-sales-taxes'),
                    'type'  => 'title',
                    'desc'  => esc_html__('Dynamically sets WooCommerce sales tax rate based on customer ZIP code (via RapidAPI).', 'simple-sales-taxes'),
                    'id'    => 'sst_title',
                ],

                [
                    'title'   => esc_html__('Enable', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[enabled]',
                    'type'    => 'checkbox',
                    'desc'    => esc_html__('Enable ZIP-based tax rate', 'simple-sales-taxes'),
                    'default' => $s['enabled'],
                ],
                [
                    'title'   => esc_html__('RapidAPI Key', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[rapidapi_key]',
                    'type'    => 'password',
                    'css'     => 'min-width: 420px;',
                    'default' => $s['rapidapi_key'],
                    'desc'    => esc_html__('Paste your RapidAPI subscription key.', 'simple-sales-taxes'),
                ],
                [
                    'title'   => esc_html__('RapidAPI Host', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[rapidapi_host]',
                    'type'    => 'text',
                    'css'     => 'min-width: 420px;',
                    'default' => $s['rapidapi_host'],
                    'desc'    => esc_html__('Example: u-s-a-sales-taxes-per-zip-code.p.rapidapi.com', 'simple-sales-taxes'),
                ],
                [
                    'title'   => esc_html__('Endpoint Path', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[endpoint_path]',
                    'type'    => 'text',
                    'css'     => 'min-width: 420px;',
                    'default' => $s['endpoint_path'],
                    'desc'    => esc_html__('Use {zip} placeholder. Example: /{zip}', 'simple-sales-taxes'),
                ],
                [
                    'title'   => esc_html__('Tax Label', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[tax_label]',
                    'type'    => 'text',
                    'default' => $s['tax_label'],
                ],
                [
                    'title'   => esc_html__('Tax Shipping', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[tax_shipping]',
                    'type'    => 'select',
                    'options' => [
                        'yes' => esc_html__('Yes', 'simple-sales-taxes'),
                        'no'  => esc_html__('No', 'simple-sales-taxes'),
                    ],
                    'default' => $s['tax_shipping'],
                ],
                [
                    'title'   => esc_html__('Cache TTL (minutes)', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[cache_ttl_minutes]',
                    'type'    => 'number',
                    'css'     => 'width: 120px;',
                    'default' => $s['cache_ttl_minutes'],
                    'desc'    => esc_html__('How long to cache ZIP lookups.', 'simple-sales-taxes'),
                ],
                [
                    'title'   => esc_html__('Debug Logging', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[debug_log]',
                    'type'    => 'select',
                    'options' => [
                        'no'  => esc_html__('Off', 'simple-sales-taxes'),
                        'yes' => esc_html__('On (WooCommerce logs)', 'simple-sales-taxes'),
                    ],
                    'default' => $s['debug_log'],
                ],

                [
                    'type'  => 'sst_external_services',
                    'id'    => 'sst_external_services',
                    'title' => esc_html__('External Services', 'simple-sales-taxes'),
                ],

                [
                    'type'  => 'sst_test_connection',
                    'id'    => 'sst_test_connection',
                    'title' => esc_html__('Test Connection', 'simple-sales-taxes'),
                ],

                [
                    'title'   => esc_html__('API Listing URL', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[api_listing_url]',
                    'type'    => 'text',
                    'css'     => 'min-width: 420px;',
                    'default' => $s['api_listing_url'],
                    'desc'    => esc_html__('Public page where users can subscribe and obtain credentials.', 'simple-sales-taxes'),
                ],
                [
                    'title'   => esc_html__('Privacy Policy URL', 'simple-sales-taxes'),
                    'id'      => self::OPTION_KEY . '[privacy_policy_url]',
                    'type'    => 'text',
                    'css'     => 'min-width: 420px;',
                    'default' => $s['privacy_policy_url'],
                    'desc'    => esc_html__('Optional: link describing what data is sent to the external service.', 'simple-sales-taxes'),
                ],

                [
                    'type' => 'sectionend',
                    'id'   => 'sst_end',
                ],
            ];
        }

        public function save_settings(): void {
            if (!current_user_can('manage_woocommerce')) {
                return;
            }

            // WooCommerce settings pages include a nonce with action "woocommerce-settings".
            $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, 'woocommerce-settings')) {
                return;
            }

            // Avoid touching raw $_POST arrays directly (Plugin Check).
            $raw = filter_input(INPUT_POST, self::OPTION_KEY, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            if (!is_array($raw)) {
                $raw = [];
            }

            $current = $this->get_settings();
            $new = $current;

            $new['enabled']            = isset($raw['enabled']) ? 'yes' : 'no';
            $new['rapidapi_key']       = isset($raw['rapidapi_key']) ? sanitize_text_field(wp_unslash($raw['rapidapi_key'])) : '';
            $new['rapidapi_host']      = isset($raw['rapidapi_host']) ? sanitize_text_field(wp_unslash($raw['rapidapi_host'])) : $current['rapidapi_host'];
            $new['endpoint_path']      = isset($raw['endpoint_path']) ? sanitize_text_field(wp_unslash($raw['endpoint_path'])) : $current['endpoint_path'];
            $new['tax_label']          = isset($raw['tax_label']) ? sanitize_text_field(wp_unslash($raw['tax_label'])) : $current['tax_label'];
            $new['tax_shipping']       = (isset($raw['tax_shipping']) && wp_unslash($raw['tax_shipping']) === 'no') ? 'no' : 'yes';
            $new['cache_ttl_minutes']  = isset($raw['cache_ttl_minutes']) ? (string) max(1, intval(wp_unslash($raw['cache_ttl_minutes']))) : $current['cache_ttl_minutes'];
            $new['debug_log']          = (isset($raw['debug_log']) && wp_unslash($raw['debug_log']) === 'yes') ? 'yes' : 'no';
            $new['api_listing_url']    = isset($raw['api_listing_url']) ? esc_url_raw(wp_unslash($raw['api_listing_url'])) : $current['api_listing_url'];
            $new['privacy_policy_url'] = isset($raw['privacy_policy_url']) ? esc_url_raw(wp_unslash($raw['privacy_policy_url'])) : $current['privacy_policy_url'];

            $this->update_settings($new);

            if (class_exists('WC_Admin_Settings')) {
                WC_Admin_Settings::add_message(esc_html__('Simple Sales Taxes settings saved.', 'simple-sales-taxes'));
            }
        }

        public function admin_notice_missing_key(): void {
            if (!current_user_can('manage_woocommerce')) return;

            $s = $this->get_settings();
            if (($s['enabled'] ?? 'no') !== 'yes') return;
            if (!empty($s['rapidapi_key'])) return;

            $url = admin_url('admin.php?page=wc-settings&tab=tax&section=' . self::SECTION_ID);
            printf(
                '<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
                esc_html__('Simple Sales Taxes', 'simple-sales-taxes'),
                esc_html__('is enabled but missing a RapidAPI key. Configure it here:', 'simple-sales-taxes'),
                esc_url($url),
                esc_html__('Settings', 'simple-sales-taxes')
            );
        }

        public function override_matched_tax_rates($matched_tax_rates, $country, $state, $postcode, $city, $tax_class) {
            $s = $this->get_settings();
            if (($s['enabled'] ?? 'no') !== 'yes') return $matched_tax_rates;
            if (strtoupper((string)$country) !== 'US') return $matched_tax_rates;

            $zip = $this->normalize_zip($postcode);
            if ($zip === null) return $matched_tax_rates;

            if (empty($s['rapidapi_key'])) return $matched_tax_rates;

            $rate_percent = $this->get_tax_rate_percent_for_zip($zip, $s);
            if ($rate_percent === null) return $matched_tax_rates;

            return [
                'sst_' . $zip => [
                    'rate'     => (float) $rate_percent,
                    'label'    => (string) ($s['tax_label'] ?? 'Sales Tax'),
                    'shipping' => (($s['tax_shipping'] ?? 'yes') === 'no') ? 'no' : 'yes',
                    'compound' => 'no',
                ],
            ];
        }

        private function normalize_zip($postcode): ?string {
            $digits = preg_replace('/\D+/', '', (string)$postcode);
            if (!$digits || strlen($digits) < 5) return null;
            return substr($digits, 0, 5);
        }

        private function remember_cached_zip(string $zip): void {
            $zips = get_option(self::CACHED_ZIPS_OPTION, []);
            if (!is_array($zips)) $zips = [];

            if (!in_array($zip, $zips, true)) {
                $zips[] = $zip;
                update_option(self::CACHED_ZIPS_OPTION, $zips, false);
            }
        }

        private function get_tax_rate_percent_for_zip(string $zip, array $settings): ?float {
            $ttl_minutes = max(1, intval($settings['cache_ttl_minutes'] ?? 720));
            $cache_key = 'sst_rate_' . $zip;

            $cached = get_transient($cache_key);
            if ($cached !== false && is_numeric($cached)) {
                return (float) $cached;
            }

            $rate = $this->fetch_rate_from_rapidapi($zip, $settings);
            if ($rate === null) return null;

            set_transient($cache_key, (string)$rate, $ttl_minutes * MINUTE_IN_SECONDS);
            $this->remember_cached_zip($zip);

            return $rate;
        }

        private function fetch_rate_from_rapidapi(string $zip, array $settings): ?float {
            $key  = trim((string)($settings['rapidapi_key'] ?? ''));
            $host = trim((string)($settings['rapidapi_host'] ?? ''));
            $path = (string)($settings['endpoint_path'] ?? '/{zip}');

            if ($key === '' || $host === '') {
                $this->log($settings, 'Missing RapidAPI key/host.');
                return null;
            }

            $path = str_replace('{zip}', rawurlencode($zip), $path);
            if ($path === '' || $path[0] !== '/') $path = '/' . ltrim($path, '/');

            $url = 'https://' . $host . $path;

            $resp = wp_safe_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'X-RapidAPI-Key'  => $key,
                    'X-RapidAPI-Host' => $host,
                    'Accept'          => 'application/json',
                ],
            ]);

            if (is_wp_error($resp)) {
                $this->log($settings, 'RapidAPI request failed: ' . $resp->get_error_message());
                return null;
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            $body = (string) wp_remote_retrieve_body($resp);

            if ($code === 401 || $code === 403) {
                $this->log($settings, "RapidAPI auth/subscription error HTTP {$code}.");
                return null;
            }

            if ($code < 200 || $code >= 300) {
                $this->log($settings, "RapidAPI non-2xx: HTTP {$code}. Body: " . substr($body, 0, 400));
                return null;
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                $this->log($settings, 'Unexpected payload (not JSON). Body: ' . substr($body, 0, 400));
                return null;
            }

            $raw = null;

            if (isset($data['estimated_combined_rate']) && is_numeric($data['estimated_combined_rate'])) {
                $raw = (float) $data['estimated_combined_rate'];
            }

            if ($raw === null) {
                $components = ['state_rate', 'estimated_county_rate', 'estimated_city_rate', 'estimated_special_rate'];
                $sum = 0.0;
                $found_any = false;

                foreach ($components as $k) {
                    if (isset($data[$k]) && is_numeric($data[$k])) {
                        $sum += (float) $data[$k];
                        $found_any = true;
                    }
                }

                if ($found_any) {
                    $raw = $sum;
                }
            }

            if ($raw === null) {
                $this->log($settings, 'Could not find tax rate fields. Keys: ' . implode(',', array_keys($data)));
                return null;
            }

            $rate_percent = ($raw <= 1.0) ? ($raw * 100.0) : $raw;

            if ($rate_percent < 0.0 || $rate_percent > 25.0) {
                $this->log($settings, 'Rate out of range. raw=' . $raw . ' percent=' . $rate_percent);
                return null;
            }

            return round($rate_percent, 4);
        }

        private function log(array $settings, string $message): void {
            if (($settings['debug_log'] ?? 'no') !== 'yes') return;
            if (!function_exists('wc_get_logger')) return;
            wc_get_logger()->error($message, ['source' => 'simple-sales-taxes']);
        }

        public function render_external_services_field($value): void {
            $s = $this->get_settings();

            $api_url = trim((string)($s['api_listing_url'] ?? ''));
            $privacy = trim((string)($s['privacy_policy_url'] ?? ''));

            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label>' . esc_html__('External Services', 'simple-sales-taxes') . '</label>';
            echo '</th>';
            echo '<td class="forminp">';

            echo '<p>' . esc_html__(
                'This plugin connects to an external service (RapidAPI) to retrieve an estimated combined US sales tax rate based on the customer ZIP code.',
                'simple-sales-taxes'
            ) . '</p>';

            echo '<ul style="margin-left: 1.2em; list-style: disc;">';
            echo '<li>' . esc_html__('Data sent: ZIP/postcode (first 5 digits) and RapidAPI authentication headers.', 'simple-sales-taxes') . '</li>';
            echo '<li>' . esc_html__('When sent: Only when enabled and the customer shipping country is US.', 'simple-sales-taxes') . '</li>';
            echo '</ul>';

            if ($api_url !== '') {
                echo '<p><strong>' . esc_html__('API listing:', 'simple-sales-taxes') . '</strong> ';
                echo '<a href="' . esc_url($api_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($api_url) . '</a></p>';
            }

            if ($privacy !== '') {
                echo '<p><strong>' . esc_html__('Privacy policy:', 'simple-sales-taxes') . '</strong> ';
                echo '<a href="' . esc_url($privacy) . '" target="_blank" rel="noopener noreferrer">' . esc_html($privacy) . '</a></p>';
            }

            echo '</td>';
            echo '</tr>';
        }

        public function render_test_connection_field($value): void {
            $nonce = wp_create_nonce('sst_test_connection');

            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label>' . esc_html__('Test Connection', 'simple-sales-taxes') . '</label>';
            echo '</th>';
            echo '<td class="forminp">';

            echo '<p>' . esc_html__(
                'Test your RapidAPI credentials by querying a ZIP code. This does not affect checkout totals.',
                'simple-sales-taxes'
            ) . '</p>';

            echo '<input type="text" id="sst-test-zip" value="' . esc_attr('78641') . '" style="width:120px;" />';
            echo '&nbsp;';
            echo '<button type="button" class="button" id="sst-test-btn" data-nonce="' . esc_attr($nonce) . '">'
                . esc_html__('Test Connection', 'simple-sales-taxes') . '</button>';
            echo '&nbsp;<span class="spinner" id="sst-test-spinner" style="float:none;"></span>';

            echo '<div id="sst-test-result" style="margin-top:10px;"></div>';

            echo '</td>';
            echo '</tr>';
        }

        public function enqueue_admin_assets(string $hook): void {
            if ($hook !== 'woocommerce_page_wc-settings') {
                return;
            }

            // Use filter_input to avoid Plugin Check "recommended" nonce noise on $_GET.
            $tab = filter_input(INPUT_GET, 'tab', FILTER_UNSAFE_RAW);
            $section = filter_input(INPUT_GET, 'section', FILTER_UNSAFE_RAW);

            $tab = is_string($tab) ? sanitize_text_field(wp_unslash($tab)) : '';
            $section = is_string($section) ? sanitize_text_field(wp_unslash($section)) : '';

            if ($tab !== 'tax' || $section !== self::SECTION_ID) {
                return;
            }

            wp_enqueue_script(
                'simple-sales-taxes-admin',
                plugins_url('assets/admin.js', __FILE__),
                ['jquery'],
                self::VERSION,
                true
            );

            wp_localize_script('simple-sales-taxes-admin', 'SST_Admin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action'  => 'sst_test_connection',
                'i18n'    => [
                    'missingZip' => __('Please enter a 5-digit ZIP code.', 'simple-sales-taxes'),
                    'testing'    => __('Testing...', 'simple-sales-taxes'),
                ],
            ]);
        }

        public function ajax_test_connection(): void {
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => __('Insufficient permissions.', 'simple-sales-taxes')], 403);
            }

            check_ajax_referer('sst_test_connection', 'nonce');

            $zip_in = isset($_POST['zip']) ? sanitize_text_field(wp_unslash($_POST['zip'])) : '';
            $zip = $this->normalize_zip($zip_in);

            if ($zip === null) {
                wp_send_json_error(['message' => __('Invalid ZIP code. Please enter 5 digits.', 'simple-sales-taxes')], 400);
            }

            $s = $this->get_settings();
            if (empty($s['rapidapi_key'])) {
                wp_send_json_error(['message' => __('Missing RapidAPI key. Save your key first.', 'simple-sales-taxes')], 400);
            }

            $rate = $this->fetch_rate_from_rapidapi($zip, $s);

            if ($rate === null) {
                wp_send_json_error([
                    'message' => __('Request failed. Verify your RapidAPI key/subscription and endpoint settings. (Enable Debug Logging for details.)', 'simple-sales-taxes'),
                ], 502);
            }

            wp_send_json_success([
                'zip'          => $zip,
                'rate_percent' => $rate,
                'example_tax'  => round(10.00 * ($rate / 100.0), 2),
                'message'      => sprintf(
                    /* translators: 1: ZIP code 2: percent rate */
                    __('Success. ZIP %1$s -> %2$s%%', 'simple-sales-taxes'),
                    $zip,
                    $rate
                ),
            ]);
        }
    }

    new Simple_Sales_Taxes();
});
