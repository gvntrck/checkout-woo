<?php
/**
 * Uninstall GVN Checkout for WooCommerce.
 * Remove todas as opções e transients do plugin de forma segura sem tocar em pedidos ou clientes.
 *
 * @package GVN_Checkout
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Carrega o autoloader se disponível
$autoload_path = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload_path ) ) {
    require_once $autoload_path;
}

if ( class_exists( 'GVN\Checkout\Lifecycle\Uninstaller' ) ) {
    \GVN\Checkout\Lifecycle\Uninstaller::uninstall();
} else {
    // Fallback caso autoloader não esteja presente
    $option_keys = array(
        'gvn_checkout_settings',
        'gvn_checkout_fields',
        'gvn_checkout_default_fields_config',
        'gvn_checkout_version',
        'gvn_checkout_migrated_from_legacy',
        'gvn_checkout_migration_in_progress',
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
    );

    foreach ( $option_keys as $key ) {
        delete_option( $key );
    }

    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }

    global $wpdb;
    if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->options ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' ) ) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_gvn_cep_%',
                '_transient_timeout_gvn_cep_%'
            )
        );
    }
}
