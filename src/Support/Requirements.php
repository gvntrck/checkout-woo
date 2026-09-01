<?php

namespace GVN\Checkout\Support;

/**
 * Verificação de requisitos de runtime para o GVN Checkout for WooCommerce.
 */
class Requirements {

    const MIN_PHP_VERSION = '7.4';
    const MIN_WP_VERSION  = '6.0';
    const MIN_WC_VERSION  = '7.0';

    /**
     * Valida se o ambiente atende a todos os requisitos.
     *
     * @return bool
     */
    public static function check(): bool {
        if (!self::is_php_compatible()) {
            add_action('admin_notices', [__CLASS__, 'render_php_notice']);
            return false;
        }

        if (!self::is_wp_compatible()) {
            add_action('admin_notices', [__CLASS__, 'render_wp_notice']);
            return false;
        }

        if (!self::is_wc_active()) {
            add_action('admin_notices', [__CLASS__, 'render_wc_missing_notice']);
            return false;
        }

        if (!self::is_wc_compatible()) {
            add_action('admin_notices', [__CLASS__, 'render_wc_version_notice']);
            return false;
        }

        return true;
    }

    public static function is_php_compatible(): bool {
        return version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
    }

    public static function is_wp_compatible(): bool {
        global $wp_version;
        if (empty($wp_version)) {
            return true;
        }
        return version_compare($wp_version, self::MIN_WP_VERSION, '>=');
    }

    public static function is_wc_active(): bool {
        return class_exists('WooCommerce');
    }

    public static function is_wc_compatible(): bool {
        if (!defined('WC_VERSION')) {
            return true;
        }
        return version_compare(WC_VERSION, self::MIN_WC_VERSION, '>=');
    }

    public static function render_php_notice(): void {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('GVN Checkout — Incompatibilidade:', 'gvn-checkout'); ?></strong>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: versão mínima requerida do PHP, 2: versão atual do PHP */
                        __('O plugin requer PHP versão %1$s ou superior. A versão atual é %2$s.', 'gvn-checkout'),
                        self::MIN_PHP_VERSION,
                        PHP_VERSION
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    public static function render_wp_notice(): void {
        global $wp_version;
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('GVN Checkout — Incompatibilidade:', 'gvn-checkout'); ?></strong>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: versão mínima requerida do WordPress, 2: versão atual do WordPress */
                        __('O plugin requer WordPress versão %1$s ou superior. A versão atual é %2$s.', 'gvn-checkout'),
                        self::MIN_WP_VERSION,
                        (string) $wp_version
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }

    public static function render_wc_missing_notice(): void {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('GVN Checkout — Dependência ausente:', 'gvn-checkout'); ?></strong>
                <?php esc_html_e('Este plugin requer o WooCommerce instalado e ativado.', 'gvn-checkout'); ?>
            </p>
        </div>
        <?php
    }

    public static function render_wc_version_notice(): void {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('GVN Checkout — Incompatibilidade:', 'gvn-checkout'); ?></strong>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: versão mínima requerida do WooCommerce, 2: versão atual do WooCommerce */
                        __('O plugin requer WooCommerce versão %1$s ou superior. A versão atual é %2$s.', 'gvn-checkout'),
                        self::MIN_WC_VERSION,
                        defined('WC_VERSION') ? WC_VERSION : 'desconhecida'
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }
}
