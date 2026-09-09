<?php
/**
 * Gerenciador de campos personalizados do GVN Checkout.
 * CRUD, ordenação e largura dos campos do formulário de checkout.
 *
 * @package GVN_Checkout
 * @version 1.13.40
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use GVN\Checkout\Fields\FieldConditionEvaluator;
use GVN\Checkout\Fields\FieldOrderPersister;
use GVN\Checkout\Fields\FieldSanitizer;
use GVN\Checkout\Fields\FieldSecurityPolicy;
use GVN\Checkout\Fields\FieldValidator;
use GVN\Checkout\Payments\GatewayRequirementsResolver;

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
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_fields_process' ) );
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_custom_fields_after' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'persist_custom_fields_on_order_create' ), 10, 2 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields_to_order' ), 10, 1 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_custom_fields_in_admin_billing' ), 10, 1 );
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_custom_fields_in_admin_shipping' ), 10, 1 );
        add_filter( 'woocommerce_checkout_fields', array( $this, 'register_custom_fields_with_woo' ), 20 );

        // Migração automática dos campos padrão brasileiros (se existirem na config antiga)
        add_action( 'admin_init', array( $this, 'maybe_migrate_default_fields' ) );
    }

    /**
     * Identifica campos relacionados ao endereço para organizar o checkout.
     *
     * A classificação prioriza as chaves nativas do WooCommerce e também
     * reconhece chaves/labels de campos customizados com termos de endereço.
     * Campos de contato/documentação têm prioridade para não classificar
     * "Endereço de e-mail" como endereço físico.
     *
     * @param array $field Configuração do campo.
     * @return bool
     */
    public static function is_address_field( $field ) {
        $key   = isset( $field['key'] ) ? strtolower( trim( $field['key'] ) ) : '';
        $label = isset( $field['label'] ) ? wp_strip_all_tags( $field['label'] ) : '';

        if ( function_exists( 'remove_accents' ) ) {
            $label = remove_accents( $label );
        }

        $label = strtolower( trim( $label ) );

        $address_keys = array(
            'billing_company', 'billing_address_1', 'billing_address_2',
            'billing_city', 'billing_state', 'billing_postcode',
            'billing_country', 'shipping_company', 'shipping_address_1',
            'shipping_address_2', 'shipping_city', 'shipping_state',
            'shipping_postcode', 'shipping_country', 'billing_number',
            'billing_neighborhood', 'shipping_number', 'shipping_neighborhood',
        );

        if ( in_array( $key, $address_keys, true ) ) {
            return true;
        }

        $identity = $key . ' ' . $label;
        $non_address_pattern = '/(email|e-mail|phone|telefone|celular|cpf|cnpj|rg|birth|nascimento|gender|genero|pessoa)/u';
        if ( preg_match( $non_address_pattern, $identity ) ) {
            return false;
        }

        $address_key_pattern = '/(^|_)(address|endereco|logradouro|complemento?|city|cidade|state|estado|postcode|postal|zip|cep|country|pais|bairro|neighborhood|numero|number|rua)(_|$)/u';
        if ( preg_match( $address_key_pattern, $key ) ) {
            return true;
        }

        $address_label_pattern = '/\b(endereco|logradouro|complemento|cidade|estado|postcode|postal|zip|cep|bairro|neighborhood|numero|number|rua|pais)\b/u';
        return (bool) preg_match( $address_label_pattern, $label );
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
                'key'         => 'billing_postcode',
                'label'       => 'CEP',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 13,
                'placeholder' => '00000-000',
                'enabled'     => true,
                'mask'        => 'cep',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_city',
                'label'       => 'Cidade',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 14,
                'placeholder' => 'Cidade',
                'enabled'     => true,
                'mask'        => '',
                'is_default'  => true,
            ),
            array(
                'key'         => 'billing_number',
                'label'       => 'Número',
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
                'key'         => 'billing_neighborhood',
                'label'       => 'Bairro',
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
                'key'         => 'shipping_number',
                'label'       => 'Número (Entrega)',
                'type'        => 'text',
                'required'    => true,
                'width'       => '25',
                'position'    => 17,
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
                'position'    => 18,
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
                'position'    => 19,
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

        // Normaliza cada campo garantindo todos os índices esperados.
        $defaults = array(
            'key'            => '',
            'label'          => '',
            'type'           => 'text',
            'required'       => false,
            'width'          => '100',
            'position'       => 0,
            'placeholder'    => '',
            'enabled'        => true,
            'mask'           => '',
            'is_default'     => false,
            'is_woo_default' => false,
            'options'        => '',
            'default_option' => '',
            'conditions'     => array( 'logic' => 'and', 'rules' => array() ),
        );

        $normalized = array();
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) || empty( $field['key'] ) ) {
                continue;
            }
            $field = array_merge( $defaults, $field );
            $field['conditions'] = self::sanitize_conditions( $field['conditions'] );
            $normalized[] = $field;
        }

        if ( ! in_array( 'billing_postcode', array_column( $normalized, 'key' ), true ) ) {
            foreach ( self::get_default_fields() as $default_field ) {
                if ( 'billing_postcode' === $default_field['key'] ) {
                    $normalized[] = array_merge( $defaults, $default_field );
                    break;
                }
            }
        }

        if ( ! in_array( 'billing_city', array_column( $normalized, 'key' ), true ) ) {
            $max_position = empty( $normalized ) ? 0 : max( array_map( 'intval', array_column( $normalized, 'position' ) ) );

            foreach ( self::get_default_fields() as $default_field ) {
                if ( 'billing_city' === $default_field['key'] ) {
                    $default_field['position'] = $max_position + 1;
                    $normalized[]              = array_merge( $defaults, $default_field );
                    break;
                }
            }
        }

        usort( $normalized, function ( $a, $b ) {
            return intval( $a['position'] ) - intval( $b['position'] );
        } );

        return $normalized;
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

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'gvn-checkout' ) ) );
            return;
        }

        $raw_fields = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : '';
        $fields     = json_decode( $raw_fields, true );

        if ( ! is_array( $fields ) ) {
            wp_send_json_error( array( 'message' => __( 'Dados inválidos.', 'gvn-checkout' ) ) );
            return;
        }

        $valid_types = array_keys( self::get_field_types() );
        $valid_masks = array_keys( self::get_available_masks() );
        $valid_widths = array( '25', '33', '50', '75', '100' );

        $sanitized    = array();
        $seen_keys    = array();

        foreach ( $fields as $index => $field ) {
            $key = FieldSecurityPolicy::sanitize_field_key( isset( $field['key'] ) ? $field['key'] : '' );

            // Pula campos sem chave válida, duplicados ou que colidam com chaves reservadas do pedido.
            if ( '' === $key || isset( $seen_keys[ $key ] ) || FieldSecurityPolicy::is_reserved_key( $key ) ) {
                continue;
            }
            $seen_keys[ $key ] = true;

            $type = isset( $field['type'] ) ? sanitize_text_field( $field['type'] ) : 'text';
            if ( ! FieldSecurityPolicy::is_valid_type( $type ) ) {
                $type = 'text';
            }
            $mask  = isset( $field['mask'] ) ? sanitize_text_field( $field['mask'] ) : '';
            $width = isset( $field['width'] ) ? $field['width'] : '100';

            $options_raw      = isset( $field['options'] ) ? $field['options'] : '';
            $parsed_options   = self::parse_select_options( sanitize_textarea_field( $options_raw ) );
            $default_option   = sanitize_text_field( isset( $field['default_option'] ) ? $field['default_option'] : '' );
            // Só aceita default_option se ele existir nas opções parseadas.
            if ( '' !== $default_option && ! isset( $parsed_options[ $default_option ] ) ) {
                $default_option = '';
            }

            $sanitized[] = array(
                'key'            => $key,
                'label'          => sanitize_text_field( isset( $field['label'] ) ? $field['label'] : '' ),
                'type'           => in_array( $type, $valid_types, true ) ? $type : 'text',
                'required'       => ! empty( $field['required'] ),
                'width'          => in_array( $width, $valid_widths, true ) ? $width : '100',
                'position'       => $index + 1,
                'placeholder'    => sanitize_text_field( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
                'enabled'        => ! empty( $field['enabled'] ),
                'mask'           => in_array( $mask, $valid_masks, true ) ? $mask : '',
                'is_default'     => ! empty( $field['is_default'] ),
                'is_woo_default' => ! empty( $field['is_woo_default'] ),
                'options'        => sanitize_textarea_field( $options_raw ),
                'default_option' => $default_option,
                'conditions'     => self::sanitize_conditions( isset( $field['conditions'] ) ? $field['conditions'] : array() ),
            );
        }

        update_option( self::OPTION_KEY, $sanitized );

        if ( class_exists( 'GVN\Checkout\Settings\SettingsRepository' ) ) {
            \GVN\Checkout\Settings\SettingsRepository::flush_cache();
        }

        wp_send_json_success( array( 'message' => __( 'Campos salvos com sucesso!', 'gvn-checkout' ), 'fields' => $sanitized ) );
        return;
    }

    /**
     * Sanitiza a estrutura de condições de um campo.
     */
    public static function sanitize_conditions( $conditions ) {
        $valid_operators = array( 'equals', 'not_equals', 'filled', 'empty', 'contains', 'greater', 'less' );
        $operator_aliases = array( '==' => 'equals', 'eq' => 'equals', '!=' => 'not_equals', 'neq' => 'not_equals', '>' => 'greater', '<' => 'less' );

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

        $rules = isset( $conditions['rules'] ) && is_array( $conditions['rules'] )
            ? $conditions['rules']
            : ( isset( $conditions[0] ) && is_array( $conditions[0] ) ? $conditions : array() );

        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) || empty( $rule['field'] ) || empty( $rule['operator'] ) ) {
                continue;
            }

            $operator = $operator_aliases[ $rule['operator'] ] ?? $rule['operator'];
            if ( ! in_array( $operator, $valid_operators, true ) ) {
                continue;
            }

            $sanitized['rules'][] = array(
                'field'    => sanitize_key( $rule['field'] ),
                'operator' => $operator,
                'value'    => sanitize_text_field( isset( $rule['value'] ) ? $rule['value'] : '' ),
            );
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
        $posted_data = array();
        if ( isset( $_POST['payment_method'] ) ) {
            $posted_data['payment_method'] = sanitize_key( wp_unslash( $_POST['payment_method'] ) );
        }
        foreach ( array( 'payment_method_variant', 'gateway_variant', 'payment_variant' ) as $context_key ) {
            if ( isset( $_POST[ $context_key ] ) ) {
                $posted_data[ $context_key ] = sanitize_key( wp_unslash( $_POST[ $context_key ] ) );
            }
        }
        $resolver    = new GatewayRequirementsResolver();
        $gateway_id  = $resolver->get_current_gateway_id( $posted_data );

        $width_class_map = array(
            '25'  => array( 'form-row-first' ),
            '33'  => array( 'form-row-first' ),
            '50'  => array( 'form-row-first' ),
            '75'  => array( 'form-row-wide' ),
            '100' => array( 'form-row-wide' ),
        );

        foreach ( $fields as $field ) {
            $key            = $field['key'];
            $is_conditional = self::has_conditions( $field );
            $required       = $is_conditional ? false : ! empty( $field['required'] );
            $width          = isset( $field['width'] ) ? $field['width'] : '100';
            $classes        = isset( $width_class_map[ $width ] ) ? $width_class_map[ $width ] : array( 'form-row-wide' );

            $woo_field = array(
                'type'        => $field['type'],
                'label'       => isset( $field['label'] ) ? $field['label'] : '',
                'required'    => $required,
                'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
                'priority'    => ( isset( $field['position'] ) ? intval( $field['position'] ) : 0 ) * 10,
                'class'       => $classes,
            );

            if ( 'select' === $field['type'] && ! empty( $field['options'] ) ) {
                $parsed = self::parse_select_options( $field['options'] );
                $woo_field['type']    = 'select';
                $woo_field['options'] = array_merge( array( '' => '-- Selecione --' ), $parsed );
                if ( ! empty( $field['default_option'] ) && isset( $parsed[ $field['default_option'] ] ) ) {
                    $woo_field['default'] = $field['default_option'];
                }
            }

            if ( strpos( $key, 'billing_' ) === 0 ) {
                $checkout_fields['billing'][ $key ] = $woo_field;
            } elseif ( strpos( $key, 'shipping_' ) === 0 ) {
                $checkout_fields['shipping'][ $key ] = $woo_field;
            } elseif ( $key !== 'order_comments' ) {
                // Campos custom sem prefixo billing/shipping vão para billing
                // para que o WooCommerce valide e processe no checkout.
                $checkout_fields['billing'][ $key ] = $woo_field;
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
            if ( $resolver->is_field_required( $field_key, $checkout_fields, $gateway_id, $posted_data, false ) ) {
                continue;
            }
            if ( isset( $checkout_fields['billing'][ $field_key ] ) ) {
                $checkout_fields['billing'][ $field_key ]['required'] = false;
                $checkout_fields['billing'][ $field_key ]['class'][]  = 'gvn-hidden-field';
            }
        }

        return $checkout_fields;
    }

    /**
     * Validação server-side de campos durante o submit do checkout via woocommerce_after_checkout_validation.
     *
     * @param array    $data
     * @param WP_Error $errors
     */
    public function validate_custom_fields_after( $data, $errors ) {
        $fields = self::get_enabled_fields();
        $posted = ! empty( $_POST ) ? wp_unslash( $_POST ) : array();
        $merged = array_merge( (array) $data, (array) $posted );
        FieldValidator::validate( $fields, $merged, $errors );
    }

    /**
     * Validação auxiliar em woocommerce_checkout_process.
     */
    public function validate_custom_fields_process() {
        $fields = self::get_enabled_fields();
        $posted = ! empty( $_POST ) ? wp_unslash( $_POST ) : array();
        FieldValidator::validate( $fields, $posted, null );
    }

    /**
     * Persiste campos customizados no objeto WC_Order durante woocommerce_checkout_create_order (atômico e HPOS-safe).
     *
     * @param WC_Order $order
     * @param array    $data
     */
    public function persist_custom_fields_on_order_create( $order, $data ) {
        $fields = self::get_enabled_fields();
        $posted = ! empty( $_POST ) ? wp_unslash( $_POST ) : array();
        $merged = array_merge( (array) $data, (array) $posted );
        FieldOrderPersister::persist( $order, $fields, $merged );
    }

    /**
     * Salva campos custom no pedido via hook legado de fallback woocommerce_checkout_update_order_meta.
     *
     * @param int $order_id
     */
    public function save_custom_fields_to_order( $order_id ) {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        if ( ! $order ) {
            return;
        }

        $fields = self::get_enabled_fields();
        $posted = ! empty( $_POST ) ? wp_unslash( $_POST ) : array();
        $persisted = FieldOrderPersister::persist( $order, $fields, $posted );

        if ( ! empty( $persisted ) ) {
            $order->save();
        }
    }

    /**
     * Exibe campos custom no admin do pedido.
     */
    /**
     * Exibe campos customizados (não nativos) no admin após o endereço de faturamento.
     * Campos shipping_* são exibidos após o endereço de entrega.
     */
    public function display_custom_fields_in_admin_billing( $order ) {
        $this->display_custom_fields_in_admin( $order, 'billing' );
    }

    /**
     * Exibe campos customizados shipping_* no admin após o endereço de entrega.
     */
    public function display_custom_fields_in_admin_shipping( $order ) {
        $this->display_custom_fields_in_admin( $order, 'shipping' );
    }

    /**
     * Exibe campos customizados (não nativos) no admin.
     *
     * @param WC_Order $order
     * @param string   $scope 'billing' ou 'shipping'. Se 'shipping', exibe apenas campos shipping_*.
     *                        Se 'billing', exibe campos que NÃO são shipping_*.
     */
    private function display_custom_fields_in_admin( $order, $scope = 'billing' ) {
        $fields = self::get_enabled_fields();

        // Lista de campos nativos do WooCommerce que já são exibidos pelo Woo.
        $woo_native_keys = array(
            'billing_first_name', 'billing_last_name', 'billing_email',
            'billing_phone', 'billing_company', 'billing_address_1',
            'billing_address_2', 'billing_city', 'billing_state',
            'billing_postcode', 'billing_country', 'order_comments',
            'shipping_first_name', 'shipping_last_name', 'shipping_company',
            'shipping_address_1', 'shipping_address_2', 'shipping_city',
            'shipping_state', 'shipping_postcode', 'shipping_country',
        );

        foreach ( $fields as $field ) {
            $key = $field['key'];

            if ( in_array( $key, $woo_native_keys, true ) ) {
                continue;
            }

            // Filtra por scope: shipping_* só aparece no hook de shipping.
            $is_shipping_field = ( strpos( $key, 'shipping_' ) === 0 );

            if ( 'shipping' === $scope && ! $is_shipping_field ) {
                continue;
            }
            if ( 'billing' === $scope && $is_shipping_field ) {
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
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

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
            'billing_postcode' => array(
                'label'       => 'CEP',
                'type'        => 'text',
                'mask'        => 'cep',
                'placeholder' => '00000-000',
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
            } elseif ( strpos( $line, ' : ' ) !== false ) {
                list( $value, $label ) = array_map( 'trim', explode( ' : ', $line, 2 ) );
            } else {
                $value = sanitize_title( $line );
                $label = $line;
                // Fallback: se sanitize_title gerar vazio (emoji/acentos estranhos),
                // usa um hash curto do label para garantir unicidade.
                if ( '' === $value ) {
                    $value = 'opt_' . substr( md5( $line ), 0, 8 );
                }
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
