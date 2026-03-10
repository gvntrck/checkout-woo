<?php
/**
 * Classe de Order Bump do GVN Checkout.
 * Gerencia a adição/remoção do produto de order bump no carrinho via AJAX.
 *
 * @package GVN_Checkout
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
     * AJAX: Adicionar ou remover o produto do order bump.
     */
    public function toggle_order_bump() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        $action     = isset( $_POST['bump_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bump_action'] ) ) : '';
        $product_id = absint( get_option( 'gvn_checkout_order_bump_product_id', 0 ) );

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Produto do order bump não configurado.' ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Produto não encontrado.' ) );
        }

        if ( 'add' === $action ) {
            $cart_item_key = $this->find_bump_in_cart( $product_id );

            if ( $cart_item_key ) {
                wp_send_json_success( array( 'message' => 'Produto já está no carrinho.' ) );
                return;
            }

            $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'gvn_order_bump' => true ) );

            if ( $cart_item_key ) {
                WC()->cart->calculate_totals();
                wp_send_json_success( $this->get_cart_data( 'Oferta adicionada ao pedido!' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Não foi possível adicionar o produto.' ) );
            }
        } elseif ( 'remove' === $action ) {
            $cart_item_key = $this->find_bump_in_cart( $product_id );

            if ( $cart_item_key ) {
                WC()->cart->remove_cart_item( $cart_item_key );
                WC()->cart->calculate_totals();
                wp_send_json_success( $this->get_cart_data( 'Oferta removida do pedido.' ) );
            } else {
                wp_send_json_success( array( 'message' => 'Produto não estava no carrinho.' ) );
            }
        }

        wp_send_json_error( array( 'message' => 'Ação inválida.' ) );
    }

    /**
     * Busca o item do order bump no carrinho.
     */
    private function find_bump_in_cart( $product_id ) {
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( $cart_item['product_id'] == $product_id && ! empty( $cart_item['gvn_order_bump'] ) ) {
                return $cart_item_key;
            }
        }
        return false;
    }

    /**
     * Aplica o preço promocional do order bump.
     */
    public function apply_bump_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $bump_price = get_option( 'gvn_checkout_order_bump_price', '' );

        if ( '' === $bump_price ) {
            return;
        }

        $bump_price = floatval( $bump_price );

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! empty( $cart_item['gvn_order_bump'] ) ) {
                $cart_item['data']->set_price( $bump_price );
            }
        }
    }

    /**
     * Retorna dados atualizados do carrinho.
     */
    private function get_cart_data( $message = '' ) {
        return array(
            'message'  => $message,
            'total'    => WC()->cart->get_total(),
            'subtotal' => wc_price( WC()->cart->get_subtotal() ),
            'discount' => wc_price( WC()->cart->get_discount_total() ),
            'items'    => $this->get_cart_items_html(),
        );
    }

    /**
     * Gera o HTML dos itens do carrinho para atualização via AJAX.
     */
    private function get_cart_items_html() {
        $html = '';
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product  = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            $subtotal = WC()->cart->get_product_subtotal( $product, $quantity );

            $html .= '<div class="gvn-order-item" data-key="' . esc_attr( $cart_item_key ) . '">';
            $html .= '<div class="gvn-order-item__name">';
            $html .= esc_html( $product->get_name() );
            $html .= '<div class="gvn-order-item__qty">&times; ' . esc_html( $quantity ) . '</div>';
            $html .= '</div>';
            $html .= '<div class="gvn-order-item__subtotal">' . $subtotal . '</div>';
            $html .= '</div>';
        }
        return $html;
    }
}
