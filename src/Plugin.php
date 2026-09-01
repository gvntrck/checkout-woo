<?php

namespace GVN\Checkout;

use GVN\Checkout\Compatibility\LegacyConflict;
use GVN\Checkout\Support\Requirements;

/**
 * Composition Root e ponto central de orquestração do GVN Checkout for WooCommerce.
 */
final class Plugin {

    /**
     * @var Plugin|null Instância singleton principal para acesso ao container de serviços.
     */
    private static $instance = null;

    /**
     * @var array<string, object> Módulos e serviços registrados.
     */
    private $modules = [];

    /**
     * @var bool Flag indicando se o plugin foi inicializado.
     */
    private $initialized = false;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Inicialização encapsulada.
    }

    /**
     * Ponto de entrada do bootstrap registrado em plugins_loaded.
     */
    public function boot(): void {
        if ($this->initialized) {
            return;
        }

        // Validação de requisitos de runtime (PHP, WP, WC).
        if (!Requirements::check()) {
            return;
        }

        // Detecção de conflito com checkout-woo-2.
        LegacyConflict::check();

        // Carregamento de traduções no hook 'init' apropriado.
        add_action('init', [$this, 'load_textdomain']);

        // Carregamento dos módulos nos contextos adequados.
        $this->register_legacy_modules();

        $this->initialized = true;
    }

    /**
     * Declaração de compatibilidade com HPOS e Blocks do WooCommerce.
     */
    public static function declare_woocommerce_compatibility(): void {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            $plugin_file = defined('GVN_CHECKOUT_PLUGIN_DIR') ? GVN_CHECKOUT_PLUGIN_DIR . 'gvn-checkout.php' : __FILE__;
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', $plugin_file, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', $plugin_file, false);
        }
    }

    /**
     * Carrega as traduções do plugin.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain('gvn-checkout', false, dirname(plugin_basename(GVN_CHECKOUT_PLUGIN_DIR . 'gvn-checkout.php')) . '/languages');
    }

    /**
     * Registra e instancia os módulos legados existentes preservando compatibilidade.
     */
    private function register_legacy_modules(): void {
        require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-custom-fields.php';
        require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-checkout.php';
        require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-admin.php';
        require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-order-bump.php';
        require_once GVN_CHECKOUT_PLUGIN_DIR . 'includes/class-gvn-address-validation.php';

        $this->modules['custom_fields']       = \GVN_Custom_Fields::get_instance();
        $this->modules['checkout']            = \GVN_Checkout::get_instance();
        $this->modules['order_bump']          = \GVN_Order_Bump::get_instance();
        $this->modules['address_validation']  = \GVN_Address_Validation::get_instance();

        // Carrega módulo administrativo apenas no contexto admin ou AJAX.
        if (is_admin() || wp_doing_ajax()) {
            $this->modules['admin'] = \GVN_Admin::get_instance();
        }
    }

    /**
     * Retorna um módulo registrado pelo nome.
     *
     * @param string $name
     * @return object|null
     */
    public function get_module(string $name): ?object {
        return $this->modules[$name] ?? null;
    }

    /**
     * Verifica se a requisição atual é puramente de frontend do checkout (não-REST, não-cron, não-webhook).
     *
     * @return bool
     */
    public function is_frontend_checkout_context(): bool {
        if (is_admin() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if (isset($_GET['wc-api']) || isset($_GET['webhook'])) {
            return false;
        }

        return true;
    }
}
