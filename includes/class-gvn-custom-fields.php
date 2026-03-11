<?php
/**
 * Gerenciador de campos personalizados do GVN Checkout.
 * CRUD, ordenação e largura dos campos do formulário de checkout.
 *
 * @package GVN_Checkout
 * @version 1.12.0
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

        // Migração automática dos campos padrão brasileiros (se existirem na config antiga)
        add_action( 'admin_init', array( $this, 'maybe_migrate_default_fields' ) );
    }

    /**
     * Campos padrão que vêm pré-configurados (inclui campos brasileiros).
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
                'key'         => 'billing_persontype',
                'label'       => 'Tipo de Pessoa',
                'type'        => 'select',
                'required'    => false,
                'width'       => '100',
                'position'    => 3,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => '',
                'is_default'  => true,
                'options'     => "pf|Pessoa Física\npj|Pessoa Jurídica",
            ),
            array(
                'key'         => 'billing_cpf',
                'label'       => 'CPF',
                'type'        => 'text',
                'required'    => true,
                'width'       => '100',
                'position'    => 4,
                'placeholder' => '000.000.000-00',
                'enabled'     => true,
                'mask'        => 'cpf',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_rg',
                'label'       => 'RG',
                'type'        => 'text',
                'required'    => false,
                'width'       => '50',
                'position'    => 5,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => 'rg',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_cnpj',
                'label'       => 'CNPJ',
                'type'        => 'text',
                'required'    => false,
                'width'       => '100',
                'position'    => 6,
                'placeholder' => '00.000.000/0000-00',
                'enabled'     => false,
                'mask'        => 'cnpj',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_ie',
                'label'       => 'Inscrição Estadual',
                'type'        => 'text',
                'required'    => false,
                'width'       => '50',
                'position'    => 7,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_phone',
                'label'       => 'Celular',
                'type'        => 'tel',
                'required'    => true,
                'width'       => '50',
                'position'    => 8,
                'placeholder' => '(00) 00000-0000',
                'enabled'     => true,
                'mask'        => 'phone',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_cellphone',
                'label'       => 'Celular (Adicional)',
                'type'        => 'tel',
                'required'    => false,
                'width'       => '50',
                'position'    => 9,
                'placeholder' => '(00) 00000-0000',
                'enabled'     => false,
                'mask'        => 'phone',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_email',
                'label'       => 'Endereço de e-mail',
                'type'        => 'email',
                'required'    => true,
                'width'       => '50',
                'position'    => 10,
                'placeholder' => '',
                'enabled'     => true,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_birthdate',
                'label'       => 'Data de Nascimento',
                'type'        => 'text',
                'required'    => false,
                'width'       => '50',
                'position'    => 11,
                'placeholder' => 'DD/MM/AAAA',
                'enabled'     => false,
                'mask'        => 'date',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_gender',
                'label'       => 'Gênero',
                'type'        => 'select',
                'required'    => false,
                'width'       => '50',
                'position'    => 12,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => '',
                'is_default'  => true,
                'options'     => "prefiro_nao_dizer|Prefiro não dizer\nfeminino|Feminino\nmasculino|Masculino\noutro|Outro",
            ),
            array(
                'key'         => 'billing_number',
                'label'       => 'Número',
                'type'        => 'text',
                'required'    => true,
                'width'       => '25',
                'position'    => 13,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_neighborhood',
                'label'       => 'Bairro',
                'type'        => 'text',
                'required'    => false,
                'width'       => '50',
                'position'    => 14,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'shipping_number',
                'label'       => 'Número (Entrega)',
                'type'        => 'text',
                'required'    => true,
                'width'       => '25',
                'position'    => 15,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'shipping_neighborhood',
                'label'       => 'Bairro (Entrega)',
                'type'        => 'text',
                'required'    => false,
                'width'       => '50',
                'position'    => 16,
                'placeholder' => '',
                'enabled'     => false,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'order_comments',
                'label'       => 'Observações do pedido',
                'type'        => 'textarea',
                'required'    => false,
                'width'       => '100',
                'position'    => 17,
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
                'is_woo_default' => ! empty( $field['is_woo_default'] ),
                'options'        => sanitize_textarea_field( isset( $field['options'] ) ? $field['options'] : '' ),
                'default_option' => sanitize_text_field( isset( $field['default_option'] ) ? $field['default_option'] : '' ),
                'conditions'     => self::sanitize_conditions( isset( $field['conditions'] ) ? $field['conditions'] : array() ),
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
                $woo_field = array(
                    'type'        => $field['type'],
                    'label'       => $field['label'],
                    'required'    => $required,
                    'placeholder' => $field['placeholder'],
                    'priority'    => $field['position'] * 10,
                    'class'       => array( 'form-row-wide' ),
                );

                if ( 'select' === $field['type'] && ! empty( $field['options'] ) ) {
                    $parsed = self::parse_select_options( $field['options'] );
                    $woo_field['type']    = 'select';
                    $woo_field['options'] = array_merge( array( '' => '-- Selecione --' ), $parsed );
                }

                $checkout_fields['billing'][ $key ] = $woo_field;
            } elseif ( strpos( $key, 'shipping_' ) === 0 ) {
                $woo_field = array(
                    'type'        => $field['type'],
                    'label'       => $field['label'],
                    'required'    => $required,
                    'placeholder' => $field['placeholder'],
                    'priority'    => $field['position'] * 10,
                    'class'       => array( 'form-row-wide' ),
                );

                $checkout_fields['shipping'][ $key ] = $woo_field;
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

        // Não ocultar campos que foram adicionados explicitamente pelo admin
        $enabled_keys = wp_list_pluck( $fields, 'key' );
        foreach ( $hide_fields as $field_key ) {
            if ( in_array( $field_key, $enabled_keys, true ) ) {
                continue; // O admin adicionou este campo, não ocultar
            }
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

        // Lista de campos nativos do WooCommerce que ele já salva sozinho
        $woo_native_keys = array(
            'billing_first_name', 'billing_last_name', 'billing_email',
            'billing_phone', 'billing_company', 'billing_address_1',
            'billing_address_2', 'billing_city', 'billing_state',
            'billing_postcode', 'billing_country',
        );

        foreach ( $fields as $field ) {
            $key = $field['key'];

            // Pula campos nativos do WooCommerce (ele já salva)
            if ( in_array( $key, $woo_native_keys, true ) ) {
                continue;
            }

            // Pula order_comments (Woo já salva)
            if ( 'order_comments' === $key ) {
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

        // Lista de campos nativos do WooCommerce que já são exibidos pelo Woo
        $woo_native_keys = array(
            'billing_first_name', 'billing_last_name', 'billing_email',
            'billing_phone', 'billing_company', 'billing_address_1',
            'billing_address_2', 'billing_city', 'billing_state',
            'billing_postcode', 'billing_country', 'order_comments',
        );

        foreach ( $fields as $field ) {
            $key = $field['key'];

            if ( in_array( $key, $woo_native_keys, true ) ) {
                continue;
            }

            $value = $order->get_meta( '_' . $key );
            if ( $value ) {
                echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
    }

    /**
     * Migra campos da aba "Campos Padrão" antiga para a lista unificada.
     * Executa apenas uma vez, quando detecta a opção antiga.
     */
    public function maybe_migrate_default_fields() {
        $old_config = get_option( 'gvn_checkout_default_fields_config', false );

        if ( false === $old_config || ! is_array( $old_config ) || empty( $old_config ) ) {
            return; // Nada a migrar
        }

        $current_fields = get_option( self::OPTION_KEY, false );

        if ( false === $current_fields || ! is_array( $current_fields ) || empty( $current_fields ) ) {
            $current_fields = self::get_default_fields();
        }

        // Coletar keys existentes
        $existing_keys = array();
        foreach ( $current_fields as $f ) {
            $existing_keys[] = $f['key'];
        }

        // Definições dos campos brasileiros para referência
        $brazilian_defs = self::get_brazilian_field_definitions();

        $max_position = 0;
        foreach ( $current_fields as $f ) {
            if ( intval( $f['position'] ) > $max_position ) {
                $max_position = intval( $f['position'] );
            }
        }

        $added = false;
        foreach ( $old_config as $key => $config ) {
            if ( in_array( $key, $existing_keys, true ) ) {
                // Campo já existe; atualizar enabled/required se estava ativo na config antiga
                if ( ! empty( $config['enabled'] ) ) {
                    foreach ( $current_fields as &$cf ) {
                        if ( $cf['key'] === $key ) {
                            $cf['enabled']  = true;
                            $cf['required'] = ! empty( $config['required'] );
                            break;
                        }
                    }
                    unset( $cf );
                    $added = true;
                }
                continue;
            }

            // Campo não existe — adicionar se estava ativo
            if ( ! empty( $config['enabled'] ) && isset( $brazilian_defs[ $key ] ) ) {
                $def = $brazilian_defs[ $key ];
                $max_position++;
                $current_fields[] = array(
                    'key'         => $key,
                    'label'       => $def['label'],
                    'type'        => $def['type'],
                    'required'    => ! empty( $config['required'] ),
                    'width'       => '50',
                    'position'    => $max_position,
                    'placeholder' => isset( $def['placeholder'] ) ? $def['placeholder'] : '',
                    'enabled'     => true,
                    'mask'        => isset( $def['mask'] ) ? $def['mask'] : '',
                    'is_default'  => true,
                    'options'     => isset( $def['options'] ) ? $def['options'] : '',
                );
                $added = true;
            }
        }

        if ( $added ) {
            update_option( self::OPTION_KEY, $current_fields );
        }

        // Remove a opção antiga para não migrar novamente
        delete_option( 'gvn_checkout_default_fields_config' );
    }

    /**
     * Definições dos campos brasileiros para uso na migração.
     */
    private static function get_brazilian_field_definitions() {
        return array(
            'billing_persontype' => array(
                'label'   => 'Tipo de Pessoa',
                'type'    => 'select',
                'mask'    => '',
                'options' => "pf|Pessoa Física\npj|Pessoa Jurídica",
            ),
            'billing_cpf' => array(
                'label'       => 'CPF',
                'type'        => 'text',
                'mask'        => 'cpf',
                'placeholder' => '000.000.000-00',
            ),
            'billing_rg' => array(
                'label' => 'RG',
                'type'  => 'text',
                'mask'  => 'rg',
            ),
            'billing_cnpj' => array(
                'label'       => 'CNPJ',
                'type'        => 'text',
                'mask'        => 'cnpj',
                'placeholder' => '00.000.000/0000-00',
            ),
            'billing_ie' => array(
                'label' => 'Inscrição Estadual',
                'type'  => 'text',
            ),
            'billing_birthdate' => array(
                'label'       => 'Data de Nascimento',
                'type'        => 'text',
                'mask'        => 'date',
                'placeholder' => 'DD/MM/AAAA',
            ),
            'billing_gender' => array(
                'label'   => 'Gênero',
                'type'    => 'select',
                'options' => "prefiro_nao_dizer|Prefiro não dizer\nfeminino|Feminino\nmasculino|Masculino\noutro|Outro",
            ),
            'billing_number' => array(
                'label' => 'Número',
                'type'  => 'text',
            ),
            'billing_neighborhood' => array(
                'label' => 'Bairro',
                'type'  => 'text',
            ),
            'billing_cellphone' => array(
                'label'       => 'Celular (Adicional)',
                'type'        => 'tel',
                'mask'        => 'phone',
                'placeholder' => '(00) 00000-0000',
            ),
            'shipping_number' => array(
                'label' => 'Número (Entrega)',
                'type'  => 'text',
            ),
            'shipping_neighborhood' => array(
                'label' => 'Bairro (Entrega)',
                'type'  => 'text',
            ),
        );
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
