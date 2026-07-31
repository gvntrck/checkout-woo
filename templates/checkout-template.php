<?php
/**
 * Template do checkout personalizado GVN.
 *
 * @package GVN_Checkout
 * @version 1.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$header_text       = get_option( 'gvn_checkout_header_text', 'EFEAD - Conectando Saberes' );
$header_badge_text = get_option( 'gvn_checkout_header_badge_text', 'COMPRA SEGURA' );
$button_text       = get_option( 'gvn_checkout_button_text', 'Finalizar pedido' );
$bump_enabled      = 'yes' === get_option( 'gvn_checkout_order_bump_enabled', 'no' );
$bump_product_id   = absint( get_option( 'gvn_checkout_order_bump_product_id', 0 ) );
$bump_title        = get_option( 'gvn_checkout_order_bump_title', 'Oferta Exclusiva' );
$bump_description  = get_option( 'gvn_checkout_order_bump_description', '' );
$bump_cta_text     = get_option( 'gvn_checkout_order_bump_cta_text', 'Sim! Quero adicionar ao meu pedido' );
$bump_price        = get_option( 'gvn_checkout_order_bump_price', '' );
$bump_product      = $bump_enabled && $bump_product_id ? wc_get_product( $bump_product_id ) : null;

$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
$cart               = WC()->cart;

$bump_in_cart = false;
if ( $bump_product ) {
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['product_id'] ) && (int) $cart_item['product_id'] === (int) $bump_product_id && ! empty( $cart_item['gvn_order_bump'] ) ) {
            $bump_in_cart = true;
            break;
        }
    }
}
?>

<div class="gvn-checkout" id="gvn-checkout">

    <!-- Header -->
    <header class="gvn-header">
        <div class="gvn-header__text">
            <?php echo esc_html( $header_text ); ?>
        </div>
        <div class="gvn-header__badge">
            <svg class="gvn-header__badge-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
            </svg>
            <?php echo esc_html( $header_badge_text ); ?>
        </div>
    </header>

    <!-- Título -->
    <div class="gvn-title">
        <h1 class="gvn-title__heading">
            <span class="gvn-title__dot">&bull;</span> Finalize sua inscrição
        </h1>
        <p class="gvn-title__sub">Acesso imediato após confirmação do pagamento</p>
    </div>

    <!-- Form de Checkout do WooCommerce -->
    <form name="checkout" method="post" class="checkout woocommerce-checkout gvn-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

        <div class="gvn-grid">

            <!-- Coluna Esquerda: Dados do Usuário -->
            <section class="gvn-col-left">
                <div class="gvn-card">
                    <div class="gvn-card__header">SEUS DADOS</div>
                    <div class="gvn-card__body">
                        <?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

                        <?php $gvn_fields = GVN_Custom_Fields::get_enabled_fields(); ?>
                        <div class="gvn-fields-dynamic">
                            <?php foreach ( $gvn_fields as $gvn_field ) :
                                $f_key         = esc_attr( $gvn_field['key'] );
                                $f_label       = esc_html( $gvn_field['label'] );
                                $f_type        = $gvn_field['type'];
                                $f_required    = ! empty( $gvn_field['required'] );
                                $f_placeholder = esc_attr( $gvn_field['placeholder'] );
                                $f_width       = $gvn_field['width'];
                                $f_mask        = ! empty( $gvn_field['mask'] ) ? $gvn_field['mask'] : '';
                                $f_raw_value   = $checkout->get_value( $gvn_field['key'] );
                                $f_value       = esc_attr( $f_raw_value );
                                $width_class   = 'gvn-field--w' . $f_width;
                                $f_conditions  = isset( $gvn_field['conditions'] ) ? $gvn_field['conditions'] : array( 'logic' => 'and', 'rules' => array() );
                                $has_conditions = GVN_Custom_Fields::has_conditions( $gvn_field );
                                $f_orig_required = $f_required;
                            ?>
                                <div class="gvn-field <?php echo esc_attr( $width_class ); ?><?php echo $has_conditions ? ' gvn-field--conditional' : ''; ?>" data-field-key="<?php echo $f_key; ?>" data-mask="<?php echo esc_attr( $f_mask ); ?>"<?php if ( $has_conditions ) : ?> data-conditions="<?php echo esc_attr( wp_json_encode( $f_conditions ) ); ?>" data-required="<?php echo $f_orig_required ? '1' : '0'; ?>"<?php endif; ?>>
                                    <label class="gvn-field__label" for="<?php echo $f_key; ?>">
                                        <?php echo $f_label; ?>
                                        <?php if ( $f_required ) : ?><span class="gvn-field__required">*</span><?php endif; ?>
                                        <?php if ( ! $f_required && $f_key === 'order_comments' ) : ?><span class="gvn-field__optional">(opcional)</span><?php endif; ?>
                                    </label>
                                    <?php if ( 'textarea' === $f_type ) : ?>
                                        <textarea class="gvn-field__input gvn-field__textarea" name="<?php echo $f_key; ?>" id="<?php echo $f_key; ?>" rows="3" placeholder="<?php echo $f_placeholder; ?>" <?php echo $f_required ? 'required' : ''; ?>><?php echo esc_textarea( $f_raw_value ); ?></textarea>
                                    <?php elseif ( 'select' === $f_type ) :
                                        $f_options_raw  = isset( $gvn_field['options'] ) ? $gvn_field['options'] : '';
                                        $f_options      = GVN_Custom_Fields::parse_select_options( $f_options_raw );
                                        $f_default_opt  = isset( $gvn_field['default_option'] ) ? $gvn_field['default_option'] : '';
                                        $f_select_value = '' !== $f_raw_value ? $f_raw_value : $f_default_opt;
                                    ?>
                                        <select class="gvn-field__input gvn-field__select" name="<?php echo $f_key; ?>" id="<?php echo $f_key; ?>" <?php echo $f_required ? 'required' : ''; ?>>
                                            <option value=""><?php echo $f_placeholder ? esc_html( $f_placeholder ) : '-- Selecione --'; ?></option>
                                            <?php if ( empty( $f_options ) ) : ?>
                                                <option value="" disabled><?php echo esc_html__( 'Nenhuma opção configurada', 'gvn-checkout' ); ?></option>
                                            <?php else : ?>
                                                <?php foreach ( $f_options as $opt_value => $opt_label ) : ?>
                                                    <option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $f_select_value, $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    <?php else : ?>
                                        <input type="<?php echo esc_attr( $f_type ); ?>" class="gvn-field__input" name="<?php echo $f_key; ?>" id="<?php echo $f_key; ?>" value="<?php echo $f_value; ?>" placeholder="<?php echo $f_placeholder; ?>" <?php echo $f_required ? 'required' : ''; ?> />
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>

                        <?php
                        // Campos ocultos padrão do WooCommerce: só renderizar os que o admin NÃO inseriu como campo visual
                        $woo_hidden_defaults = array(
                            'billing_country'   => 'BR',
                            'billing_address_1' => '',
                            'billing_address_2' => '',
                            'billing_city'      => '',
                            'billing_state'     => '',
                            'billing_postcode'  => '',
                            'billing_company'   => '',
                        );

                        // Verificar campos habilitados
                        foreach ( $gvn_fields as $f ) {
                            if ( ! empty( $f['enabled'] ) && isset( $woo_hidden_defaults[ $f['key'] ] ) ) {
                                unset( $woo_hidden_defaults[ $f['key'] ] );
                            }
                        }

                        // Renderiza os que sobraram como hidden
                        foreach ( $woo_hidden_defaults as $wk => $wval ) {
                            echo '<input type="hidden" name="' . esc_attr( $wk ) . '" value="' . esc_attr( $wval ) . '" />';
                        }
                        ?>
                    </div>
                </div>
            </section>

            <!-- Coluna Direita: Resumo do Pedido -->
            <aside class="gvn-col-right">
                <div class="gvn-card">
                    <div class="gvn-card__header">RESUMO DO PEDIDO</div>
                    <div class="gvn-card__body">
                        <div class="gvn-order-labels">
                            <span>Produto</span>
                            <span>Subtotal</span>
                        </div>

                        <div class="gvn-order-items" id="gvn-order-items">
                            <?php foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) :
                                $product  = $cart_item['data'];
                                $quantity = $cart_item['quantity'];
                                $subtotal = $cart->get_product_subtotal( $product, $quantity );
                            ?>
                                <div class="gvn-order-item" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
                                    <div class="gvn-order-item__name">
                                        <?php echo esc_html( $product->get_name() ); ?>
                                        <div class="gvn-order-item__qty">&times; <?php echo esc_html( $quantity ); ?></div>
                                    </div>
                                    <div class="gvn-order-item__subtotal"><?php echo $subtotal; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="gvn-order-totals">
                            <div class="gvn-order-totals__row">
                                <span class="gvn-order-totals__label">Subtotal</span>
                                <span class="gvn-order-totals__value" id="gvn-subtotal"><?php echo wc_price( $cart->get_subtotal() ); ?></span>
                            </div>

                            <?php if ( $cart->get_discount_total() > 0 ) : ?>
                                <div class="gvn-order-totals__row gvn-order-totals__row--discount" id="gvn-discount-row">
                                    <span class="gvn-order-totals__label">Desconto</span>
                                    <span class="gvn-order-totals__value" id="gvn-discount">-<?php echo wc_price( $cart->get_discount_total() ); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="gvn-order-totals__total">
                                <span class="gvn-order-totals__total-label">Total</span>
                                <span class="gvn-order-totals__total-value" id="gvn-total"><?php echo $cart->get_total(); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Seção de Pagamento (full width) -->
            <section class="gvn-col-full">
                <div class="gvn-card">
                    <div class="gvn-card__header">FORMA DE PAGAMENTO</div>
                    <div class="gvn-card__body">

                        <!-- Cupom -->
                        <div class="gvn-coupon" id="gvn-coupon-toggle">
                            <div class="gvn-coupon__trigger">
                                <div class="gvn-coupon__trigger-inner">
                                    <span class="gvn-coupon__icon">🏷️</span>
                                    <span class="gvn-coupon__text">Tem um cupom de desconto?</span>
                                </div>
                                <span class="gvn-coupon__arrow" id="gvn-coupon-arrow">▼</span>
                            </div>
                        </div>
                        <div class="gvn-coupon__form" id="gvn-coupon-form" style="display: none;">
                            <div class="gvn-coupon__form-inner">
                                <input type="text" class="gvn-field__input" id="gvn-coupon-code" placeholder="Digite o código do cupom" />
                                <button type="button" class="gvn-coupon__btn" id="gvn-apply-coupon">Aplicar</button>
                            </div>
                            <div class="gvn-coupon__message" id="gvn-coupon-message"></div>

                            <?php foreach ( $cart->get_applied_coupons() as $coupon_code ) : ?>
                                <div class="gvn-coupon__applied">
                                    <span>Cupom: <strong><?php echo esc_html( $coupon_code ); ?></strong></span>
                                    <button type="button" class="gvn-coupon__remove" data-coupon="<?php echo esc_attr( $coupon_code ); ?>">Remover</button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Gateways de Pagamento -->
                        <div class="gvn-gateways" id="payment">
                            <?php if ( ! empty( $available_gateways ) ) : ?>
                                <div class="gvn-gateways__list">
                                    <?php
                                    $gateway_icons = array(
                                        'pix'    => '<svg class="gvn-gateway__icon-svg" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4.5 9.5 12 17l7.5-7.5L12 2zm0 13l-5.5-5.5L12 4l5.5 5.5L12 15z"/></svg>',
                                        'bacs'   => '<svg class="gvn-gateway__icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>',
                                        'boleto' => '<svg class="gvn-gateway__icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>',
                                        'card'   => '<svg class="gvn-gateway__icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>',
                                    );

                                    $first = true;
                                    foreach ( $available_gateways as $gateway_id => $gateway ) :
                                        $icon_key   = 'card';
                                        $extra_info = '';
                                        $is_recommended = false;

                                        if ( stripos( $gateway_id, 'pix' ) !== false ) {
                                            $icon_key       = 'pix';
                                            $extra_info     = 'Confirmação imediata';
                                            $is_recommended = true;
                                        } elseif ( stripos( $gateway_id, 'boleto' ) !== false || stripos( $gateway_id, 'bacs' ) !== false || stripos( $gateway_id, 'ticket' ) !== false ) {
                                            $icon_key   = 'boleto';
                                            $extra_info = 'Compensação bancária';
                                        } elseif ( stripos( $gateway_id, 'card' ) !== false || stripos( $gateway_id, 'credit' ) !== false || stripos( $gateway_id, 'cc' ) !== false || stripos( $gateway_id, 'stripe' ) !== false ) {
                                            $icon_key   = 'card';
                                            $extra_info = 'Parcelamento disponível';
                                        }

                                        $icon_svg = isset( $gateway_icons[ $icon_key ] ) ? $gateway_icons[ $icon_key ] : $gateway_icons['card'];
                                    ?>
                                        <div class="gvn-gateway <?php echo $first ? 'gvn-gateway--active' : ''; ?>" data-gateway="<?php echo esc_attr( $gateway_id ); ?>">
                                            <input type="radio" name="payment_method" id="payment_method_<?php echo esc_attr( $gateway_id ); ?>" value="<?php echo esc_attr( $gateway_id ); ?>" <?php checked( $first, true ); ?> class="gvn-gateway__radio" />
                                            <div class="gvn-gateway__icon <?php echo $is_recommended ? 'gvn-gateway__icon--recommended' : 'gvn-gateway__icon--default'; ?>">
                                                <?php echo $icon_svg; ?>
                                            </div>
                                            <div class="gvn-gateway__info">
                                                <div class="gvn-gateway__name-row">
                                                    <span class="gvn-gateway__name"><?php echo esc_html( $gateway->get_title() ); ?></span>
                                                    <?php if ( $is_recommended ) : ?>
                                                        <span class="gvn-gateway__badge">Recomendado</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ( $extra_info ) : ?>
                                                    <p class="gvn-gateway__desc"><?php echo esc_html( $extra_info ); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php
                                        $first = false;
                                    endforeach;
                                    ?>
                                </div>

                                <!-- Conteúdo dos gateways (campos extras como cartão de crédito) -->
                                <div class="gvn-gateways__content" id="gvn-gateway-content">
                                    <?php foreach ( $available_gateways as $gateway_id => $gateway ) : ?>
                                        <div class="gvn-gateway-fields" id="gvn-gateway-fields-<?php echo esc_attr( $gateway_id ); ?>" style="display: none;">
                                            <?php
                                            if ( $gateway->has_fields() || $gateway->get_description() ) {
                                                $gateway->payment_fields();
                                            }
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <p class="gvn-gateways__empty">Nenhum método de pagamento disponível. Configure os métodos de pagamento nas configurações do WooCommerce.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Nota de Privacidade -->
                        <p class="gvn-privacy">
                            Os seus dados pessoais serão utilizados para processar a sua compra, apoiar a sua experiência em todo este site e para outros fins descritos na nossa
                            <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" class="gvn-privacy__link">política de privacidade</a>.
                        </p>

                        <!-- Order Bump -->
                        <?php if ( $bump_product ) :
                            $bump_price_raw = $bump_price;
                            if ( '' !== $bump_price_raw && floatval( $bump_price_raw ) > 0 ) {
                                $bump_display_price = wc_price( floatval( $bump_price_raw ) );
                            } else {
                                $bump_display_price = wc_price( $bump_product->get_price() );
                            }
                        ?>
                            <div class="gvn-order-bump" id="gvn-order-bump">
                                <div class="gvn-order-bump__inner">
                                    <div class="gvn-order-bump__checkbox">
                                        <input type="checkbox" class="gvn-order-bump__input" id="gvn-bump-checkbox" <?php checked( $bump_in_cart ); ?> />
                                    </div>
                                    <div class="gvn-order-bump__content">
                                        <div class="gvn-order-bump__badge">
                                            ❗<?php echo esc_html( strtoupper( $bump_title ) ); ?>
                                        </div>
                                        <h3 class="gvn-order-bump__product">
                                            <?php echo esc_html( $bump_product->get_name() ); ?><br />
                                            <span class="gvn-order-bump__price">por apenas <?php echo $bump_display_price; ?></span>
                                        </h3>
                                        <?php if ( $bump_description ) : ?>
                                            <p class="gvn-order-bump__desc"><?php echo esc_html( $bump_description ); ?></p>
                                        <?php endif; ?>
                                        <label class="gvn-order-bump__cta" for="gvn-bump-checkbox">
                                            <?php echo esc_html( $bump_cta_text ); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Botão Finalizar -->
                        <div class="gvn-submit">
                            <?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
                            <button type="submit" class="gvn-submit__btn" name="woocommerce_checkout_place_order" id="place_order" value="<?php echo esc_attr( $button_text ); ?>">
                                <?php echo esc_html( $button_text ); ?>
                            </button>
                        </div>

                    </div>
                </div>
            </section>

        </div><!-- .gvn-grid -->

    </form>

</div><!-- .gvn-checkout -->
