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
