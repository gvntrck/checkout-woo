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

if (!defined('GVN_CHECKOUT_VERSION')) {
    define('GVN_CHECKOUT_VERSION', '1.13.19');
}

// Mocks e stubs básicos de WordPress para testes unitários em isolamento (sem banco de dados).
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

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
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

if (!function_exists('wc_add_notice')) {
    function wc_add_notice($message, $type = 'error') {
        global $wc_mock_notices;
        if (!is_array($wc_mock_notices)) {
            $wc_mock_notices = [];
        }
        $wc_mock_notices[] = ['message' => $message, 'type' => $type];
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

