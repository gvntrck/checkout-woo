<?php
/**
 * Classe principal do GVN Checkout.
 * Registra o shortcode [gvn-checkout] e gerencia hooks do WooCommerce.
 *
 * @package GVN_Checkout
 * @version 1.13.25
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GVN_Checkout {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'gvn-checkout', array( $this, 'render_checkout' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_gvn_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_nopriv_gvn_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_gvn_remove_coupon', array( $this, 'ajax_remove_coupon' ) );
        add_action( 'wp_ajax_nopriv_gvn_remove_coupon', array( $this, 'ajax_remove_coupon' ) );
        add_filter( 'wc_get_template', array( $this, 'override_thankyou_template' ), 10, 5 );
    }

    /**
     * Enqueue de CSS e JS apenas nas páginas que contêm o shortcode.
     */
    public function enqueue_assets() {
        global $post;

        $is_checkout_page = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'gvn-checkout' );
        $is_thankyou_page = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );

        if ( ! $is_checkout_page && ! $is_thankyou_page ) {
            return;
        }

        wp_enqueue_style(
            'gvn-checkout-css',
            GVN_CHECKOUT_PLUGIN_URL . 'assets/css/gvn-checkout.css',
            array(),
            GVN_CHECKOUT_VERSION
        );

        // JS do checkout só é necessário na página de checkout (não na thank you).
        if ( $is_checkout_page ) {
            wp_enqueue_script(
                'gvn-checkout-js',
                GVN_CHECKOUT_PLUGIN_URL . 'assets/js/gvn-checkout.js',
                array( 'jquery', 'wc-checkout' ),
                GVN_CHECKOUT_VERSION,
                true
            );

            wp_localize_script( 'gvn-checkout-js', 'gvn_checkout_params', array(
                'ajax_url'    => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'gvn_checkout_nonce' ),
                'wc_ajax_url' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
            ) );
        }

        $this->enqueue_inline_colors();
    }

    /**
     * Adiciona o CSS inline com as cores configuradas (uma única vez).
     */
    private function enqueue_inline_colors() {
        $primary_color = get_option( 'gvn_checkout_primary_color', '#0066d4' );
        $button_color  = get_option( 'gvn_checkout_button_color', '#ff8a22' );
        $header_bg     = get_option( 'gvn_checkout_header_bg_color', '#3a4759' );
        $badge_bg      = get_option( 'gvn_checkout_badge_bg_color', '#ff8a22' );

        $custom_css = "
            :root {
                --gvn-primary: {$primary_color};
                --gvn-button: {$button_color};
                --gvn-header-bg: {$header_bg};
                --gvn-badge-bg: {$badge_bg};
            }
        ";
        wp_add_inline_style( 'gvn-checkout-css', $custom_css );
    }

    /**
     * Renderiza o shortcode [gvn-checkout].
     */
    public function render_checkout( $atts ) {
        if ( ! function_exists( 'WC' ) ) {
            return '<p>WooCommerce não está disponível.</p>';
        }

        global $wp;

        // Detecta endpoint order-received (página de confirmação do pedido).
        // Renderiza o thankyou aqui para suportar páginas que usam apenas [gvn-checkout]
        // (sem o shortcode nativo [woocommerce_checkout]).
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
            return $this->render_thankyou_page();
        }

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return '<div class="gvn-checkout-empty">
                <p>Seu carrinho está vazio. <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Voltar à loja</a></p>
            </div>';
        }

        if ( ! is_user_logged_in() && 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ) {
            return '<p>Você precisa estar logado para finalizar a compra. <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">Faça login</a></p>';
        }

        $checkout = WC()->checkout();

        if ( ! $checkout ) {
            return '<p>Não foi possível inicializar o checkout. Tente novamente.</p>';
        }

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/checkout-template.php';
        return ob_get_clean();
    }

    /**
     * Renderiza a página de confirmação de pedido (thank you).
     */
    private function render_thankyou_page() {
        global $wp;

        $order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
        $order    = false;

        if ( $order_id > 0 ) {
            $order = wc_get_order( $order_id );
        }

        // Validação de segurança com a key do pedido.
        $order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
        if ( $order && $order->get_order_key() !== $order_key ) {
            $order = false;
        }

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/thankyou-template.php';
        return ob_get_clean();
    }

    /**
     * AJAX: Aplicar cupom.
     */
    public function ajax_apply_coupon() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array( 'message' => 'Carrinho não disponível.' ) );
        }

        $coupon_code = isset( $_POST['coupon_code'] ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : '';

        if ( empty( $coupon_code ) ) {
            wp_send_json_error( array( 'message' => 'Informe um código de cupom.' ) );
        }

        $result = WC()->cart->apply_coupon( $coupon_code );

        // Em WC moderno apply_coupon pode retornar true, string (cart coupon msg) ou WP_Error.
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        if ( true === $result || ( is_string( $result ) && '' !== $result ) ) {
            wp_send_json_success( array(
                'message'  => 'Cupom aplicado com sucesso!',
                'total'    => WC()->cart->get_total(),
                'subtotal' => WC()->cart->get_subtotal(),
                'discount' => WC()->cart->get_discount_total(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Cupom inválido ou já aplicado.' ) );
        }
    }

    /**
     * AJAX: Remover cupom.
     */
    public function ajax_remove_coupon() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array( 'message' => 'Carrinho não disponível.' ) );
        }

        $coupon_code = isset( $_POST['coupon_code'] ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : '';

        if ( empty( $coupon_code ) ) {
            wp_send_json_error( array( 'message' => 'Informe o código do cupom.' ) );
        }

        WC()->cart->remove_coupon( $coupon_code );
        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'message'  => 'Cupom removido.',
            'total'    => WC()->cart->get_total(),
            'subtotal' => WC()->cart->get_subtotal(),
        ) );
    }

    /**
     * Substitui o template padrão de thankyou do WooCommerce pelo template customizado.
     * Só é chamado quando a página usa o shortcode nativo [woocommerce_checkout].
     * Quando a página usa [gvn-checkout], o thankyou é renderizado por render_thankyou_page().
     */
    public function override_thankyou_template( $template, $template_name, $args, $template_path, $default_path ) {
        if ( 'checkout/thankyou.php' === $template_name ) {
            $custom_template = GVN_CHECKOUT_PLUGIN_DIR . 'templates/thankyou-template.php';
            if ( file_exists( $custom_template ) ) {
                // Prepara $order validando a order_key se ainda não foi setado.
                if ( ! isset( $GLOBALS['order'] ) || ! $GLOBALS['order'] ) {
                    global $wp, $order;
                    $order = false;
                    $order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
                    if ( $order_id > 0 ) {
                        $order = wc_get_order( $order_id );
                    }
                    $order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
                    if ( $order && $order->get_order_key() !== $order_key ) {
                        $order = false;
                    }
                }
                return $custom_template;
            }
        }
        return $template;
    }

    /**
     * Enqueue de CSS na página de thank you (order-received).
     * Removido: lógica unificada em enqueue_assets() + enqueue_inline_colors().
     */
}
