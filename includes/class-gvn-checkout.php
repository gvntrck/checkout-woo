<?php
/**
 * Classe principal do GVN Checkout.
 * Registra o shortcode [gvn-checkout] e gerencia hooks do WooCommerce.
 *
 * @package GVN_Checkout
 * @version 1.0.0
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
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_thankyou_assets' ) );
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
        if ( is_wc_endpoint_url( 'order-received' ) ) {
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

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/checkout-template.php';
        return ob_get_clean();
    }

    /**
     * Renderiza a página de confirmação de pedido (thank you).
     */
    private function render_thankyou_page() {
        global $wp;

        $order_id = absint( $wp->query_vars['order-received'] );
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

        $coupon_code = isset( $_POST['coupon_code'] ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : '';

        if ( empty( $coupon_code ) ) {
            wp_send_json_error( array( 'message' => 'Informe um código de cupom.' ) );
        }

        $result = WC()->cart->apply_coupon( $coupon_code );

        if ( $result ) {
            wp_send_json_success( array(
                'message'  => 'Cupom aplicado com sucesso!',
                'total'    => WC()->cart->get_total(),
                'subtotal' => WC()->cart->get_subtotal(),
                'discount' => WC()->cart->get_discount_total(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Cupom inválido.' ) );
        }
    }

    /**
     * AJAX: Remover cupom.
     */
    public function ajax_remove_coupon() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        $coupon_code = isset( $_POST['coupon_code'] ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : '';

        WC()->cart->remove_coupon( $coupon_code );

        wp_send_json_success( array(
            'message'  => 'Cupom removido.',
            'total'    => WC()->cart->get_total(),
            'subtotal' => WC()->cart->get_subtotal(),
        ) );
    }

    /**
     * Substitui o template padrão de thankyou do WooCommerce pelo template customizado.
     */
    public function override_thankyou_template( $template, $template_name, $args, $template_path, $default_path ) {
        if ( 'checkout/thankyou.php' === $template_name ) {
            $custom_template = GVN_CHECKOUT_PLUGIN_DIR . 'templates/thankyou-template.php';
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }
        return $template;
    }

    /**
     * Enqueue de CSS na página de thank you (order-received).
     */
    public function enqueue_thankyou_assets() {
        if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
            return;
        }

        wp_enqueue_style(
            'gvn-checkout-css',
            GVN_CHECKOUT_PLUGIN_URL . 'assets/css/gvn-checkout.css',
            array(),
            GVN_CHECKOUT_VERSION
        );

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
}
