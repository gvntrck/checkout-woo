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
        add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'refresh_checkout_fragments' ) );
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

        if ( $is_checkout_page ) {
            $classes[] = 'gvn-layout-' . self::get_current_layout( array(), 'body' );
        }

        return $classes;
    }

    /**
     * Resolve o layout de checkout vigente.
     *
     * Prioridade: atributo `layout` do shortcode > atributo no conteúdo da
     * página > configuração do plugin > layout padrão. O resultado passa
     * pelo filtro `gvn_checkout_layout` e sempre cai para um slug válido.
     *
     * @param array|string $atts Atributos do shortcode (quando em renderização).
     * @param string       $context Contexto: 'render', 'enqueue' ou 'body'.
     * @return string Slug do layout.
     */
    public static function get_current_layout( $atts = array(), $context = 'render' ) {
        $requested = '';

        if ( is_array( $atts ) && isset( $atts['layout'] ) ) {
            $requested = trim( (string) $atts['layout'] );
        }

        if ( '' === $requested ) {
            $requested = self::detect_page_layout();
        }

        if ( '' === $requested ) {
            $requested = class_exists( 'GVN\Checkout\Settings\SettingsRepository' )
                ? \GVN\Checkout\Settings\SettingsRepository::get( 'checkout_layout', 'classic' )
                : get_option( 'gvn_checkout_checkout_layout', 'classic' );
        }

        $layout = class_exists( 'GVN\Checkout\Layouts\LayoutRegistry' )
            ? \GVN\Checkout\Layouts\LayoutRegistry::resolve( $requested )
            : 'classic';

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'gvn_checkout_layout', $layout, $context );
            if ( is_string( $filtered ) && '' !== $filtered && class_exists( 'GVN\Checkout\Layouts\LayoutRegistry' ) ) {
                $layout = \GVN\Checkout\Layouts\LayoutRegistry::resolve( $filtered );
            }
        }

        return $layout;
    }

    /**
     * Detecta o atributo `layout` do shortcode [gvn-checkout] no conteúdo da página.
     * Permite enfileirar o CSS/JS correto antes da renderização do shortcode.
     *
     * @return string Slug bruto encontrado ou string vazia.
     */
    private static function detect_page_layout() {
        global $post;

        if ( ! is_object( $post ) || ! isset( $post->post_content ) ) {
            return '';
        }

        $content = (string) $post->post_content;

        if ( false === strpos( $content, 'gvn-checkout' ) ) {
            return '';
        }

        if ( preg_match( '/\[gvn-checkout[^\]]*layout\s*=\s*["\']([^"\']+)["\']/', $content, $matches ) ) {
            return trim( $matches[1] );
        }

        return '';
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

        // CSS/JS exclusivos do layout vigente (o clássico usa apenas a base).
        if ( class_exists( 'GVN\Checkout\Layouts\LayoutRegistry' ) ) {
            $layout  = self::get_current_layout( array(), 'enqueue' );
            $css_url = \GVN\Checkout\Layouts\LayoutRegistry::get_css_url( $layout );
            if ( '' !== $css_url ) {
                wp_enqueue_style(
                    'gvn-checkout-layout-' . $layout,
                    $css_url,
                    array( 'gvn-checkout-css' ),
                    GVN_CHECKOUT_VERSION
                );
            }
            $js_url = \GVN\Checkout\Layouts\LayoutRegistry::get_js_url( $layout );
            if ( '' !== $js_url ) {
                wp_enqueue_script(
                    'gvn-checkout-layout-' . $layout,
                    $js_url,
                    array( 'gvn-checkout-js' ),
                    GVN_CHECKOUT_VERSION,
                    true
                );
            }
        }

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
        $get_setting = static function ( $key, $default ) {
            return class_exists( 'GVN\Checkout\Settings\SettingsRepository' )
                ? \GVN\Checkout\Settings\SettingsRepository::get( $key, $default )
                : get_option( 'gvn_checkout_' . $key, $default );
        };
        $primary_color = $get_setting( 'primary_color', '#0066d4' );
        $button_color  = $get_setting( 'button_color', '#ff8a22' );
        $header_bg     = $get_setting( 'header_bg_color', '#3a4759' );
        $badge_bg      = $get_setting( 'badge_bg_color', '#ff8a22' );
        $preset        = $get_setting( 'typography_preset', 'normal' );

        $preset_scales = array( 'compact' => 0.875, 'normal' => 1, 'large' => 1.125 );
        $scale         = isset( $preset_scales[ $preset ] ) ? $preset_scales[ $preset ] : 1;
        $font_sizes    = array();
        foreach ( array( 'body', 'label', 'section_title', 'page_title', 'price' ) as $category ) {
            $size = $get_setting( 'font_size_' . $category, '' );
            if ( '' !== $size && is_numeric( $size ) && (float) $size >= 10 && (float) $size <= 48 ) {
                $font_sizes[ $category ] = (float) $size;
            }
        }

        $primary_color = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $primary_color ) ) ? sanitize_hex_color( (string) $primary_color ) : '#0066d4';
        $button_color  = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $button_color ) ) ? sanitize_hex_color( (string) $button_color ) : '#ff8a22';
        $header_bg     = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $header_bg ) ) ? sanitize_hex_color( (string) $header_bg ) : '#3a4759';
        $badge_bg      = ( function_exists( 'sanitize_hex_color' ) && sanitize_hex_color( (string) $badge_bg ) ) ? sanitize_hex_color( (string) $badge_bg ) : '#ff8a22';

        $custom_css = sprintf(
            ":root {\n    --gvn-primary: %s;\n    --gvn-button: %s;\n    --gvn-header-bg: %s;\n    --gvn-badge-bg: %s;\n}\n.gvn-checkout { --gvn-font-scale: %s; font-size: %spx !important; }\n.gvn-checkout :is(input, select, textarea, button) { font-size: %spx !important; }\n.gvn-checkout :is(label, .gvn-field__label, .gvn-terms-label, .gvn-order-totals__label, .gvn-split__plan-label, .gvn-split__section-label) { font-size: %spx !important; }\n.gvn-checkout :is(.gvn-title__heading, .gvn-title__dot, .gvn-split__plan-price) { font-size: %spx !important; }\n.gvn-checkout :is(.gvn-section__title, .gvn-payment-title, .gvn-order-totals__total-label) { font-size: %spx !important; }\n.gvn-checkout :is(.gvn-order-totals__total, .gvn-order-item__subtotal, .amount, .gvn-split__plan-price) { font-size: %spx !important; }",
            esc_attr( $primary_color ),
            esc_attr( $button_color ),
            esc_attr( $header_bg ),
            esc_attr( $badge_bg ),
            esc_attr( (string) $scale ),
            esc_attr( (string) ( $font_sizes['body'] ?? 16 * $scale ) ),
            esc_attr( (string) ( $font_sizes['body'] ?? 16 * $scale ) ),
            esc_attr( (string) ( $font_sizes['label'] ?? 12 * $scale ) ),
            esc_attr( (string) ( $font_sizes['page_title'] ?? 32 * $scale ) ),
            esc_attr( (string) ( $font_sizes['section_title'] ?? 18 * $scale ) ),
            esc_attr( (string) ( $font_sizes['price'] ?? 20 * $scale ) )
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

        $layout      = self::get_current_layout( $atts, 'render' );
        $gvn_layout  = $layout;
        $template    = class_exists( 'GVN\Checkout\Layouts\LayoutRegistry' )
            ? \GVN\Checkout\Layouts\LayoutRegistry::get_template( $layout )
            : GVN_CHECKOUT_PLUGIN_DIR . 'templates/checkout-template.php';

        ob_start();
        include $template;
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

        if ( ! WC()->cart->remove_coupon( $coupon_code ) ) {
            return wp_send_json_error( array( 'message' => __( 'Cupom não encontrado no carrinho.', 'gvn-checkout' ) ) );
        }

        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'message'  => __( 'Cupom removido.', 'gvn-checkout' ),
            'total'    => WC()->cart->get_total(),
            'subtotal' => WC()->cart->get_subtotal(),
        ) );
    }

    /**
     * Atualiza os componentes próprios do checkout depois que o WooCommerce
     * recalcula frete, impostos, cupons ou gateways disponíveis.
     *
     * @param array $fragments Fragments retornados pelo endpoint update_order_review.
     * @return array
     */
    public function refresh_checkout_fragments( $fragments ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return $fragments;
        }

        $fragments['#gvn-order-items']  = $this->render_order_items_fragment();
        $fragments['#gvn-order-totals'] = $this->render_order_totals_fragment();

        // Exclusivos do layout split / cupom: só existem no DOM quando o layout
        // correspondente está ativo; replaceWith em seletor ausente é no-op.
        $fragments['#gvn-split-headline-total'] = $this->render_split_headline_fragment();
        $fragments['#gvn-coupon-applied-list']  = $this->render_applied_coupons_fragment();

        return $fragments;
    }

    /**
     * Gera o fragmento de itens do resumo do pedido.
     *
     * @return string
     */
    private function render_order_items_fragment() {
        $cart = WC()->cart;

        ob_start();
        ?>
        <div class="gvn-order-items" id="gvn-order-items">
            <?php foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) : ?>
                <?php
                $product  = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
                $quantity = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 0;
                if ( ! is_object( $product ) ) {
                    continue;
                }
                $subtotal = $cart->get_product_subtotal( $product, $quantity );
                ?>
                <div class="gvn-order-item" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
                    <div class="gvn-order-item__name">
                        <?php echo esc_html( $product->get_name() ); ?>
                        <div class="gvn-order-item__qty">&times; <?php echo esc_html( $quantity ); ?></div>
                    </div>
                    <div class="gvn-order-item__subtotal"><?php echo wp_kses_post( $subtotal ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Gera o fragmento de totais do resumo do pedido.
     *
     * @return string
     */
    private function render_order_totals_fragment() {
        $cart = WC()->cart;

        ob_start();
        ?>
        <div class="gvn-order-totals" id="gvn-order-totals">
            <div class="gvn-order-totals__row cart-subtotal">
                <span class="gvn-order-totals__label"><?php esc_html_e( 'Subtotal', 'gvn-checkout' ); ?></span>
                <span class="gvn-order-totals__value" id="gvn-subtotal"><?php echo wp_kses_post( wc_price( $cart->get_subtotal() ) ); ?></span>
            </div>

            <?php if ( $cart->get_discount_total() > 0 ) : ?>
                <div class="gvn-order-totals__row gvn-order-totals__row--discount cart-discount" id="gvn-discount-row">
                    <span class="gvn-order-totals__label"><?php esc_html_e( 'Desconto', 'gvn-checkout' ); ?></span>
                    <span class="gvn-order-totals__value" id="gvn-discount">-<?php echo wp_kses_post( wc_price( $cart->get_discount_total() ) ); ?></span>
                </div>
            <?php endif; ?>

            <div class="gvn-order-totals__total order-total">
                <span class="gvn-order-totals__total-label"><?php esc_html_e( 'Total', 'gvn-checkout' ); ?></span>
                <span class="gvn-order-totals__total-value" id="gvn-total"><?php echo wp_kses_post( $cart->get_total() ); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Fragmento do preço de destaque do layout split (mantém o total sincronizado
     * com cupons e order bump).
     *
     * @return string
     */
    private function render_split_headline_fragment() {
        $cart = WC()->cart;
        return '<p class="gvn-split__plan-price" id="gvn-split-headline-total">' . ( $cart ? $cart->get_total() : '' ) . '</p>';
    }

    /**
     * Fragmento da lista de cupons aplicados (chips com botão de remoção).
     *
     * @return string
     */
    private function render_applied_coupons_fragment() {
        $cart = WC()->cart;

        ob_start();
        ?>
        <div id="gvn-coupon-applied-list">
            <?php if ( $cart ) : ?>
                <?php foreach ( $cart->get_applied_coupons() as $coupon_code ) : ?>
                    <div class="gvn-coupon__applied">
                        <span><?php esc_html_e( 'Cupom:', 'gvn-checkout' ); ?> <strong><?php echo esc_html( $coupon_code ); ?></strong></span>
                        <button type="button" class="gvn-coupon__remove" data-coupon="<?php echo esc_attr( $coupon_code ); ?>"><?php esc_html_e( 'Remover', 'gvn-checkout' ); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Gera novamente os gateways após uma atualização de endereço ou total.
     *
     * @return string
     */
    private function render_payment_methods_fragment() {
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/payment-methods.php';
        return ob_get_clean();
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
