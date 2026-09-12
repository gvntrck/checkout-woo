<?php
/**
 * Template de confirmação de pedido (Thank You) do GVN Checkout.
 *
 * @package GVN_Checkout
 * @version 1.15.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$header_text       = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'header_text', 'EFEAD - Conectando Saberes' ) : get_option( 'gvn_checkout_header_text', 'EFEAD - Conectando Saberes' );
$header_badge_text = class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ? \GVN\Checkout\Settings\SettingsRepository::get( 'header_badge_text', 'COMPRA SEGURA' ) : get_option( 'gvn_checkout_header_badge_text', 'COMPRA SEGURA' );
$order_context     = isset( $order ) && is_object( $order ) ? $order : null;

$thankyou_defaults = array(
    'thankyou_success_title'          => 'Pedido recebido!',
    'thankyou_success_message'        => 'Obrigado pela sua compra. Seu pedido foi registrado com sucesso.',
    'thankyou_failed_title'           => 'Pagamento não processado',
    'thankyou_failed_message'         => 'Infelizmente seu pagamento não pôde ser processado. Tente novamente ou entre em contato conosco.',
    'thankyou_retry_text'             => 'Tentar novamente',
    'thankyou_not_found_title'        => 'Pedido não encontrado',
    'thankyou_not_found_message'      => 'Não foi possível localizar seu pedido. Verifique se o link está correto ou entre em contato conosco.',
    'thankyou_not_found_button_text'  => 'Voltar à loja',
    'thankyou_payment_title'          => 'INSTRUÇÕES DE PAGAMENTO',
    'thankyou_items_title'            => 'ITENS DO PEDIDO',
    'thankyou_customer_title'         => 'SEUS DADOS',
    'thankyou_orders_button_text'     => 'Ver meus pedidos',
    'thankyou_shop_button_text'       => 'Continuar comprando',
);
$thankyou_texts = array();

foreach ( $thankyou_defaults as $thankyou_key => $thankyou_default ) {
    $thankyou_texts[ $thankyou_key ] = class_exists( 'GVN\Checkout\Settings\SettingsRepository' )
        ? \GVN\Checkout\Settings\SettingsRepository::get( $thankyou_key, $thankyou_default )
        : $thankyou_default;
}

if ( class_exists( 'GVN\Checkout\Checkout\TextPlaceholderResolver' ) ) {
    $header_text       = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $header_text, $order_context );
    $header_badge_text = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $header_badge_text, $order_context );
    foreach ( $thankyou_texts as $thankyou_key => $thankyou_text ) {
        $thankyou_texts[ $thankyou_key ] = \GVN\Checkout\Checkout\TextPlaceholderResolver::resolve( (string) $thankyou_text, $order_context );
    }
}
?>

<div class="gvn-checkout gvn-thankyou" id="gvn-thankyou">

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

    <?php if ( $order ) : ?>
        <?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

        <?php if ( $order->has_status( 'failed' ) ) : ?>

            <!-- Pedido Falhou -->
            <div class="gvn-thankyou__status gvn-thankyou__status--failed">
                <div class="gvn-thankyou__status-icon">✕</div>
                <h1 class="gvn-thankyou__status-title"><?php echo esc_html( $thankyou_texts['thankyou_failed_title'] ); ?></h1>
                <p class="gvn-thankyou__status-text">
                    <?php echo esc_html( $thankyou_texts['thankyou_failed_message'] ); ?>
                </p>
                <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="gvn-thankyou__retry-btn">
                    <?php echo esc_html( $thankyou_texts['thankyou_retry_text'] ); ?>
                </a>
            </div>

        <?php else : ?>

            <!-- Pedido Recebido -->
            <div class="gvn-thankyou__status gvn-thankyou__status--success">
                <div class="gvn-thankyou__status-icon">
                    <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="3" opacity="0.2"/>
                        <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="3" stroke-dasharray="188" stroke-dashoffset="0" class="gvn-thankyou__circle"/>
                        <path d="M20 33l8 8 16-16" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" class="gvn-thankyou__check"/>
                    </svg>
                </div>
                <h1 class="gvn-thankyou__status-title"><?php echo esc_html( $thankyou_texts['thankyou_success_title'] ); ?></h1>
                <p class="gvn-thankyou__status-text">
                    <?php echo esc_html( $thankyou_texts['thankyou_success_message'] ); ?>
                </p>
            </div>

            <!-- Detalhes do Pedido -->
            <div class="gvn-thankyou__grid">

                <!-- Info Cards -->
                <div class="gvn-thankyou__info-cards">
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label"><?php esc_html_e( 'Número do pedido', 'gvn-checkout' ); ?></span>
                        <span class="gvn-thankyou__info-value">#<?php echo esc_html( $order->get_order_number() ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label"><?php esc_html_e( 'Data', 'gvn-checkout' ); ?></span>
                        <span class="gvn-thankyou__info-value"><?php echo esc_html( function_exists( 'wc_format_datetime' ) ? wc_format_datetime( $order->get_date_created() ) : date_i18n( get_option( 'date_format' ), strtotime( (string) $order->get_date_created() ) ) ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label"><?php esc_html_e( 'E-mail', 'gvn-checkout' ); ?></span>
                        <span class="gvn-thankyou__info-value"><?php echo esc_html( $order->get_billing_email() ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label"><?php esc_html_e( 'Total', 'gvn-checkout' ); ?></span>
                        <span class="gvn-thankyou__info-value gvn-thankyou__info-value--highlight"><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label"><?php esc_html_e( 'Método de pagamento', 'gvn-checkout' ); ?></span>
                        <span class="gvn-thankyou__info-value"><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label"><?php esc_html_e( 'Status', 'gvn-checkout' ); ?></span>
                        <span class="gvn-thankyou__info-value">
                            <span class="gvn-thankyou__status-badge gvn-thankyou__status-badge--<?php echo esc_attr( $order->get_status() ); ?>">
                                <?php echo esc_html( function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status() ); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Instruções do gateway de pagamento -->
                <?php
                $payment_method = $order->get_payment_method();
                if ( $payment_method ) :
                    ob_start();
                    // Executa para o método gravado no pedido, mesmo que ele já
                    // não esteja disponível para uma nova compra.
                    do_action( 'woocommerce_thankyou_' . $payment_method, $order->get_id() );
                    do_action( 'woocommerce_thankyou', $order->get_id() );
                    $gateway_output = ob_get_clean();
                    if ( ! empty( trim( $gateway_output ) ) ) :
                ?>
                    <div class="gvn-thankyou__payment-instructions" id="gvn-payment-instructions">
                        <div class="gvn-card">
                            <div class="gvn-card__header"><?php echo esc_html( $thankyou_texts['thankyou_payment_title'] ); ?></div>
                            <div class="gvn-card__body">
                                <?php echo $gateway_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    </div>
                    <script>
                    (function(){
                        var el = document.getElementById('gvn-payment-instructions');
                        if (!el) return;
                        var scroll = function(){ el.scrollIntoView({behavior:'smooth', block:'start'}); };
                        if (document.readyState === 'complete') { scroll(); }
                        else { window.addEventListener('load', scroll); }
                    })();
                    </script>
                <?php
                    endif;
                endif;
                ?>

                <!-- Resumo dos Itens -->
                <div class="gvn-card">
                    <div class="gvn-card__header"><?php echo esc_html( $thankyou_texts['thankyou_items_title'] ); ?></div>
                    <div class="gvn-card__body">
                        <div class="gvn-order-labels">
                            <span><?php esc_html_e( 'Produto', 'gvn-checkout' ); ?></span>
                            <span><?php esc_html_e( 'Subtotal', 'gvn-checkout' ); ?></span>
                        </div>

                        <?php foreach ( $order->get_items() as $item_id => $item ) :
                            $product  = $item->get_product();
                            $quantity = $item->get_quantity();
                            $subtotal = $order->get_formatted_line_subtotal( $item );
                        ?>
                            <div class="gvn-order-item">
                                <div class="gvn-order-item__name">
                                    <?php echo esc_html( $item->get_name() ); ?>
                                    <div class="gvn-order-item__qty">&times; <?php echo esc_html( $quantity ); ?></div>
                                </div>
                                <div class="gvn-order-item__subtotal"><?php echo $subtotal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            </div>
                        <?php endforeach; ?>

                        <div class="gvn-order-totals">
                            <div class="gvn-order-totals__row">
                                <span class="gvn-order-totals__label"><?php esc_html_e( 'Subtotal', 'gvn-checkout' ); ?></span>
                                <span class="gvn-order-totals__value"><?php echo function_exists( 'wc_price' ) ? wc_price( $order->get_subtotal() ) : esc_html( $order->get_subtotal() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            </div>

                            <?php if ( $order->get_total_discount() > 0 ) : ?>
                                <div class="gvn-order-totals__row gvn-order-totals__row--discount">
                                    <span class="gvn-order-totals__label"><?php esc_html_e( 'Desconto', 'gvn-checkout' ); ?></span>
                                    <span class="gvn-order-totals__value">-<?php echo function_exists( 'wc_price' ) ? wc_price( $order->get_total_discount() ) : esc_html( $order->get_total_discount() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ( $order->get_total_tax() > 0 ) : ?>
                                <div class="gvn-order-totals__row">
                                    <span class="gvn-order-totals__label"><?php esc_html_e( 'Impostos', 'gvn-checkout' ); ?></span>
                                    <span class="gvn-order-totals__value"><?php echo function_exists( 'wc_price' ) ? wc_price( $order->get_total_tax() ) : esc_html( $order->get_total_tax() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ( $order->get_shipping_total() > 0 ) : ?>
                                <div class="gvn-order-totals__row">
                                    <span class="gvn-order-totals__label"><?php esc_html_e( 'Frete', 'gvn-checkout' ); ?></span>
                                    <span class="gvn-order-totals__value"><?php echo function_exists( 'wc_price' ) ? wc_price( $order->get_shipping_total() ) : esc_html( $order->get_shipping_total() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="gvn-order-totals__total">
                                <span class="gvn-order-totals__total-label"><?php esc_html_e( 'Total', 'gvn-checkout' ); ?></span>
                                <span class="gvn-order-totals__total-value"><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Dados do cliente -->
            <div class="gvn-thankyou__customer">
                <div class="gvn-card">
                    <div class="gvn-card__header"><?php echo esc_html( $thankyou_texts['thankyou_customer_title'] ); ?></div>
                    <div class="gvn-card__body">
                        <div class="gvn-thankyou__customer-grid">
                            <div class="gvn-thankyou__customer-col">
                                <h4 class="gvn-thankyou__customer-heading"><?php esc_html_e( 'Dados de cobrança', 'gvn-checkout' ); ?></h4>
                                <p class="gvn-thankyou__customer-text">
                                    <?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?><br />
                                    <?php echo esc_html( $order->get_billing_email() ); ?><br />
                                    <?php if ( $order->get_billing_phone() ) : ?>
                                        <?php echo esc_html( $order->get_billing_phone() ); ?><br />
                                    <?php endif; ?>
                                    <?php
                                    $cpf = method_exists( $order, 'get_meta' ) ? $order->get_meta( '_billing_cpf' ) : '';
                                    if ( $cpf ) :
                                    ?>
                                        CPF: <?php echo esc_html( $cpf ); ?>
                                    <?php endif; ?>
                                </p>

                                <?php
                                $custom_fields = class_exists( 'GVN_Custom_Fields' ) ? GVN_Custom_Fields::get_enabled_fields() : array();
                                $has_custom = false;
                                $skip_keys = array(
                                    'billing_first_name', 'billing_last_name', 'billing_email',
                                    'billing_phone', 'billing_company', 'billing_address_1',
                                    'billing_address_2', 'billing_city', 'billing_state',
                                    'billing_postcode', 'billing_country', 'order_comments',
                                    'billing_cpf',
                                );
                                foreach ( $custom_fields as $field ) {
                                    if ( ! empty( $field['is_default'] ) || in_array( $field['key'], $skip_keys, true ) ) continue;
                                    $val = method_exists( $order, 'get_meta' ) ? $order->get_meta( '_' . $field['key'] ) : '';
                                    if ( $val ) {
                                        if ( ! $has_custom ) {
                                            echo '<div class="gvn-thankyou__custom-fields">';
                                            $has_custom = true;
                                        }
                                        echo '<p class="gvn-thankyou__customer-text"><strong>' . esc_html( $field['label'] ) . ':</strong> ' . esc_html( $val ) . '</p>';
                                    }
                                }
                                if ( $has_custom ) echo '</div>';
                                ?>
                            </div>

                            <?php if ( $order->get_customer_note() ) : ?>
                                <div class="gvn-thankyou__customer-col">
                                    <h4 class="gvn-thankyou__customer-heading"><?php esc_html_e( 'Observações', 'gvn-checkout' ); ?></h4>
                                    <p class="gvn-thankyou__customer-text"><?php echo esc_html( $order->get_customer_note() ); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ações -->
            <div class="gvn-thankyou__actions">
                <?php if ( is_user_logged_in() && function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="gvn-thankyou__action-btn gvn-thankyou__action-btn--primary">
                        <?php echo esc_html( $thankyou_texts['thankyou_orders_button_text'] ); ?>
                    </a>
                <?php endif; ?>
                <?php $shop_permalink = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : ''; ?>
                <a href="<?php echo esc_url( $shop_permalink ); ?>" class="gvn-thankyou__action-btn gvn-thankyou__action-btn--secondary">
                    <?php echo esc_html( $thankyou_texts['thankyou_shop_button_text'] ); ?>
                </a>
            </div>

        <?php endif; ?>

    <?php else : ?>

        <!-- Pedido não encontrado -->
        <div class="gvn-thankyou__status gvn-thankyou__status--not-found">
            <div class="gvn-thankyou__status-icon">?</div>
            <h1 class="gvn-thankyou__status-title"><?php echo esc_html( $thankyou_texts['thankyou_not_found_title'] ); ?></h1>
            <p class="gvn-thankyou__status-text">
                <?php echo esc_html( $thankyou_texts['thankyou_not_found_message'] ); ?>
            </p>
            <?php $shop_permalink = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : ''; ?>
            <a href="<?php echo esc_url( $shop_permalink ); ?>" class="gvn-thankyou__action-btn gvn-thankyou__action-btn--primary">
                <?php echo esc_html( $thankyou_texts['thankyou_not_found_button_text'] ); ?>
            </a>
        </div>

    <?php endif; ?>

</div><!-- .gvn-thankyou -->
