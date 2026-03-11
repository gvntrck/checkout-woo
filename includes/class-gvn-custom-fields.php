<?php
/**
 * Gerenciador de campos personalizados do GVN Checkout.
 * CRUD, ordenação e largura dos campos do formulário de checkout.
 *
 * @package GVN_Checkout
 * @version 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GVN_Custom_Fields {

    private static $instance = null;

    const OPTION_KEY = 'gvn_checkout_fields';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_gvn_save_fields', array( $this, 'ajax_save_fields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields_to_order' ), 10, 1 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_custom_fields_in_admin' ), 10, 1 );
        add_filter( 'woocommerce_checkout_fields', array( $this, 'register_custom_fields_with_woo' ), 20 );
    }

    /**
     * Campos padrão que vêm pré-configurados.
     */
    public static function get_default_fields() {
        return array(
            array(
                'key'         => 'billing_first_name',
                'label'       => 'Nome',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 1,
                'placeholder' => '',
                'enabled'     => true,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_last_name',
                'label'       => 'Sobrenome',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 2,
                'placeholder' => '',
                'enabled'     => true,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_cpf',
                'label'       => 'CPF',
                'type'        => 'text',
                'required'    => true,
                'width'       => '100',
                'position'    => 3,
                'placeholder' => '000.000.000-00',
                'enabled'     => true,
                'mask'        => 'cpf',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_phone',
                'label'       => 'Celular',
                'type'        => 'tel',
                'required'    => true,
                'width'       => '50',
                'position'    => 4,
                'placeholder' => '(00) 00000-0000',
                'enabled'     => true,
                'mask'        => 'phone',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_email',
                'label'       => 'Endereço de e-mail',
                'type'        => 'email',
                'required'    => true,
                'width'       => '50',
                'position'    => 5,
                'placeholder' => '',
                'enabled'     => true,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'order_comments',
                'label'       => 'Observações do pedido',
                'type'        => 'textarea',
                'required'    => false,
                'width'       => '100',
                'position'    => 6,
                'placeholder' => 'Observações sobre seu pedido, ex.: observações especiais sobre entrega.',
                'enabled'     => true,
                'mask'        => '',
                'is_default'  => true,
            ),
        );
    }

    /**
     * Retorna os campos configurados (ou padrão se nunca salvou).
     */
    public static function get_fields() {
        $fields = get_option( self::OPTION_KEY, false );

        if ( false === $fields || ! is_array( $fields ) || empty( $fields ) ) {
            return self::get_default_fields();
        }

        usort( $fields, function ( $a, $b ) {
            return intval( $a['position'] ) - intval( $b['position'] );
        } );

        return $fields;
    }

    /**
     * Retorna apenas os campos habilitados e ordenados.
     */
    public static function get_enabled_fields() {
        return array_filter( self::get_fields(), function ( $field ) {
            return ! empty( $field['enabled'] );
        } );
    }

    /**
     * AJAX: Salvar campos do admin.
     */
    public function ajax_save_fields() {
        check_ajax_referer( 'gvn_admin_fields_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ) );
        }

        $raw_fields = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : '';
        $fields     = json_decode( $raw_fields, true );

        if ( ! is_array( $fields ) ) {
            wp_send_json_error( array( 'message' => 'Dados inválidos.' ) );
        }

        $sanitized = array();

        foreach ( $fields as $index => $field ) {
            $sanitized[] = array(
                'key'         => sanitize_key( $field['key'] ),
                'label'       => sanitize_text_field( $field['label'] ),
                'type'        => sanitize_text_field( $field['type'] ),
                'required'    => ! empty( $field['required'] ),
                'width'       => in_array( $field['width'], array( '25', '33', '50', '75', '100' ), true ) ? $field['width'] : '100',
                'position'    => $index + 1,
                'placeholder' => sanitize_text_field( $field['placeholder'] ),
                'enabled'     => ! empty( $field['enabled'] ),
                'mask'        => sanitize_text_field( $field['mask'] ),
                'is_default'  => ! empty( $field['is_default'] ),
                'options'     => sanitize_textarea_field( isset( $field['options'] ) ? $field['options'] : '' ),
                'conditions'  => self::sanitize_conditions( isset( $field['conditions'] ) ? $field['conditions'] : array() ),
            );
        }

        update_option( self::OPTION_KEY, $sanitized );

        wp_send_json_success( array( 'message' => 'Campos salvos com sucesso!', 'fields' => $sanitized ) );
    }

    /**
     * Sanitiza a estrutura de condições de um campo.
     */
    public static function sanitize_conditions( $conditions ) {
        $valid_operators = array( 'equals', 'not_equals', 'filled', 'empty', 'contains', 'greater', 'less' );

        $sanitized = array(
            'logic' => 'and',
            'rules' => array(),
        );

        if ( ! is_array( $conditions ) ) {
            return $sanitized;
        }

        if ( isset( $conditions['logic'] ) && in_array( $conditions['logic'], array( 'and', 'or' ), true ) ) {
            $sanitized['logic'] = $conditions['logic'];
        }

        if ( isset( $conditions['rules'] ) && is_array( $conditions['rules'] ) ) {
            foreach ( $conditions['rules'] as $rule ) {
                if ( ! is_array( $rule ) || empty( $rule['field'] ) || empty( $rule['operator'] ) ) {
                    continue;
                }

                if ( ! in_array( $rule['operator'], $valid_operators, true ) ) {
                    continue;
                }

                $sanitized['rules'][] = array(
                    'field'    => sanitize_key( $rule['field'] ),
                    'operator' => sanitize_key( $rule['operator'] ),
                    'value'    => sanitize_text_field( isset( $rule['value'] ) ? $rule['value'] : '' ),
                );
            }
        }

        return $sanitized;
    }

    /**
     * Verifica se um campo tem condições configuradas.
     */
    public static function has_conditions( $field ) {
        return ! empty( $field['conditions'] )
            && ! empty( $field['conditions']['rules'] )
            && is_array( $field['conditions']['rules'] )
            && count( $field['conditions']['rules'] ) > 0;
    }

    /**
     * Registra campos personalizados no WooCommerce para validação.
     * Campos com condições são registrados como NÃO obrigatórios no WooCommerce
     * (a obrigatoriedade é controlada via JS no frontend).
     */
    public function register_custom_fields_with_woo( $checkout_fields ) {
        $fields = self::get_enabled_fields();

        foreach ( $fields as $field ) {
            $key = $field['key'];
            $is_conditional = self::has_conditions( $field );
            $required = $is_conditional ? false : $field['required'];

            if ( strpos( $key, 'billing_' ) === 0 ) {
                $checkout_fields['billing'][ $key ] = array(
                    'type'        => $field['type'],
                    'label'       => $field['label'],
                    'required'    => $required,
                    'placeholder' => $field['placeholder'],
                    'priority'    => $field['position'] * 10,
                    'class'       => array( 'form-row-wide' ),
                );
            } elseif ( $key !== 'order_comments' && strpos( $key, 'gvn_' ) === 0 ) {
                $checkout_fields['billing'][ $key ] = array(
                    'type'        => $field['type'],
                    'label'       => $field['label'],
                    'required'    => $required,
                    'placeholder' => $field['placeholder'],
                    'priority'    => $field['position'] * 10,
                    'class'       => array( 'form-row-wide' ),
                );
            }
        }

        $hide_fields = array(
            'billing_company', 'billing_address_1', 'billing_address_2',
            'billing_city', 'billing_postcode', 'billing_country', 'billing_state',
        );
        foreach ( $hide_fields as $field_key ) {
            if ( isset( $checkout_fields['billing'][ $field_key ] ) ) {
                $checkout_fields['billing'][ $field_key ]['required'] = false;
                $checkout_fields['billing'][ $field_key ]['class'][]  = 'gvn-hidden-field';
            }
        }

        return $checkout_fields;
    }

    /**
     * Salva campos custom no pedido.
     */
    public function save_custom_fields_to_order( $order_id ) {
        $fields = self::get_enabled_fields();

        foreach ( $fields as $field ) {
            $key = $field['key'];

            if ( $field['is_default'] && $key !== 'billing_cpf' ) {
                continue;
            }

            if ( isset( $_POST[ $key ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                update_post_meta( $order_id, '_' . $key, $value );
            }
        }
    }

    /**
     * Exibe campos custom no admin do pedido.
     */
    public function display_custom_fields_in_admin( $order ) {
        $fields = self::get_enabled_fields();

        foreach ( $fields as $field ) {
            $key = $field['key'];

            if ( $field['is_default'] && $key !== 'billing_cpf' ) {
                continue;
            }

            $value = $order->get_meta( '_' . $key );
            if ( $value ) {
                echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
    }

    /**
     * Converte a string de opções (uma por linha) em array associativo.
     * Formato: "valor|Rótulo" ou apenas "Rótulo" (valor = sanitize do rótulo).
     */
    public static function parse_select_options( $options_string ) {
        $options = array();

        if ( empty( $options_string ) ) {
            return $options;
        }

        $lines = array_filter( array_map( 'trim', explode( "\n", $options_string ) ) );

        foreach ( $lines as $line ) {
            if ( strpos( $line, '|' ) !== false ) {
                list( $value, $label ) = array_map( 'trim', explode( '|', $line, 2 ) );
            } else {
                $value = sanitize_title( $line );
                $label = $line;
            }

            if ( '' !== $value && '' !== $label ) {
                $options[ $value ] = $label;
            }
        }

        return $options;
    }

    /**
     * Tipos de campo disponíveis.
     */
    public static function get_field_types() {
        return array(
            'text'     => 'Texto',
            'email'    => 'E-mail',
            'tel'      => 'Telefone',
            'number'   => 'Número',
            'textarea' => 'Área de texto',
            'select'   => 'Seleção',
            'date'     => 'Data',
            'password' => 'Senha',
        );
    }

    /**
     * Máscaras disponíveis.
     */
    public static function get_available_masks() {
        return array(
            ''        => 'Nenhuma',
            'cpf'     => 'CPF (000.000.000-00)',
            'cnpj'    => 'CNPJ (00.000.000/0000-00)',
            'phone'   => 'Celular ((00) 00000-0000)',
            'cep'     => 'CEP (00000-000)',
            'date'    => 'Data (00/00/0000)',
            'rg'      => 'RG (00.000.000-0)',
        );
    }
}
