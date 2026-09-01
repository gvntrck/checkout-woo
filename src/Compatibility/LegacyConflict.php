<?php

namespace GVN\Checkout\Compatibility;

/**
 * Detecção e aviso de conflito com o plugin histórico checkout-woo-2.
 */
class LegacyConflict {

    /**
     * Verifica se o plugin histórico checkout-woo-2 está ativo simultaneamente.
     *
     * @return bool
     */
    public static function check(): bool {
        if (defined('CGV_VERSION') || class_exists('CGV_Plugin') || defined('CGV_FILE')) {
            add_action('admin_notices', [__CLASS__, 'render_notice']);
            return true;
        }
        return false;
    }

    public static function render_notice(): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('GVN Checkout — Conflito detectado:', 'gvn-checkout'); ?></strong>
                <?php esc_html_e('O plugin histórico Checkout GVNTRCK (checkout-woo-2) está ativo simultaneamente. Para evitar conflitos de fluxo e hooks no checkout, mantenha apenas o GVN Checkout for WooCommerce ativo.', 'gvn-checkout'); ?>
            </p>
        </div>
        <?php
    }
}
