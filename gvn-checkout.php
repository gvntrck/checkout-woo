<?php
/**
 * Plugin Name: GVN Checkout for WooCommerce
 * Plugin URI: https://github.com/gvntrck/checkout-woo
 * Description: Checkout personalizado e otimizado para WooCommerce com layout moderno, order bump e configurações avançadas.
 * Version: 1.14.0
 * Author: GVN Track
 * Author URI: https://projetoalfa.org
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gvn-checkout
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * Update URI: false
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes essenciais do plugin
define('GVN_CHECKOUT_VERSION', '1.14.0');
define('GVN_CHECKOUT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GVN_CHECKOUT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GVN_CHECKOUT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader PSR-4 para o namespace GVN\Checkout
if (file_exists(GVN_CHECKOUT_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once GVN_CHECKOUT_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'GVN\\Checkout\\';
        $base_dir = GVN_CHECKOUT_PLUGIN_DIR . 'src/';
        $len = strlen($prefix);

        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Declaração precoce de compatibilidade com WooCommerce HPOS e Blocks
add_action('before_woocommerce_init', ['\\GVN\\Checkout\\Plugin', 'declare_woocommerce_compatibility']);

// Bootstrap principal do plugin
add_action('plugins_loaded', function () {
    \GVN\Checkout\Plugin::instance()->boot();
});

/**
 * Ativação do plugin — inicializa opções e feature flags padrão.
 */
function gvn_checkout_activate() {
    $defaults = array(
        'checkout_layout' => 'classic',
        'header_text' => 'EFEAD - Conectando Saberes',
        'header_badge_text' => 'COMPRA SEGURA',
        'header_bg_color' => '#3a4759',
        'badge_bg_color' => '#ff8a22',
        'primary_color' => '#0066d4',
        'button_color' => '#ff8a22',
        'button_text' => 'Finalizar pedido',
        'coupon_enabled' => 'yes',
        'order_bump_enabled' => 'no',
        'order_bump_product_id' => '',
        'order_bump_title' => 'Oferta Exclusiva',
        'order_bump_description' => 'Adicione este item ao seu pedido com condições especiais.',
        'order_bump_cta_text' => 'Sim! Quero adicionar ao meu pedido',
        'order_bump_price' => '',
    );

    foreach ($defaults as $key => $value) {
        if (false === get_option('gvn_checkout_' . $key)) {
            update_option('gvn_checkout_' . $key, $value);
        }
    }

    // Inicializa feature flags para clean install
    $flag_defaults = \GVN\Checkout\Support\Features::get_clean_install_defaults();
    foreach ($flag_defaults as $flag => $val) {
        if (false === get_option($flag)) {
            update_option($flag, $val ? 'yes' : 'no');
        }
    }
}
register_activation_hook(__FILE__, 'gvn_checkout_activate');

/**
 * Desativação do plugin — limpa transients de cache de CEP.
 */
function gvn_checkout_deactivate() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gvn_cep_%' OR option_name LIKE '_transient_timeout_gvn_cep_%'"
    );
}
register_deactivation_hook(__FILE__, 'gvn_checkout_deactivate');
