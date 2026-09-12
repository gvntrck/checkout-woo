<?php
/**
 * Layout "Dividido" do checkout GVN (resumo lateral escuro + formulário claro).
 *
 * Segundo modelo visual selecionável via Configurações > GVN Checkout > Layout
 * ou pelo shortcode [gvn-checkout layout="split"].
 *
 * CONTRATO (igual ao layout clássico — não remover):
 *  - form[name=checkout].woocommerce-checkout com action do WC e nonce;
 *  - todos os hooks canônicos do WooCommerce na mesma ordem do clássico;
 *  - radio name="payment_method" + $gateway->payment_fields();
 *  - IDs usados pelo JS/fragments: #gvn-order-items, #gvn-order-totals,
 *    #gvn-subtotal, #gvn-total, #payment, #place_order, #gvn-coupon-*,
 *    #gvn-bump-checkbox, #customer_details.
 *
 * A ordem visual (resumo à esquerda) é feita via CSS grid; a ordem do DOM
 * segue a do layout clássico para preservar a sequência dos hooks.
 *
 * @package GVN_Checkout
 * @since 1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$checkout = isset( $checkout ) ? $checkout : ( ( function_exists( 'WC' ) && WC()->checkout() ) ? WC()->checkout() : null );

$header_text       = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'header_text', 'EFEAD - Conectando Saberes' ) : get_option( 'gvn_checkout_header_text', 'EFEAD - Conectando Saberes' );
$header_badge_text = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'header_badge_text', 'COMPRA SEGURA' ) : get_option( 'gvn_checkout_header_badge_text', 'COMPRA SEGURA' );
$title_text        = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'title_text', '' ) : get_option( 'gvn_checkout_title_text', '' );
$subtitle_text     = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'subtitle_text', '' ) : get_option( 'gvn_checkout_subtitle_text', '' );
$button_text       = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'button_text', 'Finalizar pedido' ) : get_option( 'gvn_checkout_button_text', 'Finalizar pedido' );
$coupon_enabled    = 'yes' === ( class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'coupon_enabled', 'yes' ) : get_option( 'gvn_checkout_coupon_enabled', 'yes' ) );
$bump_enabled      = 'yes' === ( class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'order_bump_enabled', 'no' ) : get_option( 'gvn_checkout_order_bump_enabled', 'no' ) );
$bump_product_id   = absint( class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'order_bump_product_id', 0 ) : get_option( 'gvn_checkout_order_bump_product_id', 0 ) );
$bump_title        = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'order_bump_title', 'Oferta Exclusiva' ) : get_option( 'gvn_checkout_order_bump_title', 'Oferta Exclusiva' );
$bump_description  = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'order_bump_description', '' ) : get_option( 'gvn_checkout_order_bump_description', '' );
$bump_cta_text     = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'order_bump_cta_text', 'Sim! Quero adicionar ao meu pedido' ) : get_option( 'gvn_checkout_order_bump_cta_text', 'Sim! Quero adicionar ao meu pedido' );
$bump_price        = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'order_bump_price', '' ) : get_option( 'gvn_checkout_order_bump_price', '' );
$bump_product      = $bump_enabled && $bump_product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $bump_product_id ) : null;

$available_gateways = ( function_exists( 'WC' ) && WC()->payment_gateways() ) ? WC()->payment_gateways()->get_available_payment_gateways() : array();
$cart               = ( function_exists( 'WC' ) ) ? WC()->cart : null;
$shop_url           = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

if ( class_exists( 'GVN\Checkout\Checkout\TextPlaceholderResolver' ) ) {
    $header_text       = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $header_text, $cart );
    $header_badge_text = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $header_badge_text, $cart );
    $title_text        = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $title_text, $cart );
    $subtitle_text     = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $subtitle_text, $cart );
    $button_text       = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $button_text, $cart );
    $bump_title        = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $bump_title, $cart );
    $bump_description  = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $bump_description, $cart );
    $bump_cta_text     = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $bump_cta_text, $cart );
}

$bump_in_cart = false;
if ( $bump_product && $cart ) {
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['product_id'] ) && (int) $cart_item['product_id'] === (int) $bump_product_id && ! empty( $cart_item['gvn_order_bump'] ) ) {
            $bump_in_cart = true;
            break;
        }
    }
}

$split_heading = '' !== trim( (string) $title_text ) ? $title_text : __( 'Inserir detalhes de pagamento', 'gvn-checkout' );

$cart_items = $cart ? $cart->get_cart() : array();
$cart_count = count( $cart_items );
$first_name = '';
if ( 1 === $cart_count ) {
    $only_item = reset( $cart_items );
    if ( isset( $only_item['data'] ) && is_object( $only_item['data'] ) ) {
        $first_name = $only_item['data']->get_name();
    }
}
$split_plan_label = '' !== $first_name ? $first_name : __( 'Resumo do pedido', 'gvn-checkout' );
?>

<div class="gvn-checkout gvn-layout-split" id="gvn-checkout" data-layout="split">

    <?php do_action( 'woocommerce_before_checkout_form', $checkout ); ?>

    <form name="checkout" method="post" class="checkout woocommerce-checkout gvn-form gvn-split__form" action="<?php echo esc_url( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '' ); ?>" enctype="multipart/form-data">

        <div class="gvn-split">

            <!-- Coluna principal: dados do cliente (DOM primeiro = mesma ordem de hooks do clássico) -->
            <div class="gvn-split__main">
                <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

                <div class="gvn-split__panel" id="customer_details">
                    <h2 class="gvn-split__heading"><?php echo esc_html( $split_heading ); ?></h2>
                    <?php if ( '' !== trim( (string) $subtitle_text ) ) : ?>
                        <p class="gvn-split__subheading"><?php echo esc_html( $subtitle_text ); ?></p>
                    <?php endif; ?>

                    <div class="gvn-split__section-label"><?php esc_html_e( 'Dados de contato', 'gvn-checkout' ); ?></div>

                    <?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

                    <?php $gvn_fields = class_exists( 'GVN_Custom_Fields' ) ? GVN_Custom_Fields::get_enabled_fields() : array(); ?>
                    <?php
                    $gvn_field_groups = array(
                        array(
                            'label'  => '',
                            'fields' => array(),
                        ),
                        array(
                            'label'  => __( 'Endereço', 'gvn-checkout' ),
                            'fields' => array(),
                        ),
                    );

                    foreach ( $gvn_fields as $gvn_field ) {
                        $gvn_group_index = ( class_exists( 'GVN_Custom_Fields' ) && GVN_Custom_Fields::is_address_field( $gvn_field ) ) ? 1 : 0;
                        $gvn_field_groups[ $gvn_group_index ]['fields'][] = $gvn_field;
                    }
                    ?>
                    <div class="gvn-fields-dynamic">
                        <?php
                        // Contador por chave: ocorrências duplicadas da mesma key ganham
                        // id único (name permanece igual). A 1ª mantém o id original.
                        $gvn_key_counts = array();
                        ?>
                        <?php foreach ( $gvn_field_groups as $gvn_group ) : ?>
                            <?php if ( ! empty( $gvn_group['fields'] ) ) : ?>
                                <?php if ( ! empty( $gvn_group['label'] ) ) : ?>
                                    <h3 class="gvn-fields-divider"><?php echo esc_html( $gvn_group['label'] ); ?></h3>
                                <?php endif; ?>
                                <?php foreach ( $gvn_group['fields'] as $gvn_field ) :
                                    $f_key         = esc_attr( $gvn_field['key'] );
                                    if ( ! isset( $gvn_key_counts[ $gvn_field['key'] ] ) ) {
                                        $gvn_key_counts[ $gvn_field['key'] ] = 0;
                                    }
                                    $gvn_key_counts[ $gvn_field['key'] ]++;
                                    $f_id            = 1 === $gvn_key_counts[ $gvn_field['key'] ] ? $f_key : $f_key . '--' . $gvn_key_counts[ $gvn_field['key'] ];
                                    $f_id_attr       = esc_attr( $f_id );
                                    $f_label       = esc_html( $gvn_field['label'] );
                                    $f_type        = $gvn_field['type'];
                                    $f_required    = ! empty( $gvn_field['required'] );
                                    $f_placeholder = esc_attr( $gvn_field['placeholder'] );
                                    $f_width       = $gvn_field['width'];
                                    $f_mask        = ! empty( $gvn_field['mask'] ) ? $gvn_field['mask'] : '';
                                    $f_raw_value   = ( $checkout && method_exists( $checkout, 'get_value' ) ) ? $checkout->get_value( $gvn_field['key'] ) : '';
                                    $f_value       = esc_attr( $f_raw_value );
                                    $width_class   = 'gvn-field--w' . $f_width;
                                    $f_conditions  = isset( $gvn_field['conditions'] ) ? $gvn_field['conditions'] : array( 'logic' => 'and', 'rules' => array() );
                                    $has_conditions = class_exists( 'GVN_Custom_Fields' ) ? GVN_Custom_Fields::has_conditions( $gvn_field ) : false;
                                    $f_orig_required = $f_required;
                                ?>
                                <div class="gvn-field <?php echo esc_attr( $width_class ); ?><?php echo $has_conditions ? ' gvn-field--conditional' : ''; ?>" data-field-key="<?php echo $f_key; ?>" data-mask="<?php echo esc_attr( $f_mask ); ?>"<?php if ( $has_conditions ) : ?> data-conditions="<?php echo esc_attr( wp_json_encode( $f_conditions ) ); ?>" data-required="<?php echo $f_orig_required ? '1' : '0'; ?>"<?php endif; ?>>
                                    <label class="gvn-field__label" for="<?php echo $f_id_attr; ?>">
                                        <?php echo $f_label; ?>
                                        <?php if ( $f_required ) : ?><span class="gvn-field__required">*</span><?php endif; ?>
                                        <?php if ( ! $f_required && 'order_comments' === $f_key ) : ?><span class="gvn-field__optional">(<?php esc_html_e( 'opcional', 'gvn-checkout' ); ?>)</span><?php endif; ?>
                                    </label>
                                    <?php if ( 'textarea' === $f_type ) : ?>
                                        <textarea class="gvn-field__input gvn-field__textarea" name="<?php echo $f_key; ?>" id="<?php echo $f_id_attr; ?>" rows="3" placeholder="<?php echo $f_placeholder; ?>" <?php echo $f_required ? 'required' : ''; ?>><?php echo esc_textarea( $f_raw_value ); ?></textarea>
                                    <?php elseif ( 'select' === $f_type ) :
                                        $f_options_raw  = isset( $gvn_field['options'] ) ? $gvn_field['options'] : '';
                                        $f_options      = class_exists( 'GVN_Custom_Fields' ) ? GVN_Custom_Fields::parse_select_options( $f_options_raw ) : array();
                                        $f_default_opt  = isset( $gvn_field['default_option'] ) ? $gvn_field['default_option'] : '';
                                        if ( '' !== $f_default_opt && ! isset( $f_options[ $f_default_opt ] ) ) {
                                            $f_default_opt = '';
                                        }
                                        $f_select_value = ( null !== $f_raw_value && '' !== $f_raw_value ) ? $f_raw_value : $f_default_opt;
                                    ?>
                                        <select class="gvn-field__input gvn-field__select" name="<?php echo $f_key; ?>" id="<?php echo $f_id_attr; ?>" <?php echo $f_required ? 'required' : ''; ?>>
                                            <option value=""><?php echo $f_placeholder ? esc_html( $f_placeholder ) : '-- ' . esc_html__( 'Selecione', 'gvn-checkout' ) . ' --'; ?></option>
                                            <?php if ( empty( $f_options ) ) : ?>
                                                <option value="" disabled><?php echo esc_html__( 'Nenhuma opção configurada', 'gvn-checkout' ); ?></option>
                                            <?php else : ?>
                                                <?php foreach ( $f_options as $opt_value => $opt_label ) : ?>
                                                    <option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $f_select_value, $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    <?php else : ?>
                                        <input type="<?php echo esc_attr( $f_type ); ?>" class="gvn-field__input" name="<?php echo $f_key; ?>" id="<?php echo $f_id_attr; ?>" value="<?php echo $f_value; ?>" placeholder="<?php echo $f_placeholder; ?>" <?php echo $f_required ? 'required' : ''; ?> />
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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

                    // Um gateway pode declarar que um campo nativo precisa permanecer visível.
                    // A declaração é específica do gateway; não executamos código do gateway aqui.
                    $gvn_gateway_resolver = new \GVN\Checkout\Payments\GatewayRequirementsResolver();
                    $gvn_posted_data      = array();
                    if ( isset( $_POST['payment_method'] ) ) {
                        $gvn_posted_data['payment_method'] = sanitize_key( wp_unslash( $_POST['payment_method'] ) );
                    }
                    foreach ( array( 'payment_method_variant', 'gateway_variant', 'payment_variant' ) as $gvn_context_key ) {
                        if ( isset( $_POST[ $gvn_context_key ] ) ) {
                            $gvn_posted_data[ $gvn_context_key ] = sanitize_key( wp_unslash( $_POST[ $gvn_context_key ] ) );
                        }
                    }
                    $gvn_gateway_id       = $gvn_gateway_resolver->get_current_gateway_id( $gvn_posted_data );
                    $gvn_checkout_fields  = array();
                    if ( $checkout && method_exists( $checkout, 'get_checkout_fields' ) ) {
                        $gvn_checkout_fields = (array) $checkout->get_checkout_fields();
                    }

                    // Renderiza os campos não exigidos como hidden e os requisitos confirmados como campo nativo.
                    foreach ( $woo_hidden_defaults as $wk => $wval ) {
                        if ( $gvn_gateway_resolver->is_field_required( $wk, $gvn_checkout_fields, $gvn_gateway_id, $gvn_posted_data, false ) ) {
                            $gvn_native_field = isset( $gvn_checkout_fields['billing'][ $wk ] ) && is_array( $gvn_checkout_fields['billing'][ $wk ] )
                                ? $gvn_checkout_fields['billing'][ $wk ]
                                : array(
                                    'type'     => 'text',
                                    'label'    => ucwords( str_replace( '_', ' ', preg_replace( '/^billing_/', '', $wk ) ) ),
                                    'required' => true,
                                );
                            $gvn_native_field['required'] = true;
                            $gvn_native_value = ( $checkout && method_exists( $checkout, 'get_value' ) ) ? $checkout->get_value( $wk ) : $wval;

                            if ( function_exists( 'woocommerce_form_field' ) ) {
                                woocommerce_form_field( $wk, $gvn_native_field, $gvn_native_value );
                            } else {
                                echo '<p class="form-row form-row-wide"><label for="' . esc_attr( $wk ) . '">' . esc_html( $gvn_native_field['label'] ) . ' <abbr class="required" title="required">*</abbr></label><input type="text" name="' . esc_attr( $wk ) . '" id="' . esc_attr( $wk ) . '" value="' . esc_attr( $gvn_native_value ) . '" required /></p>';
                            }
                        } else {
                            echo '<input type="hidden" name="' . esc_attr( $wk ) . '" value="' . esc_attr( $wval ) . '" />';
                        }
                    }
                    ?>
                </div>

                <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
            </div><!-- .gvn-split__main -->

            <!-- Coluna lateral: resumo do pedido (posicionada à esquerda via CSS grid) -->
            <aside class="gvn-split__summary">
                <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

                <div class="gvn-split__brand">
                    <?php if ( '' !== $shop_url ) : ?>
                        <a class="gvn-split__back" href="<?php echo esc_url( $shop_url ); ?>" aria-label="<?php esc_attr_e( 'Voltar à loja', 'gvn-checkout' ); ?>">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                        </a>
                    <?php endif; ?>
                    <span class="gvn-split__brand-check" aria-hidden="true">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </span>
                    <span class="gvn-split__brand-name"><?php echo esc_html( $header_text ); ?></span>
                </div>

                <div class="gvn-split__plan">
                    <p class="gvn-split__plan-label"><?php echo esc_html( $split_plan_label ); ?></p>
                    <p class="gvn-split__plan-price" id="gvn-split-headline-total"><?php echo ( $cart ) ? $cart->get_total() : ''; ?></p>
                </div>

                <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

                <div class="gvn-order-items gvn-split__items" id="gvn-order-items">
                    <?php if ( $cart ) : ?>
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
                    <?php endif; ?>
                </div>

                <div class="gvn-order-totals gvn-split__totals" id="gvn-order-totals">
                    <div class="gvn-order-totals__row cart-subtotal">
                        <span class="gvn-order-totals__label"><?php esc_html_e( 'Subtotal', 'gvn-checkout' ); ?></span>
                        <span class="gvn-order-totals__value" id="gvn-subtotal"><?php echo ( $cart && function_exists( 'wc_price' ) ) ? wc_price( $cart->get_subtotal() ) : ''; ?></span>
                    </div>

                    <?php if ( $cart && $cart->get_discount_total() > 0 ) : ?>
                        <div class="gvn-order-totals__row gvn-order-totals__row--discount cart-discount" id="gvn-discount-row">
                            <span class="gvn-order-totals__label"><?php esc_html_e( 'Desconto', 'gvn-checkout' ); ?></span>
                            <span class="gvn-order-totals__value" id="gvn-discount">-<?php echo function_exists( 'wc_price' ) ? wc_price( $cart->get_discount_total() ) : ''; ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="gvn-order-totals__total order-total">
                        <span class="gvn-order-totals__total-label"><?php esc_html_e( 'Total', 'gvn-checkout' ); ?></span>
                        <span class="gvn-order-totals__total-value" id="gvn-total"><?php echo ( $cart ) ? $cart->get_total() : '<bdi>0</bdi>'; ?></span>
                    </div>
                </div>

                <?php if ( $coupon_enabled ) : ?>
                    <div class="gvn-split__promo">
                        <button type="button" class="gvn-split__promo-btn" id="gvn-coupon-toggle" aria-expanded="false" aria-controls="gvn-coupon-form">
                            <?php esc_html_e( 'Adicionar código promocional', 'gvn-checkout' ); ?>
                        </button>
                        <div class="gvn-coupon__form gvn-split__promo-form" id="gvn-coupon-form" style="display: none;">
                            <div class="gvn-coupon__form-inner">
                                <input type="text" class="gvn-field__input" id="gvn-coupon-code" placeholder="<?php esc_attr_e( 'Digite o código do cupom', 'gvn-checkout' ); ?>" />
                                <button type="button" class="gvn-coupon__btn" id="gvn-apply-coupon"><?php esc_html_e( 'Aplicar', 'gvn-checkout' ); ?></button>
                            </div>
                            <div class="gvn-coupon__message" id="gvn-coupon-message"></div>

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
                        </div>
                    </div>
                <?php endif; ?>

                <div class="gvn-split__secure">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    <span><?php echo esc_html( $header_badge_text ); ?></span>
                </div>

                <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
            </aside><!-- .gvn-split__summary -->

            <!-- Coluna principal (continuação): pagamento -->
            <div class="gvn-split__pay">
                <div class="gvn-split__panel gvn-split__panel--pay">
                    <div class="gvn-split__section-label"><?php esc_html_e( 'Forma de pagamento', 'gvn-checkout' ); ?></div>

                    <?php do_action( 'woocommerce_review_order_before_payment' ); ?>

                    <div class="gvn-gateways" id="payment">
                        <?php if ( ! empty( $available_gateways ) ) : ?>
                            <div class="gvn-gateways__list">
                                <?php
                                $gvn_chosen_gateway = '';
                                if ( function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
                                    $gvn_chosen_gateway = (string) WC()->session->get( 'chosen_payment_method', '' );
                                }
                                if ( ! isset( $available_gateways[ $gvn_chosen_gateway ] ) && ! empty( $available_gateways ) ) {
                                    $gvn_gateway_ids    = array_keys( $available_gateways );
                                    $gvn_chosen_gateway = (string) reset( $gvn_gateway_ids );
                                }

                                foreach ( $available_gateways as $gateway_id => $gateway ) :
                                    $is_selected = ( $gateway_id === $gvn_chosen_gateway );
                                    $extra_info = '';
                                    $is_recommended = false;

                                    if ( stripos( $gateway_id, 'pix' ) !== false ) {
                                        $extra_info     = __( 'Confirmação imediata', 'gvn-checkout' );
                                        $is_recommended = true;
                                    } elseif ( stripos( $gateway_id, 'boleto' ) !== false || stripos( $gateway_id, 'bacs' ) !== false || stripos( $gateway_id, 'ticket' ) !== false ) {
                                        $extra_info = __( 'Compensação bancária', 'gvn-checkout' );
                                    } elseif ( stripos( $gateway_id, 'card' ) !== false || stripos( $gateway_id, 'credit' ) !== false || stripos( $gateway_id, 'cc' ) !== false || stripos( $gateway_id, 'stripe' ) !== false ) {
                                        $extra_info = __( 'Parcelamento disponível', 'gvn-checkout' );
                                    }
                                ?>
                                    <div class="gvn-gateway <?php echo $is_selected ? 'gvn-gateway--active' : ''; ?>" data-gateway="<?php echo esc_attr( $gateway_id ); ?>">
                                        <input type="radio" name="payment_method" id="payment_method_<?php echo esc_attr( $gateway_id ); ?>" value="<?php echo esc_attr( $gateway_id ); ?>" <?php checked( $is_selected ); ?> class="gvn-gateway__radio" />
                                        <div class="gvn-gateway__info">
                                            <div class="gvn-gateway__name-row">
                                                <span class="gvn-gateway__name"><?php echo esc_html( $gateway->get_title() ); ?></span>
                                                <?php if ( $is_recommended ) : ?>
                                                    <span class="gvn-gateway__badge"><?php esc_html_e( 'Recomendado', 'gvn-checkout' ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ( $extra_info ) : ?>
                                                <p class="gvn-gateway__desc"><?php echo esc_html( $extra_info ); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php
                                endforeach;
                                ?>
                            </div>

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
                            <div class="gvn-gateways__empty-box">
                                <p class="gvn-gateways__empty"><?php esc_html_e( 'Nenhum método de pagamento disponível no momento. Por favor, entre em contato com o suporte.', 'gvn-checkout' ); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ( $bump_product ) :
                        $bump_price_raw = $bump_price;
                        if ( '' !== $bump_price_raw && floatval( $bump_price_raw ) > 0 && function_exists( 'wc_price' ) ) {
                            $bump_display_price = wc_price( floatval( $bump_price_raw ) );
                        } elseif ( function_exists( 'wc_price' ) ) {
                            $bump_display_price = wc_price( $bump_product->get_price() );
                        } else {
                            $bump_display_price = '';
                        }
                    ?>
                        <div class="gvn-order-bump gvn-split__bump" id="gvn-order-bump">
                            <div class="gvn-order-bump__inner">
                                <div class="gvn-order-bump__checkbox">
                                    <input type="checkbox" class="gvn-order-bump__input" id="gvn-bump-checkbox" <?php checked( $bump_in_cart ); ?> />
                                </div>
                                <div class="gvn-order-bump__content">
                                    <div class="gvn-order-bump__badge">
                                        ⚡ <?php echo esc_html( strtoupper( $bump_title ) ); ?>
                                    </div>
                                    <h3 class="gvn-order-bump__product">
                                        <?php echo esc_html( $bump_product->get_name() ); ?><br />
                                        <span class="gvn-order-bump__price"><?php esc_html_e( 'por apenas', 'gvn-checkout' ); ?> <?php echo $bump_display_price; ?></span>
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

                    <div class="woocommerce-terms-and-conditions-wrapper gvn-terms-wrapper">
                        <?php
                        /**
                         * Terms and conditions hook.
                         *
                         * @hooked wc_checkout_privacy_policy_text - 20
                         * @hooked wc_terms_and_conditions_page_content - 30
                         */
                        do_action( 'woocommerce_checkout_terms_and_conditions' );
                        ?>

                        <?php if ( function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) && wc_terms_and_conditions_checkbox_enabled() ) : ?>
                            <p class="form-row validate-required gvn-terms-row">
                                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox gvn-terms-label">
                                    <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox gvn-terms-checkbox" name="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing ?> id="terms" />
                                    <span class="woocommerce-terms-and-conditions-checkbox-text"><?php function_exists( 'wc_terms_and_conditions_checkbox_text' ) ? wc_terms_and_conditions_checkbox_text() : esc_html_e( 'Concordo com os termos e condições', 'gvn-checkout' ); ?></span>&nbsp;<abbr class="required" title="<?php esc_attr_e( 'required', 'woocommerce' ); ?>">*</abbr>
                                </label>
                                <input type="hidden" name="terms-field" value="1" />
                            </p>
                        <?php elseif ( ! has_action( 'woocommerce_checkout_terms_and_conditions' ) ) : ?>
                            <?php if ( function_exists( 'wc_checkout_privacy_policy_text' ) && wc_checkout_privacy_policy_text() ) : ?>
                                <?php wc_checkout_privacy_policy_text(); ?>
                            <?php elseif ( function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url() ) : ?>
                                <p class="gvn-privacy">
                                    <?php esc_html_e( 'Os seus dados pessoais serão utilizados para processar a sua compra, apoiar a sua experiência em todo este site e para outros fins descritos na nossa', 'gvn-checkout' ); ?>
                                    <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" class="gvn-privacy__link" target="_blank"><?php esc_html_e( 'política de privacidade', 'gvn-checkout' ); ?></a>.
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php do_action( 'woocommerce_after_checkout_terms_and_conditions' ); ?>

                    <?php do_action( 'woocommerce_review_order_before_submit' ); ?>

                    <div class="gvn-submit gvn-split__submit">
                        <?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
                        <?php
                        $order_button_text = apply_filters( 'woocommerce_order_button_text', $button_text );
                        $order_button_html = '<button type="submit" class="gvn-submit__btn gvn-split__cta" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>';
                        echo apply_filters( 'woocommerce_order_button_html', $order_button_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>

                    <?php do_action( 'woocommerce_review_order_after_submit' ); ?>

                    <?php do_action( 'woocommerce_review_order_after_payment' ); ?>

                </div>
            </div><!-- .gvn-split__pay -->

        </div><!-- .gvn-split -->

    </form>

    <?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

</div><!-- .gvn-checkout.gvn-layout-split -->
