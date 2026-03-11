<?php
/**
 * Plugin Name: GVN Checkout for WooCommerce
 * Plugin URI: https://github.com/gvntrck/checkout-woo
 * Description: Checkout personalizado e otimizado para WooCommerce com layout moderno, order bump e configurações avançadas.
 * Version:1.10.5
 * Author: GVN Track
 * Author URI: https://projetoalfa.org
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gvn-checkout
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GVN_CHECKOUT_VERSION', '1.10.5');
define('GVN_CHECKOUT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GVN_CHECKOUT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GVN_CHECKOUT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Verifica se o WooCommerce está ativo antes de inicializar o plugin.
 */
function gvn_checkout_check_woocommerce()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'gvn_checkout_woocommerce_missing_notice');
        return false;
    }
    return true;
}

function gvn_checkout_woocommerce_missing_notice()
{
    ?>
    <div class="notice notice-error">
        <p><strong>GVN Checkout</strong> requer o <a href="https://woocommerce.com/" target="_blank">WooCommerce</a>
            instalado e ativado.</p>
    </div>
    <?php
}

/**
 * Inicializa o plugin após todos os plugins serem carregados.
 */
function gvn_checkout_init()
{
    if (!gvn_checkout_check_woocommerce()) {
        return;
    }

    require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-custom-fields.php';
    require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-checkout.php';
    require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-admin.php';
    require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-order-bump.php';

    GVN_Custom_Fields::get_instance();
    GVN_Checkout::get_instance();
    GVN_Admin::get_instance();
    GVN_Order_Bump::get_instance();
}
add_action('plugins_loaded', 'gvn_checkout_init');

/**
 * Declara compatibilidade com HPOS e Cart/Checkout Blocks do WooCommerce.
 */
function gvn_checkout_declare_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
    }
}
add_action('before_woocommerce_init', 'gvn_checkout_declare_compatibility');

/**
 * Ativação do plugin — define opções padrão.
 */
function gvn_checkout_activate()
{
    $defaults = array(
        'header_text' => 'EFEAD - Conectando Saberes',
        'header_badge_text' => 'COMPRA SEGURA',
        'header_bg_color' => '#3a4759',
        'badge_bg_color' => '#ff8a22',
        'primary_color' => '#0066d4',
        'button_color' => '#ff8a22',
        'button_text' => 'Finalizar pedido',
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
}
register_activation_hook(__FILE__, 'gvn_checkout_activate');
