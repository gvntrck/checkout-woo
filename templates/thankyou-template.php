<?php
/**
 * Template de confirmação de pedido (Thank You) do GVN Checkout.
 *
 * @package GVN_Checkout
 * @version 1.13.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$header_text       = get_option( 'gvn_checkout_header_text', 'EFEAD - Conectando Saberes' );
$header_badge_text = get_option( 'gvn_checkout_header_badge_text', 'COMPRA SEGURA' );
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

        <?php if ( $order->has_status( 'failed' ) ) : ?>

            <!-- Pedido Falhou -->
            <div class="gvn-thankyou__status gvn-thankyou__status--failed">
                <div class="gvn-thankyou__status-icon">✕</div>
                <h1 class="gvn-thankyou__status-title">Pagamento não processado</h1>
                <p class="gvn-thankyou__status-text">
                    Infelizmente seu pagamento não pôde ser processado. Tente novamente ou entre em contato conosco.
                </p>
                <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="gvn-thankyou__retry-btn">
                    Tentar novamente
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
                <h1 class="gvn-thankyou__status-title">Pedido recebido!</h1>
                <p class="gvn-thankyou__status-text">
                    Obrigado pela sua compra. Seu pedido foi registrado com sucesso.
                </p>
            </div>

            <!-- Detalhes do Pedido -->
            <div class="gvn-thankyou__grid">

                <!-- Info Cards -->
                <div class="gvn-thankyou__info-cards">
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label">Número do pedido</span>
                        <span class="gvn-thankyou__info-value">#<?php echo esc_html( $order->get_order_number() ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label">Data</span>
                        <span class="gvn-thankyou__info-value"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label">E-mail</span>
                        <span class="gvn-thankyou__info-value"><?php echo esc_html( $order->get_billing_email() ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label">Total</span>
                        <span class="gvn-thankyou__info-value gvn-thankyou__info-value--highlight"><?php echo $order->get_formatted_order_total(); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label">Método de pagamento</span>
                        <span class="gvn-thankyou__info-value"><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
                    </div>
                    <div class="gvn-thankyou__info-card">
                        <span class="gvn-thankyou__info-label">Status</span>
                        <span class="gvn-thankyou__info-value">
                            <span class="gvn-thankyou__status-badge gvn-thankyou__status-badge--<?php echo esc_attr( $order->get_status() ); ?>">
                                <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Resumo dos Itens -->
                <div class="gvn-card">
                    <div class="gvn-card__header">ITENS DO PEDIDO</div>
                    <div class="gvn-card__body">
                        <div class="gvn-order-labels">
                            <span>Produto</span>
                            <span>Subtotal</span>
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
                                <div class="gvn-order-item__subtotal"><?php echo $subtotal; ?></div>
                            </div>
                        <?php endforeach; ?>

                        <div class="gvn-order-totals">
                            <div class="gvn-order-totals__row">
                                <span class="gvn-order-totals__label">Subtotal</span>
                                <span class="gvn-order-totals__value"><?php echo wc_price( $order->get_subtotal() ); ?></span>
                            </div>

                            <?php if ( $order->get_total_discount() > 0 ) : ?>
                                <div class="gvn-order-totals__row gvn-order-totals__row--discount">
                                    <span class="gvn-order-totals__label">Desconto</span>
                                    <span class="gvn-order-totals__value">-<?php echo wc_price( $order->get_total_discount() ); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ( $order->get_total_tax() > 0 ) : ?>
                                <div class="gvn-order-totals__row">
                                    <span class="gvn-order-totals__label">Impostos</span>
                                    <span class="gvn-order-totals__value"><?php echo wc_price( $order->get_total_tax() ); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ( $order->get_shipping_total() > 0 ) : ?>
                                <div class="gvn-order-totals__row">
                                    <span class="gvn-order-totals__label">Frete</span>
                                    <span class="gvn-order-totals__value"><?php echo wc_price( $order->get_shipping_total() ); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="gvn-order-totals__total">
                                <span class="gvn-order-totals__total-label">Total</span>
                                <span class="gvn-order-totals__total-value"><?php echo $order->get_formatted_order_total(); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Instruções do gateway de pagamento -->
            <?php
            $payment_method = $order->get_payment_method();
            if ( $payment_method ) :
                $gateways = WC()->payment_gateways()->get_available_payment_gateways();
                if ( isset( $gateways[ $payment_method ] ) ) :
                    $gateway = $gateways[ $payment_method ];
                    ob_start();
                    // Hook específico do método de pagamento.
                    do_action( 'woocommerce_thankyou_' . $payment_method, $order->get_id() );
                    // Hook global do WC (plugins de e-mail, tracking, etc. dependem dele).
                    do_action( 'woocommerce_thankyou', $order->get_id() );
                    $gateway_output = ob_get_clean();
                    if ( ! empty( trim( $gateway_output ) ) ) :
            ?>
                <div class="gvn-thankyou__payment-instructions">
                    <div class="gvn-card">
                        <div class="gvn-card__header">INSTRUÇÕES DE PAGAMENTO</div>
                        <div class="gvn-card__body">
                            <?php echo $gateway_output; ?>
                        </div>
                    </div>
                </div>
            <?php
                    endif;
                endif;
            endif;
            ?>

            <!-- Dados do cliente -->
            <div class="gvn-thankyou__customer">
                <div class="gvn-card">
                    <div class="gvn-card__header">SEUS DADOS</div>
                    <div class="gvn-card__body">
                        <div class="gvn-thankyou__customer-grid">
                            <div class="gvn-thankyou__customer-col">
                                <h4 class="gvn-thankyou__customer-heading">Dados de cobrança</h4>
                                <p class="gvn-thankyou__customer-text">
                                    <?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?><br />
                                    <?php echo esc_html( $order->get_billing_email() ); ?><br />
                                    <?php if ( $order->get_billing_phone() ) : ?>
                                        <?php echo esc_html( $order->get_billing_phone() ); ?><br />
                                    <?php endif; ?>
                                    <?php
                                    $cpf = $order->get_meta( '_billing_cpf' );
                                    if ( $cpf ) :
                                    ?>
                                        CPF: <?php echo esc_html( $cpf ); ?>
                                    <?php endif; ?>
                                </p>

                                <?php
                                $custom_fields = GVN_Custom_Fields::get_enabled_fields();
                                $has_custom = false;
                                // Campos já exibidos acima ou tratados pelo Woo nativamente.
                                $skip_keys = array(
                                    'billing_first_name', 'billing_last_name', 'billing_email',
                                    'billing_phone', 'billing_company', 'billing_address_1',
                                    'billing_address_2', 'billing_city', 'billing_state',
                                    'billing_postcode', 'billing_country', 'order_comments',
                                    'billing_cpf',
                                );
                                foreach ( $custom_fields as $field ) {
                                    if ( ! empty( $field['is_default'] ) || in_array( $field['key'], $skip_keys, true ) ) continue;
                                    $val = $order->get_meta( '_' . $field['key'] );
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
                                    <h4 class="gvn-thankyou__customer-heading">Observações</h4>
                                    <p class="gvn-thankyou__customer-text"><?php echo esc_html( $order->get_customer_note() ); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ações -->
            <div class="gvn-thankyou__actions">
                <?php if ( is_user_logged_in() ) : ?>
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="gvn-thankyou__action-btn gvn-thankyou__action-btn--primary">
                        Ver meus pedidos
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="gvn-thankyou__action-btn gvn-thankyou__action-btn--secondary">
                    Continuar comprando
                </a>
            </div>

        <?php endif; ?>

    <?php else : ?>

        <!-- Pedido não encontrado -->
        <div class="gvn-thankyou__status gvn-thankyou__status--not-found">
            <div class="gvn-thankyou__status-icon">?</div>
            <h1 class="gvn-thankyou__status-title">Pedido não encontrado</h1>
            <p class="gvn-thankyou__status-text">
                Não foi possível localizar seu pedido. Verifique se o link está correto ou entre em contato conosco.
            </p>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="gvn-thankyou__action-btn gvn-thankyou__action-btn--primary">
                Voltar à loja
            </a>
        </div>

    <?php endif; ?>

</div><!-- .gvn-thankyou -->
