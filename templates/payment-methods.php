<?php
/**
 * Fragmento de métodos de pagamento atualizado pelo WooCommerce no checkout.
 *
 * @package GVN_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$chosen_gateway = '';
if ( function_exists( 'WC' ) && WC()->session ) {
    $chosen_gateway = (string) WC()->session->get( 'chosen_payment_method', '' );
}

if ( ! isset( $available_gateways[ $chosen_gateway ] ) && ! empty( $available_gateways ) ) {
    $gateway_ids    = array_keys( $available_gateways );
    $chosen_gateway = (string) reset( $gateway_ids );
}
?>
<div class="gvn-gateways woocommerce-checkout-payment" id="payment">
    <?php if ( ! empty( $available_gateways ) ) : ?>
        <div class="gvn-gateways__list">
            <?php foreach ( $available_gateways as $gateway_id => $gateway ) : ?>
                <?php
                $is_selected    = $gateway_id === $chosen_gateway;
                $is_recommended = false;
                $extra_info     = '';

                if ( stripos( $gateway_id, 'pix' ) !== false ) {
                    $extra_info     = __( 'Confirmação imediata', 'gvn-checkout' );
                    $is_recommended = true;
                } elseif ( stripos( $gateway_id, 'boleto' ) !== false || stripos( $gateway_id, 'bacs' ) !== false || stripos( $gateway_id, 'ticket' ) !== false ) {
                    $extra_info = __( 'Compensação bancária', 'gvn-checkout' );
                } elseif ( stripos( $gateway_id, 'card' ) !== false || stripos( $gateway_id, 'credit' ) !== false || stripos( $gateway_id, 'cc' ) !== false || stripos( $gateway_id, 'stripe' ) !== false ) {
                    $extra_info = __( 'Parcelamento disponível', 'gvn-checkout' );
                }
                ?>
                <div class="gvn-gateway <?php echo esc_attr( $is_selected ? 'gvn-gateway--active' : '' ); ?>" data-gateway="<?php echo esc_attr( $gateway_id ); ?>">
                    <input type="radio" name="payment_method" id="payment_method_<?php echo esc_attr( $gateway_id ); ?>" value="<?php echo esc_attr( $gateway_id ); ?>" <?php checked( $is_selected ); ?> class="gvn-gateway__radio" />
                    <div class="gvn-gateway__icon <?php echo $is_recommended ? 'gvn-gateway__icon--recommended' : 'gvn-gateway__icon--default'; ?>" aria-hidden="true">&#9670;</div>
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
            <?php endforeach; ?>
        </div>

        <div class="gvn-gateways__content" id="gvn-gateway-content">
            <?php foreach ( $available_gateways as $gateway_id => $gateway ) : ?>
                <div class="gvn-gateway-fields" id="gvn-gateway-fields-<?php echo esc_attr( $gateway_id ); ?>"<?php if ( $gateway_id !== $chosen_gateway ) : ?> style="display: none;"<?php endif; ?>>
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
