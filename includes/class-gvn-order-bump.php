<?php
/**
 * Classe de Order Bump do GVN Checkout.
 * Gerencia a adição/remoção do produto de order bump no carrinho via AJAX com validação server-side e idempotência.
 *
 * @package GVN_Checkout
 * @version 1.13.29
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use GVN\Checkout\Settings\SettingsRepository;

class GVN_Order_Bump {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_gvn_toggle_order_bump', array( $this, 'toggle_order_bump' ) );
        add_action( 'wp_ajax_nopriv_gvn_toggle_order_bump', array( $this, 'toggle_order_bump' ) );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_bump_price' ), 20, 1 );
    }

    /**
     * Obtém configuração do repositório central ou fallback seguro.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    private function get_setting( $key, $default = '' ) {
        if ( class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ) {
            return SettingsRepository::get( $key );
        }
        return get_option( 'gvn_checkout_' . $key, $default );
    }

    /**
     * AJAX: Adicionar ou remover o produto do order bump de forma idempotente.
     */
    public function toggle_order_bump() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array( 'message' => __( 'Carrinho não disponível.', 'gvn-checkout' ) ) );
            return;
        }

        $enabled = $this->get_setting( 'order_bump_enabled', 'no' );
        if ( 'yes' !== $enabled ) {
            wp_send_json_error( array( 'message' => __( 'Order bump não está habilitado.', 'gvn-checkout' ) ) );
            return;
        }

        $product_id = absint( $this->get_setting( 'order_bump_product_id', 0 ) );
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Produto do order bump não configurado.', 'gvn-checkout' ) ) );
            return;
        }

        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Produto não encontrado.', 'gvn-checkout' ) ) );
            return;
        }

        if ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() ) {
            wp_send_json_error( array( 'message' => __( 'Este produto não está disponível para compra.', 'gvn-checkout' ) ) );
            return;
        }

        if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
            wp_send_json_error( array( 'message' => __( 'Produto esgotado.', 'gvn-checkout' ) ) );
            return;
        }

        $action = isset( $_POST['bump_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bump_action'] ) ) : '';

        if ( 'add' === $action ) {
            $bump_keys = $this->find_all_bump_keys_in_cart( $product_id );

            if ( ! empty( $bump_keys ) ) {
                // Se já estiver no carrinho, assegura que não haja duplicatas
                if ( count( $bump_keys ) > 1 ) {
                    $keep_first = array_shift( $bump_keys );
                    foreach ( $bump_keys as $extra_key ) {
                        WC()->cart->remove_cart_item( $extra_key );
                    }
                    WC()->cart->calculate_totals();
                }
                wp_send_json_success( $this->get_cart_data( __( 'Oferta já está no pedido!', 'gvn-checkout' ) ) );
                return;
            }

            $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'gvn_order_bump' => true ) );

            if ( $cart_item_key ) {
                WC()->cart->calculate_totals();
                wp_send_json_success( $this->get_cart_data( __( 'Oferta adicionada ao pedido!', 'gvn-checkout' ) ) );
            } else {
                wp_send_json_error( array( 'message' => __( 'Não foi possível adicionar o produto.', 'gvn-checkout' ) ) );
            }
            return;
        } elseif ( 'remove' === $action ) {
            $bump_keys = $this->find_all_bump_keys_in_cart( $product_id );

            if ( ! empty( $bump_keys ) ) {
                foreach ( $bump_keys as $key ) {
                    WC()->cart->remove_cart_item( $key );
                }
                WC()->cart->calculate_totals();
            }

            wp_send_json_success( $this->get_cart_data( __( 'Oferta removida do pedido.', 'gvn-checkout' ) ) );
            return;
        }

        wp_send_json_error( array( 'message' => __( 'Ação inválida.', 'gvn-checkout' ) ) );
    }

    /**
     * Localiza todas as chaves do item de order bump no carrinho.
     *
     * @param int $product_id
     * @return array<int, string>
     */
    private function find_all_bump_keys_in_cart( $product_id ) {
        $keys = array();
        if ( ! WC()->cart ) {
            return $keys;
        }
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['product_id'] ) && (int) $cart_item['product_id'] === (int) $product_id && ! empty( $cart_item['gvn_order_bump'] ) ) {
                $keys[] = $cart_item_key;
            }
        }
        return $keys;
    }

    /**
     * Aplica o preço promocional do order bump no cálculo de totais do WooCommerce.
     *
     * @param WC_Cart $cart
     */
    public function apply_bump_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $enabled = $this->get_setting( 'order_bump_enabled', 'no' );
        if ( 'yes' !== $enabled ) {
            return;
        }

        $bump_price_raw = (string) $this->get_setting( 'order_bump_price', '' );
        if ( '' === trim( $bump_price_raw ) ) {
            return;
        }

        $bump_price   = floatval( str_replace( ',', '.', $bump_price_raw ) );
        $bump_product = absint( $this->get_setting( 'order_bump_product_id', 0 ) );

        // Preço inválido (negativo ou zero) ou produto não configurado — ignora override
        if ( $bump_price <= 0 || ! $bump_product ) {
            return;
        }

        if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! empty( $cart_item['gvn_order_bump'] )
                && isset( $cart_item['product_id'] )
                && (int) $cart_item['product_id'] === $bump_product
                && isset( $cart_item['data'] )
                && is_object( $cart_item['data'] )
                && method_exists( $cart_item['data'], 'set_price' ) ) {
                $cart_item['data']->set_price( $bump_price );
            }
        }
    }

    /**
     * Retorna dados atualizados do carrinho para resposta AJAX.
     *
     * @param string $message
     * @return array
     */
    private function get_cart_data( $message = '' ) {
        return array(
            'message'  => $message,
            'total'    => function_exists( 'wc_price' ) ? wc_price( WC()->cart->get_total( 'edit' ) ) : WC()->cart->get_total( 'edit' ),
            'subtotal' => function_exists( 'wc_price' ) ? wc_price( WC()->cart->get_subtotal() ) : WC()->cart->get_subtotal(),
            'discount' => function_exists( 'wc_price' ) ? wc_price( WC()->cart->get_discount_total() ) : WC()->cart->get_discount_total(),
            'items'    => $this->get_cart_items_html(),
        );
    }

    /**
     * Gera o HTML dos itens do carrinho para atualização via AJAX.
     *
     * @return string
     */
    private function get_cart_items_html() {
        if ( ! WC()->cart ) {
            return '';
        }
        $html = '';
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product  = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            $subtotal = WC()->cart->get_product_subtotal( $product, $quantity );

            $product_name = method_exists( $product, 'get_name' ) ? $product->get_name() : ( $cart_item['name'] ?? '' );

            $html .= '<div class="gvn-order-item" data-key="' . esc_attr( $cart_item_key ) . '">';
            $html .= '<div class="gvn-order-item__name">';
            $html .= esc_html( $product_name );
            $html .= '<div class="gvn-order-item__qty">&times; ' . esc_html( $quantity ) . '</div>';
            $html .= '</div>';
            $html .= '<div class="gvn-order-item__subtotal">' . $subtotal . '</div>';
            $html .= '</div>';
        }
        return $html;
    }
}
