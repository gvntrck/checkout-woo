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
    // Fallback caso autoloader não esteja presente: remove por prefixo,
    // cobrindo opções atuais e futuras sem depender de lista manual.
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }

    global $wpdb;
    if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->options ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' ) ) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                'gvn_checkout\_%',
                'gvn_flag\_%',
                '_transient_gvn_cep_%',
                '_transient_timeout_gvn_cep_%'
            )
        );
    }
}
