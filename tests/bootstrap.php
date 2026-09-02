<?php
/**
 * PHPUnit Test Bootstrap para GVN Checkout for WooCommerce.
 */

if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('GVN_CHECKOUT_PLUGIN_DIR')) {
    define('GVN_CHECKOUT_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('GVN_CHECKOUT_PLUGIN_BASENAME')) {
    define('GVN_CHECKOUT_PLUGIN_BASENAME', 'gvn-checkout/gvn-checkout.php');
}

if (!defined('GVN_CHECKOUT_PLUGIN_URL')) {
    define('GVN_CHECKOUT_PLUGIN_URL', 'https://example.com/wp-content/plugins/gvn-checkout/');
}

if (!defined('GVN_CHECKOUT_VERSION')) {
    define('GVN_CHECKOUT_VERSION', '1.13.42');
}

// Mocks e stubs básicos de WordPress para testes unitários em isolamento (sem banco de dados).
global $wp_mock_actions, $wp_mock_filters, $wp_mock_options, $wc_mock_notices;
$wp_mock_actions = [];
$wp_mock_filters = [];
$wp_mock_options = [];
$wc_mock_notices = [];

global $wp_mock_enqueued_styles, $wp_mock_enqueued_scripts, $wp_mock_inline_styles;
$wp_mock_enqueued_styles = [];
$wp_mock_enqueued_scripts = [];
$wp_mock_inline_styles = [];

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        global $wp_mock_enqueued_styles;
        $wp_mock_enqueued_styles[$handle] = ['src' => $src, 'deps' => $deps, 'ver' => $ver];
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
        global $wp_mock_enqueued_scripts;
        $wp_mock_enqueued_scripts[$handle] = ['src' => $src, 'deps' => $deps, 'ver' => $ver];
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $name, $data) {
        return true;
    }
}

if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data) {
        global $wp_mock_inline_styles;
        $wp_mock_inline_styles[$handle][] = $data;
        return true;
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) {
        if (empty($color)) {
            return '';
        }
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color)) {
            return $color;
        }
        return '';
    }
}

if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) {
        return false !== strpos((string) $content, '[' . $tag);
    }
}

if (!function_exists('is_wc_endpoint_url')) {
    function is_wc_endpoint_url($endpoint = false) {
        return false;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'mock_nonce_' . $action;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') {
        echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@siu', '', (string) $str);
        return trim(strip_tags($text));
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) {
        if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) {
            return $color;
        }
        return '';
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        return 1;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        $response = ['success' => true];
        if (isset($data)) {
            $response['data'] = $data;
        }
        echo json_encode($response);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        $response = ['success' => false];
        if (isset($data)) {
            $response['data'] = $data;
        }
        echo json_encode($response);
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        if (count($args) === 2 && is_array($args[0])) {
            $uri = $args[1];
            $query = http_build_query($args[0]);
            return $uri . (strpos($uri, '?') !== false ? '&' : '?') . $query;
        } elseif (count($args) === 3) {
            $key = $args[0];
            $value = $args[1];
            $uri = $args[2];
            return $uri . (strpos($uri, '?') !== false ? '&' : '?') . urlencode((string)$key) . '=' . urlencode((string)$value);
        }
        return $args[count($args) - 1] ?? '';
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ((string) $checked === (string) $current) ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        $result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

global $wp_mock_transients;
$wp_mock_transients = [];

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $wp_mock_transients;
        return $wp_mock_transients[$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $wp_mock_transients;
        $wp_mock_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $wp_mock_transients;
        unset($wp_mock_transients[$transient]);
        return true;
    }
}

global $wp_mock_http_responses;
$wp_mock_http_responses = [];

if (!function_exists('wp_safe_remote_get')) {
    function wp_safe_remote_get($url, $args = []) {
        global $wp_mock_http_responses;
        if (isset($wp_mock_http_responses[$url])) {
            return $wp_mock_http_responses[$url];
        }
        if (preg_match('/viacep\.com\.br\/ws\/(\d{8})\/json\//', $url, $m)) {
            $cep = $m[1];
            if ($cep === '01001000') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'cep' => '01001-000',
                        'logradouro' => 'Praça da Sé',
                        'complemento' => 'lado ímpar',
                        'bairro' => 'Sé',
                        'localidade' => 'São Paulo',
                        'uf' => 'SP',
                        'ibge' => '3550308',
                    ]),
                ];
            }
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['erro' => true]),
            ];
        }
        return new WP_Error('http_request_failed', 'Simulated HTTP failure');
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return wp_safe_remote_get($url, $args);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response) || !isset($response['response']['code'])) {
            return '';
        }
        return (int) $response['response']['code'];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response) || !isset($response['body'])) {
            return '';
        }
        return $response['body'];
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return '6.7';
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        return add_filter($tag, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_mock_filters;
        $wp_mock_filters[$tag][$priority][] = [
            'function' => $callback,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('has_action')) {
    function has_action($tag, $callback_to_check = false) {
        return has_filter($tag, $callback_to_check);
    }
}

if (!function_exists('has_filter')) {
    function has_filter($tag, $callback_to_check = false) {
        global $wp_mock_filters;
        if (empty($wp_mock_filters[$tag])) {
            return false;
        }
        if (false === $callback_to_check) {
            return true;
        }
        foreach ($wp_mock_filters[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $cb) {
                if ($cb['function'] === $callback_to_check) {
                    return $priority;
                }
            }
        }
        return false;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($tag, $callback_to_remove, $priority = 10) {
        return remove_filter($tag, $callback_to_remove, $priority);
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($tag, $callback_to_remove, $priority = 10) {
        global $wp_mock_filters;
        if (!empty($wp_mock_filters[$tag][$priority])) {
            foreach ($wp_mock_filters[$tag][$priority] as $idx => $cb) {
                if ($cb['function'] === $callback_to_remove) {
                    unset($wp_mock_filters[$tag][$priority][$idx]);
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) {
        return preg_replace('#</?script\b[^>]*>#is', '', $content);
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        global $wp_mock_actions, $wp_mock_filters;
        $wp_mock_actions[] = [
            'tag' => $tag,
            'args' => $args,
        ];

        if (!empty($wp_mock_filters[$tag])) {
            ksort($wp_mock_filters[$tag]);
            foreach ($wp_mock_filters[$tag] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $fn = $callback['function'];
                    $accepted_args = $callback['accepted_args'];
                    $call_args = array_slice($args, 0, $accepted_args);
                    call_user_func_array($fn, $call_args);
                }
            }
        }
    }
}

if (!function_exists('did_action')) {
    function did_action($tag) {
        global $wp_mock_actions;
        $count = 0;
        if (is_array($wp_mock_actions)) {
            foreach ($wp_mock_actions as $act) {
                if ($act['tag'] === $tag) {
                    $count++;
                }
            }
        }
        return $count;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        global $wp_mock_filters;
        if (!empty($wp_mock_filters[$tag])) {
            ksort($wp_mock_filters[$tag]);
            foreach ($wp_mock_filters[$tag] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $fn = $callback['function'];
                    $all_args = array_merge([$value], $args);
                    $accepted_args = $callback['accepted_args'];
                    $call_args = array_slice($all_args, 0, $accepted_args);
                    $value = call_user_func_array($fn, $call_args);
                }
            }
        }
        return $value;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        global $wp_mock_shortcodes;
        if (!is_array($wp_mock_shortcodes)) {
            $wp_mock_shortcodes = [];
        }
        $wp_mock_shortcodes[$tag] = $callback;
        return true;
    }
}

if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) {
        return strpos((string) $content, '[' . $tag) !== false;
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($content) {
        return $content;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $wp_mock_options;
        if (!is_array($wp_mock_options)) {
            $wp_mock_options = [];
        }
        return array_key_exists($option, $wp_mock_options) ? $wp_mock_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $wp_mock_options;
        if (!is_array($wp_mock_options)) {
            $wp_mock_options = [];
        }
        $wp_mock_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $wp_mock_options;
        if (is_array($wp_mock_options) && array_key_exists($option, $wp_mock_options)) {
            unset($wp_mock_options[$option]);
            return true;
        }
        return false;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $raw_key = (string) $key;
        $sanitized_key = strtolower($raw_key);
        return preg_replace('/[^a-z0-9_\-]/', '', $sanitized_key);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var((string) $email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@siu', '', (string) $str);
        return trim(strip_tags($text));
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@siu', '', (string) $text);
        return trim(strip_tags($text));
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        $result = ((string) $selected === (string) $current) ? 'selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ((string) $checked === (string) $current || (true === $checked && true === $current)) ? 'checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        $name_attr = esc_attr($name);
        $value_attr = esc_attr('mock_nonce_' . $action);
        $html = '<input type="hidden" id="' . $name_attr . '" name="' . $name_attr . '" value="' . $value_attr . '" />';
        if ($echo) {
            echo $html;
        }
        return $html;
    }
}

if (!function_exists('get_privacy_policy_url')) {
    function get_privacy_policy_url() {
        return 'https://example.com/privacy-policy';
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        global $wp_mock_logged_in;
        return !empty($wp_mock_logged_in);
    }
}

if (!function_exists('wc_get_checkout_url')) {
    function wc_get_checkout_url() {
        return 'https://example.com/checkout';
    }
}

if (!function_exists('wc_price')) {
    function wc_price($price, $args = []) {
        return 'R$ ' . number_format((float) $price, 2, ',', '.');
    }
}

if (!function_exists('wc_terms_and_conditions_checkbox_enabled')) {
    function wc_terms_and_conditions_checkbox_enabled() {
        global $wp_mock_options;
        return !empty($wp_mock_options['woocommerce_terms_page_id']);
    }
}

if (!function_exists('wc_terms_and_conditions_checkbox_text')) {
    function wc_terms_and_conditions_checkbox_text() {
        echo 'Li e concordo com os termos e condições do site';
    }
}

if (!function_exists('wc_terms_and_conditions_page_content')) {
    function wc_terms_and_conditions_page_content() {
        echo '<div class="woocommerce-terms-and-conditions" style="display: none; max-height: 200px; overflow: auto;"><p>Termos de serviço do site...</p></div>';
    }
}

if (!function_exists('wc_checkout_privacy_policy_text')) {
    function wc_checkout_privacy_policy_text() {
        echo '<div class="woocommerce-privacy-policy-text"><p>Seus dados pessoais serão utilizados para processar sua compra conforme nossa <a href="https://example.com/privacy-policy" class="woocommerce-privacy-policy-link" target="_blank">política de privacidade</a>.</p></div>';
    }
}

if (!function_exists('wc_add_notice')) {
    function wc_add_notice($message, $type = 'error') {
        global $wc_mock_notices;
        if (!is_array($wc_mock_notices)) {
            $wc_mock_notices = [];
        }
        $wc_mock_notices[] = ['message' => $message, 'type' => $type];
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID = 1;
        public $post_title = '';
        public $post_content = '';

        public function __construct($data = []) {
            foreach ($data as $k => $v) {
                $this->$k = $v;
            }
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];

        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
        }

        public function get_error_messages($code = '') {
            if ($code) {
                return $this->errors[$code] ?? [];
            }
            $all = [];
            foreach ($this->errors as $messages) {
                $all = array_merge($all, $messages);
            }
            return $all;
        }

        public function has_errors() {
            return !empty($this->errors);
        }
    }
}

global $wp_mock_user_caps;
$wp_mock_user_caps = [
    'manage_woocommerce' => true,
    'manage_options' => true,
];

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        global $wp_mock_user_caps;
        return !empty($wp_mock_user_caps[$capability]);
    }
}

if (!function_exists('wc_get_products')) {
    function wc_get_products($args = []) {
        return [
            new Mock_WC_Product(10, 'Curso Principal', '150.00'),
            new Mock_WC_Product(55, 'E-book Exclusivo', '29.90'),
        ];
    }
}

if (!function_exists('woocommerce_admin_fields')) {
    function woocommerce_admin_fields($options) {
        return true;
    }
}

if (!function_exists('woocommerce_update_options')) {
    function woocommerce_update_options($options) {
        foreach ($options as $opt) {
            if (isset($opt['id'], $_POST[$opt['id']])) {
                update_option($opt['id'], sanitize_text_field(wp_unslash($_POST[$opt['id']])));
            }
        }
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        throw new \Exception((string)$message);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field, $index_key = null) {
        $result = [];
        foreach ((array) $list as $key => $value) {
            if (is_object($value)) {
                $val = $value->$field ?? null;
            } else {
                $val = $value[$field] ?? null;
            }
            if ($index_key) {
                $idx = is_object($value) ? ($value->$index_key ?? null) : ($value[$index_key] ?? null);
                $result[$idx] = $val;
            } else {
                $result[] = $val;
            }
        }
        return $result;
    }
}

// Mocks do WooCommerce para ambiente de testes
if (!class_exists('Mock_WC_Product')) {
    class Mock_WC_Product {
        private $id;
        private $name;
        private $price;
        private $purchasable = true;
        private $in_stock = true;

        public function __construct($id = 1, $name = 'Produto Teste', $price = '99.00') {
            $this->id = $id;
            $this->name = $name;
            $this->price = $price;
        }

        public function get_id() { return $this->id; }
        public function get_name() { return $this->name; }
        public function get_price() { return $this->price; }
        public function set_price($price) { $this->price = (string) $price; }
        public function is_purchasable() { return $this->purchasable; }
        public function set_purchasable($val) { $this->purchasable = (bool) $val; }
        public function is_in_stock() { return $this->in_stock; }
        public function set_in_stock($val) { $this->in_stock = (bool) $val; }
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id) {
        if (!$product_id) return false;
        return new Mock_WC_Product($product_id);
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product extends Mock_WC_Product {}
}

if (!class_exists('Mock_WC_Payment_Gateway')) {
    class Mock_WC_Payment_Gateway {
        public $id;
        public $title;
        public $description;
        public $order_button_text;

        public function __construct($id = 'bacs', $title = 'Transferência Bancária', $desc = '') {
            $this->id = $id;
            $this->title = $title;
            $this->description = $desc;
            $this->order_button_text = 'Realizar Pagamento';
        }

        public function get_title() { return $this->title; }
        public function get_description() { return $this->description; }
        public function has_fields() { return false; }
        public function payment_fields() { echo '<p>' . esc_html($this->description) . '</p>'; }
    }
}

if (!class_exists('Mock_WC_Payment_Gateways')) {
    class Mock_WC_Payment_Gateways {
        public function get_available_payment_gateways() {
            return [
                'pix' => new Mock_WC_Payment_Gateway('pix', 'Pix', 'Pagamento instantâneo'),
                'bacs' => new Mock_WC_Payment_Gateway('bacs', 'Transferência Bancária', 'Pague via TED'),
            ];
        }
    }
}

if (!class_exists('Mock_WC_Cart')) {
    class Mock_WC_Cart {
        public $items = [];

        public function is_empty() { return empty($this->items); }
        public function get_cart() {
            return $this->items;
        }
        public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variation = [], $cart_item_data = []) {
            $key = 'item_' . $product_id . '_' . count($this->items);
            $product = wc_get_product($product_id);
            if (!$product) {
                return false;
            }
            $this->items[$key] = array_merge([
                'key' => $key,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'data' => $product,
            ], $cart_item_data);
            $this->calculate_totals();
            return $key;
        }
        public function remove_cart_item($cart_item_key) {
            unset($this->items[$cart_item_key]);
            $this->calculate_totals();
            return true;
        }
        public function calculate_totals() {
            // Recalcula totais
            return true;
        }
        public function get_product_subtotal($product, $quantity) {
            return 'R$ ' . number_format((float)$product->get_price() * $quantity, 2, ',', '.');
        }
        public function get_subtotal() {
            $sub = 0;
            foreach ($this->items as $item) {
                $sub += (float)$item['data']->get_price() * $item['quantity'];
            }
            return $sub;
        }
        public function get_discount_total() { return 0.00; }
        public function get_total($context = 'view') {
            return 'R$ ' . number_format($this->get_subtotal(), 2, ',', '.');
        }
        public function get_applied_coupons() { return []; }
    }
}

if (!class_exists('WC_Cart')) {
    class WC_Cart extends Mock_WC_Cart {}
}

if (!class_exists('Mock_WC_Checkout')) {
    class Mock_WC_Checkout {
        public function get_value($key) {
            return '';
        }
    }
}

if (!class_exists('Mock_WooCommerce')) {
    class Mock_WooCommerce {
        public $cart;
        public $payment_gateways;
        public $checkout;

        public function __construct() {
            $this->cart = new Mock_WC_Cart();
            $this->payment_gateways = new Mock_WC_Payment_Gateways();
            $this->checkout = new Mock_WC_Checkout();
        }

        public function payment_gateways() {
            return $this->payment_gateways;
        }

        public function checkout() {
            return $this->checkout;
        }
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($val) {
        return is_string($val) ? stripslashes($val) : $val;
    }
}

if (!function_exists('wc_clean')) {
    function wc_clean($var) {
        if (is_array($var)) {
            return array_map('wc_clean', $var);
        }
        return sanitize_text_field($var);
    }
}

if (!function_exists('wc_format_datetime')) {
    function wc_format_datetime($date) {
        return '01/09/2026 12:00';
    }
}

if (!function_exists('wc_get_order_status_name')) {
    function wc_get_order_status_name($status) {
        $names = [
            'pending' => 'Pagamento pendente',
            'processing' => 'Processando',
            'on-hold' => 'Aguardando',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'failed' => 'Falhou',
        ];
        return $names[$status] ?? ucfirst((string)$status);
    }
}

if (!function_exists('wc_get_account_endpoint_url')) {
    function wc_get_account_endpoint_url($endpoint) {
        return 'https://example.com/my-account/' . $endpoint;
    }
}

if (!function_exists('wc_get_page_permalink')) {
    function wc_get_page_permalink($page) {
        return 'https://example.com/' . $page;
    }
}

if (!function_exists('is_wc_endpoint_url')) {
    function is_wc_endpoint_url($endpoint = false) {
        global $wp;
        if ($endpoint && isset($wp->query_vars[$endpoint])) {
            return true;
        }
        return false;
    }
}

if (!class_exists('Mock_WC_Order_Item')) {
    class Mock_WC_Order_Item {
        private $name;
        private $quantity;
        private $price;

        public function __construct($name = 'Item Teste', $qty = 1, $price = '100.00') {
            $this->name = $name;
            $this->quantity = $qty;
            $this->price = $price;
        }

        public function get_name() { return $this->name; }
        public function get_quantity() { return $this->quantity; }
        public function get_product() { return new Mock_WC_Product(1, $this->name, $this->price); }
    }
}

if (!class_exists('Mock_WC_Order_Complete')) {
    class Mock_WC_Order_Complete {
        public $id = 123;
        public $order_key = 'wc_order_test_key_123';
        public $status = 'processing';
        public $meta = [];
        public $billing = [
            'first_name' => 'João',
            'last_name' => 'Silva',
            'email' => 'joao@example.com',
            'phone' => '(11) 99999-9999',
        ];

        public function __construct($id = 123, $key = 'wc_order_test_key_123') {
            $this->id = $id;
            $this->order_key = $key;
        }

        public function get_id() { return $this->id; }
        public function get_order_key() { return $this->order_key; }
        public function get_order_number() { return (string) $this->id; }
        public function get_status() { return $this->status; }
        public function has_status($status) { return $this->status === $status; }
        public function set_status($status) { $this->status = $status; }
        public function get_date_created() { return '2026-09-01 12:00:00'; }
        public function get_billing_first_name() { return $this->billing['first_name']; }
        public function get_billing_last_name() { return $this->billing['last_name']; }
        public function get_billing_email() { return $this->billing['email']; }
        public function get_billing_phone() { return $this->billing['phone']; }
        public function get_formatted_order_total() { return 'R$ 150,00'; }
        public function get_payment_method() { return 'pix'; }
        public function get_payment_method_title() { return 'Pix'; }
        public function get_items() { return [new Mock_WC_Order_Item('Curso Principal', 1, '150.00')]; }
        public function get_formatted_line_subtotal($item) { return 'R$ 150,00'; }
        public function get_subtotal() { return 150.00; }
        public function get_total_discount() { return 0.00; }
        public function get_total_tax() { return 0.00; }
        public function get_shipping_total() { return 0.00; }
        public function get_customer_note() { return ''; }
        public function get_checkout_payment_url() { return 'https://example.com/checkout/pay/' . $this->id; }
        public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
        public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
        public function delete_meta_data($key) { unset($this->meta[$key]); }
        public function save() { return true; }
    }
}

global $wp_mock_orders;
$wp_mock_orders = [];

if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        global $wp_mock_orders;
        if (isset($wp_mock_orders[$order_id])) {
            return $wp_mock_orders[$order_id];
        }
        if ($order_id > 0) {
            $order = new Mock_WC_Order_Complete($order_id);
            return $order;
        }
        return false;
    }
}

if (!function_exists('WC')) {
    function WC() {
        global $mock_woocommerce_instance;
        if (!isset($mock_woocommerce_instance)) {
            $mock_woocommerce_instance = new Mock_WooCommerce();
        }
        return $mock_woocommerce_instance;
    }
}
