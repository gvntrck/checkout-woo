<?php
/**
 * Partial do campo individual do checkout GVN.
 *
 * Fonte única do wrapper do campo (condicionais incluídas) para os 4 layouts.
 * Espera no escopo: $gvn_field (array) e $checkout (WC_Checkout|null).
 * Não pré-oculta condicionais: sem JS todos ficam visíveis e o
 * server-side (FieldValidator/FieldOrderPersister) decide no submit.
 *
 * @package GVN_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// O partial é incluído dentro do foreach de campos dos layouts; sem campo válido não há o que renderizar.
if ( ! isset( $gvn_field ) || ! is_array( $gvn_field ) || empty( $gvn_field['key'] ) ) {
    return;
}

if ( ! isset( $checkout ) ) {
    $checkout = null;
}

$f_key           = esc_attr( $gvn_field['key'] );
$f_label         = esc_html( $gvn_field['label'] );
$f_type          = $gvn_field['type'];
$f_required      = ! empty( $gvn_field['required'] );
$f_placeholder   = esc_attr( $gvn_field['placeholder'] );
$f_width         = $gvn_field['width'];
$f_mask          = ! empty( $gvn_field['mask'] ) ? $gvn_field['mask'] : '';
$f_raw_value     = ( $checkout && method_exists( $checkout, 'get_value' ) ) ? $checkout->get_value( $gvn_field['key'] ) : '';
$f_value         = esc_attr( $f_raw_value );
$width_class     = 'gvn-field--w' . $f_width;
$f_conditions    = isset( $gvn_field['conditions'] ) ? $gvn_field['conditions'] : array( 'logic' => 'and', 'rules' => array() );
$has_conditions  = class_exists( 'GVN_Custom_Fields' ) ? GVN_Custom_Fields::has_conditions( $gvn_field ) : false;
$f_orig_required = $f_required;
?>
<div class="gvn-field <?php echo esc_attr( $width_class ); ?><?php echo $has_conditions ? ' gvn-field--conditional' : ''; ?>" data-field-key="<?php echo $f_key; ?>" data-mask="<?php echo esc_attr( $f_mask ); ?>" aria-hidden="false"<?php if ( $has_conditions ) : ?> data-conditions="<?php echo esc_attr( wp_json_encode( $f_conditions ) ); ?>" data-required="<?php echo $f_orig_required ? '1' : '0'; ?>"<?php endif; ?>>
    <label class="gvn-field__label" for="<?php echo $f_key; ?>">
        <?php echo $f_label; ?>
        <?php if ( $f_required ) : ?><span class="gvn-field__required">*</span><?php endif; ?>
        <?php if ( ! $f_required && 'order_comments' === $f_key ) : ?><span class="gvn-field__optional">(<?php esc_html_e( 'opcional', 'gvn-checkout' ); ?>)</span><?php endif; ?>
    </label>
    <?php if ( 'textarea' === $f_type ) : ?>
        <textarea class="gvn-field__input gvn-field__textarea" name="<?php echo $f_key; ?>" id="<?php echo $f_key; ?>" rows="3" placeholder="<?php echo $f_placeholder; ?>" <?php echo $f_required ? 'required' : ''; ?>><?php echo esc_textarea( $f_raw_value ); ?></textarea>
    <?php elseif ( 'select' === $f_type ) :
        $f_options_raw  = isset( $gvn_field['options'] ) ? $gvn_field['options'] : '';
        $f_options      = class_exists( 'GVN_Custom_Fields' ) ? GVN_Custom_Fields::parse_select_options( $f_options_raw ) : array();
        $f_default_opt  = isset( $gvn_field['default_option'] ) ? $gvn_field['default_option'] : '';
        $f_select_value = '' !== $f_raw_value ? $f_raw_value : $f_default_opt;
    ?>
        <select class="gvn-field__input gvn-field__select" name="<?php echo $f_key; ?>" id="<?php echo $f_key; ?>" <?php echo $f_required ? 'required' : ''; ?>>
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
        <input type="<?php echo esc_attr( $f_type ); ?>" class="gvn-field__input" name="<?php echo $f_key; ?>" id="<?php echo $f_key; ?>" value="<?php echo $f_value; ?>" placeholder="<?php echo $f_placeholder; ?>" <?php echo $f_required ? 'required' : ''; ?> />
    <?php endif; ?>
</div>
