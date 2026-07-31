<?php
/**
 * Uninstall GVN Checkout for WooCommerce.
 * Remove todas as opções e transients do plugin.
 *
 * @package GVN_Checkout
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Lista de opções do plugin.
$option_keys = array(
    'gvn_checkout_header_text',
    'gvn_checkout_header_badge_text',
    'gvn_checkout_header_bg_color',
    'gvn_checkout_badge_bg_color',
    'gvn_checkout_primary_color',
    'gvn_checkout_button_color',
    'gvn_checkout_button_text',
    'gvn_checkout_order_bump_enabled',
    'gvn_checkout_order_bump_product_id',
    'gvn_checkout_order_bump_title',
    'gvn_checkout_order_bump_description',
    'gvn_checkout_order_bump_cta_text',
    'gvn_checkout_order_bump_price',
    'gvn_checkout_fields',
    'gvn_checkout_default_fields_config',
);

foreach ( $option_keys as $key ) {
    delete_option( $key );
}

// Limpa transients de cache de CEP.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gvn_cep_%' OR option_name LIKE '_transient_timeout_gvn_cep_%'"
);
