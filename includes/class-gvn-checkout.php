<?php
/**
 * Classe principal do GVN Checkout.
 * Registra o shortcode [gvn-checkout] e gerencia hooks do WooCommerce.
 *
 * @package GVN_Checkout
 * @version 1.13.36
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
        add_filter( 'body_class', array( $this, 'add_body_classes' ) );
        add_action( 'wp_ajax_gvn_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_nopriv_gvn_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_gvn_remove_coupon', array( $this, 'ajax_remove_coupon' ) );
        add_action( 'wp_ajax_nopriv_gvn_remove_coupon', array( $this, 'ajax_remove_coupon' ) );
        add_filter( 'wc_get_template', array( $this, 'override_thankyou_template' ), 10, 5 );
    }

    /**
     * Adiciona classe ao body para desbloquear a largura total dos containers dos temas.
     */
    public function add_body_classes( $classes ) {
        global $post;

        $is_checkout_page = is_object( $post ) && isset( $post->post_content ) && has_shortcode( $post->post_content, 'gvn-checkout' );
        $is_thankyou_page = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );

        if ( $is_checkout_page || $is_thankyou_page ) {
            $classes[] = 'gvn-checkout-active';
        }

        return $classes;
    }

    /**
     * Enqueue de CSS e JS apenas nas páginas que contêm o shortcode.
     */
    public function enqueue_assets() {
        global $post;

        $is_checkout_page = is_object( $post ) && isset( $post->post_content ) && has_shortcode( $post->post_content, 'gvn-checkout' );
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
                'wc_ajax_url' => function_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
                'gateway_requirements' => class_exists( 'GVN\\Checkout\\Payments\\GatewayRequirementsResolver' )
                    ? ( new \GVN\Checkout\Payments\GatewayRequirementsResolver() )->get_frontend_requirements()
                    : array(),
            ) );
        }

        $this->enqueue_inline_colors();
    }

    /**
     * Adiciona o CSS inline com as cores configuradas (sanitizadas estritamente).
     */
    private function enqueue_inline_colors() {
        $primary_color = class_exists( 'GVN\Checkout\Settings\SettingsRepository' )
            ? \GVN\Checkout\Settings\SettingsRepository::get( 'primary_color', '#0066d4' )
            : get_option( 'gvn_checkout_primary_color', '#0066d4' );
        $button_color  = class_exists( 'GVN\Checkout\Settings\SettingsRepository' )
            ? \GVN\Checkout\Settings\SettingsRepository::get( 'button_color', '#ff8a22' )
            : get_option( 'gvn_checkout_button_color', '#ff8a22' );
        $header_bg     = class_exists( 'GVN\Checkout\Settings\SettingsRepository' )
            ? \GVN\Checkout\Settings\SettingsRepository::get( 'header_bg_color', '#3a4759' )
            : get_option( 'gvn_checkout_header_bg_color', '#3a4759' );
        $badge_bg      = class_exists( 'GVN\Checkout\Settings\SettingsRepository' )
            ? \GVN\Checkout\Settings\SettingsRepository::get( 'badge_bg_color', '#ff8a22' )
            : get_option( 'gvn_checkout_badge_bg_color', '#ff8a22' );

        $primary_color = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $primary_color ) ) ? sanitize_hex_color( (string) $primary_color ) : '#0066d4';
        $button_color  = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $button_color ) ) ? sanitize_hex_color( (string) $button_color ) : '#ff8a22';
        $header_bg     = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $header_bg ) ) ? sanitize_hex_color( (string) $header_bg ) : '#3a4759';
        $badge_bg      = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $badge_bg ) ) ? sanitize_hex_color( (string) $badge_bg ) : '#ff8a22';

        $custom_css = sprintf(
            ":root {\n    --gvn-primary: %s;\n    --gvn-button: %s;\n    --gvn-header-bg: %s;\n    --gvn-badge-bg: %s;\n}",
            esc_attr( $primary_color ),
            esc_attr( $button_color ),
            esc_attr( $header_bg ),
            esc_attr( $badge_bg )
        );
        wp_add_inline_style( 'gvn-checkout-css', $custom_css );
    }

    /**
     * Renderiza o shortcode [gvn-checkout].
     */
    public function render_checkout( $atts ) {
        if ( ! function_exists( 'WC' ) ) {
            return '<p>' . esc_html__( 'WooCommerce não está disponível.', 'gvn-checkout' ) . '</p>';
        }

        global $wp;

        // Detecta endpoint order-received (página de confirmação do pedido).
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
            return $this->render_thankyou_page();
        }

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
            return '<div class="gvn-checkout-empty">
                <p>' . esc_html__( 'Seu carrinho está vazio.', 'gvn-checkout' ) . ' <a href="' . esc_url( $shop_url ) . '">' . esc_html__( 'Voltar à loja', 'gvn-checkout' ) . '</a></p>
            </div>';
        }

        if ( ! is_user_logged_in() && 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ) {
            $account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
            return '<p>' . esc_html__( 'Você precisa estar logado para finalizar a compra.', 'gvn-checkout' ) . ' <a href="' . esc_url( $account_url ) . '">' . esc_html__( 'Faça login', 'gvn-checkout' ) . '</a></p>';
        }

        $checkout = WC()->checkout();

        if ( ! $checkout ) {
            return '<p>' . esc_html__( 'Não foi possível inicializar o checkout. Tente novamente.', 'gvn-checkout' ) . '</p>';
        }

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/checkout-template.php';
        return ob_get_clean();
    }

    /**
     * Renderiza a página de confirmação de pedido (thank you).
     */
    private function render_thankyou_page() {
        global $wp, $order;

        $order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
        $order    = false;

        if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
        }

        // Validação de segurança com a key do pedido (timing-safe).
        $order_key = isset( $_GET['key'] ) ? ( function_exists( 'wc_clean' ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : '';
        if ( $order && method_exists( $order, 'get_order_key' ) ) {
            if ( empty( $order_key ) || ( function_exists( 'hash_equals' ) && ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) ) ) {
                $order = false;
            }
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
            wp_send_json_error( array( 'message' => __( 'Carrinho não disponível.', 'gvn-checkout' ) ) );
        }

        $coupon_code = isset( $_POST['coupon_code'] ) ? ( function_exists( 'wc_clean' ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) : '';

        if ( empty( $coupon_code ) ) {
            wp_send_json_error( array( 'message' => __( 'Informe um código de cupom.', 'gvn-checkout' ) ) );
        }

        $result = WC()->cart->apply_coupon( $coupon_code );

        // Em WC moderno apply_coupon pode retornar true, string (cart coupon msg) ou WP_Error.
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        if ( true === $result || ( is_string( $result ) && '' !== $result ) ) {
            wp_send_json_success( array(
                'message'  => __( 'Cupom aplicado com sucesso!', 'gvn-checkout' ),
                'total'    => WC()->cart->get_total(),
                'subtotal' => WC()->cart->get_subtotal(),
                'discount' => WC()->cart->get_discount_total(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Cupom inválido ou já aplicado.', 'gvn-checkout' ) ) );
        }
    }

    /**
     * AJAX: Remover cupom.
     */
    public function ajax_remove_coupon() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array( 'message' => __( 'Carrinho não disponível.', 'gvn-checkout' ) ) );
        }

        $coupon_code = isset( $_POST['coupon_code'] ) ? ( function_exists( 'wc_clean' ) ? wc_clean( wp_unslash( $_POST['coupon_code'] ) ) : sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) : '';

        if ( empty( $coupon_code ) ) {
            wp_send_json_error( array( 'message' => __( 'Informe o código do cupom.', 'gvn-checkout' ) ) );
        }

        WC()->cart->remove_coupon( $coupon_code );
        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'message'  => __( 'Cupom removido.', 'gvn-checkout' ),
            'total'    => WC()->cart->get_total(),
            'subtotal' => WC()->cart->get_subtotal(),
        ) );
    }

    /**
     * Substitui o template padrão de thankyou do WooCommerce pelo template customizado
     * exclusivamente para pedidos originados pelo GVN Checkout.
     */
    public function override_thankyou_template( $template, $template_name, $args, $template_path, $default_path ) {
        if ( 'checkout/thankyou.php' === $template_name ) {
            global $wp, $order;

            $order_id = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
            if ( $order_id <= 0 && isset( $args['order'] ) && is_object( $args['order'] ) && method_exists( $args['order'], 'get_id' ) ) {
                $order_id = $args['order']->get_id();
            }

            if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
                $order_obj = wc_get_order( $order_id );
                if ( $order_obj ) {
                    $order_key = isset( $_GET['key'] ) ? ( function_exists( 'wc_clean' ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : '';
                    if ( empty( $order_key ) ) {
                        return $template;
                    }
                    if ( method_exists( $order_obj, 'get_order_key' ) ) {
                        if ( function_exists( 'hash_equals' ) ) {
                            if ( ! hash_equals( (string) $order_obj->get_order_key(), (string) $order_key ) ) {
                                return $template;
                            }
                        } elseif ( (string) $order_obj->get_order_key() !== (string) $order_key ) {
                            return $template;
                        }
                    } else {
                        return $template;
                    }

                    // Verifica se o pedido foi originado pelo GVN Checkout
                    $gvn_version = method_exists( $order_obj, 'get_meta' ) ? $order_obj->get_meta( '_gvn_checkout_version' ) : '';
                    $is_gvn      = ! empty( $gvn_version ) || ( method_exists( $order_obj, 'get_meta' ) && 'yes' === $order_obj->get_meta( '_gvn_checkout' ) );

                    // Preserva o template padrão do WooCommerce para pedidos externos ao GVN
                    if ( ! $is_gvn ) {
                        return $template;
                    }

                    $order = $order_obj;
                    $custom_template = GVN_CHECKOUT_PLUGIN_DIR . 'templates/thankyou-template.php';
                    if ( file_exists( $custom_template ) ) {
                        return $custom_template;
                    }
                }
            }
        }
        return $template;
    }
}
