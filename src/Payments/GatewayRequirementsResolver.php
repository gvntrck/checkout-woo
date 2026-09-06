<?php

namespace GVN\Checkout\Payments;

use GVN\Checkout\Fields\FieldConditionEvaluator;

/**
 * Normaliza os requisitos de checkout conhecidos pelos gateways ativos.
 *
 * Gateways de terceiros podem declarar requisitos através do filtro
 * `gvn_checkout_gateway_requirements`. O resolver nunca tenta executar
 * validadores de pagamento nem infere campos a partir de JavaScript arbitrário.
 */
class GatewayRequirementsResolver {

    const FILTER = 'gvn_checkout_gateway_requirements';

    const REQUIREMENT_REQUIRED = 'required';

    const REQUIREMENT_PRESENT = 'present';

    const SOURCE_WOOCOMMERCE = 'woocommerce';

    const SOURCE_ADAPTER = 'adapter';

    const SOURCE_EXTENSION = 'extension';

    const CONFIDENCE_CONFIRMED = 'confirmed';

    const CONFIDENCE_UNKNOWN = 'unknown';

    /**
     * Retorna o relatório pronto para o painel administrativo.
     *
     * @param array|null $checkout_fields Campos efetivos do checkout.
     * @param array|null $configured_fields Campos configurados pelo GVN.
     * @param array|null $gateways Gateways a considerar.
     * @return array
     */
    public function get_report( $checkout_fields = null, $configured_fields = null, $gateways = null ) {
        $checkout_fields  = is_array( $checkout_fields ) ? $checkout_fields : $this->get_checkout_fields();
        $configured_fields = is_array( $configured_fields ) ? $configured_fields : $this->get_configured_fields();
        $gateways         = is_array( $gateways ) ? $gateways : $this->get_gateways();
        $base_requirements = $this->get_base_requirements( $checkout_fields );
        $report_gateways   = array();

        foreach ( $gateways as $gateway_key => $gateway ) {
            $metadata = $this->normalize_gateway( $gateway_key, $gateway );
            if ( '' === $metadata['id'] ) {
                continue;
            }

            $requirements = $this->get_requirements_for_gateway(
                $metadata['id'],
                $metadata['object'],
                $base_requirements,
                array()
            );
            $has_specific_declaration = false;
            foreach ( $requirements as $requirement ) {
                if ( self::SOURCE_WOOCOMMERCE !== $requirement['source'] ) {
                    $has_specific_declaration = true;
                    break;
                }
            }

            $normalized_requirements = array();
            foreach ( $requirements as $requirement ) {
                $normalized_requirements[] = $this->decorate_requirement(
                    $requirement,
                    $configured_fields,
                    $checkout_fields
                );
            }

            $specific_requirements = array_values(
                array_filter(
                    $normalized_requirements,
                    function ( $item ) {
                        return self::SOURCE_WOOCOMMERCE !== $item['source'];
                    }
                )
            );
            $general_requirements = array_values(
                array_filter(
                    $normalized_requirements,
                    function ( $item ) {
                        return self::SOURCE_WOOCOMMERCE === $item['source'];
                    }
                )
            );

            $report_gateways[] = array(
                'id'             => $metadata['id'],
                'title'          => $metadata['title'],
                'description'    => $metadata['description'],
                'plugin_id'      => $metadata['plugin_id'],
                'plugin_title'   => $metadata['plugin_title'],
                'plugin_source'  => $metadata['plugin_source'],
                'active'         => $metadata['active'],
                'has_fields'     => $metadata['has_fields'],
                'declaration'    => $has_specific_declaration ? 'declared' : 'not_declared',
                'requirements'   => $normalized_requirements,
                'specific_requirements' => $specific_requirements,
                'general_requirements'  => $general_requirements,
                'specific_count' => count( $specific_requirements ),
                'general_count'  => count( $general_requirements ),
                'conflict_count' => count(
                    array_filter(
                        $normalized_requirements,
                        function ( $item ) {
                            return 'ok' !== $item['status'];
                        }
                    )
                ),
            );
        }

        return array(
            'gateways' => $report_gateways,
        );
    }

    /**
     * Retorna apenas as chaves confirmadas por gateway para a proteção do JS.
     * Nenhum valor submetido ou dado de cliente é exposto nessa estrutura.
     *
     * @return array<string, string[]>
     */
    public function get_frontend_requirements() {
        $requirements_by_gateway = array();
        foreach ( $this->get_gateways() as $gateway_key => $gateway ) {
            $metadata = $this->normalize_gateway( $gateway_key, $gateway );
            if ( '' === $metadata['id'] ) {
                continue;
            }

            $requirements = $this->get_requirements_for_gateway(
                $metadata['id'],
                $metadata['object'],
                array(),
                array( 'payment_method' => $metadata['id'] )
            );
            $requirements_by_gateway[ $metadata['id'] ] = array_values(
                array_map(
                    function ( $requirement ) {
                        return array(
                            'field_key'  => $requirement['field_key'],
                            'variant'    => $requirement['variant'],
                            'when'       => $requirement['when'],
                        );
                    },
                    array_filter(
                        $requirements,
                        function ( $requirement ) {
                            return self::SOURCE_WOOCOMMERCE !== $requirement['source']
                                && self::CONFIDENCE_CONFIRMED === $requirement['confidence'];
                        }
                    )
                )
            );
        }

        return $requirements_by_gateway;
    }

    /**
     * Verifica se um campo deve permanecer visível para o método atual.
     *
     * @param string     $field_key
     * @param array      $checkout_fields
     * @param string|null $gateway_id
     * @param array      $posted_data
     * @param bool       $include_base Whether WooCommerce's global required fields count.
     * @return bool
     */
    public function is_field_required( $field_key, $checkout_fields = array(), $gateway_id = null, $posted_data = array(), $include_base = true ) {
        $field_key = sanitize_key( $field_key );
        if ( '' === $field_key ) {
            return false;
        }

        if ( $include_base ) {
            $base_requirements = $this->get_base_requirements( (array) $checkout_fields );
            foreach ( $base_requirements as $requirement ) {
                if ( $field_key === $requirement['field_key'] && self::REQUIREMENT_REQUIRED === $requirement['requirement'] ) {
                    return true;
                }
            }
        }

        if ( empty( $gateway_id ) ) {
            $gateway_id = $this->get_current_gateway_id( $posted_data );
        }
        if ( empty( $gateway_id ) ) {
            return false;
        }

        $active_requirements = $this->get_active_requirements_for_gateway(
            sanitize_key( $gateway_id ),
            $posted_data,
            $checkout_fields,
            $include_base
        );

        foreach ( $active_requirements as $requirement ) {
            if (
                $field_key === $requirement['field_key']
                && self::CONFIDENCE_CONFIRMED === $requirement['confidence']
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna requisitos confirmados aplicáveis a um gateway e submissão.
     *
     * @param string $gateway_id
     * @param array  $posted_data
     * @param array  $checkout_fields
     * @param bool   $include_base Whether global WooCommerce requirements count.
     * @return array
     */
    public function get_active_requirements_for_gateway( $gateway_id, $posted_data = array(), $checkout_fields = array(), $include_base = true ) {
        $gateway_id = sanitize_key( $gateway_id );
        if ( '' === $gateway_id ) {
            return array();
        }

        $gateway     = $this->find_gateway( $gateway_id );
        $base        = $include_base ? $this->get_base_requirements( (array) $checkout_fields ) : array();
        $posted_data = $this->sanitize_context( $posted_data );
        $requirements = $this->get_requirements_for_gateway( $gateway_id, $gateway, $base, $posted_data );

        return array_values(
            array_filter(
                $requirements,
                function ( $requirement ) use ( $posted_data, $gateway_id ) {
                    if ( self::CONFIDENCE_CONFIRMED !== $requirement['confidence'] ) {
                        return false;
                    }

                    if ( ! empty( $requirement['variant'] ) ) {
                        $active_variant = $this->get_active_variant( $gateway_id, $posted_data );
                        if ( '' !== $active_variant && $active_variant !== $requirement['variant'] ) {
                            return false;
                        }
                        if ( '' === $active_variant && empty( $requirement['when'] ) ) {
                            return false;
                        }
                    }

                    if ( empty( $requirement['when'] ) ) {
                        return true;
                    }

                    return FieldConditionEvaluator::evaluate( $requirement['when'], $posted_data );
                }
            )
        );
    }

    /**
     * Retorna o método escolhido no POST, na sessão ou o primeiro disponível.
     *
     * @param array $posted_data
     * @return string
     */
    public function get_current_gateway_id( $posted_data = array() ) {
        $posted_data = is_array( $posted_data ) ? $posted_data : array();
        if ( ! empty( $posted_data['payment_method'] ) ) {
            return sanitize_key( $posted_data['payment_method'] );
        }

        if ( isset( $_POST['payment_method'] ) ) {
            return sanitize_key( wp_unslash( $_POST['payment_method'] ) );
        }

        if ( function_exists( 'WC' ) && WC() ) {
            $session = isset( WC()->session ) ? WC()->session : null;
            if ( $session && method_exists( $session, 'get' ) ) {
                $chosen = $session->get( 'chosen_payment_method' );
                if ( ! empty( $chosen ) ) {
                    return sanitize_key( $chosen );
                }
            }
        }

        $gateways = $this->get_gateways();
        foreach ( $gateways as $key => $gateway ) {
            $metadata = $this->normalize_gateway( $key, $gateway );
            if ( $metadata['active'] && '' !== $metadata['id'] ) {
                return $metadata['id'];
            }
        }

        return '';
    }

    /**
     * Normaliza os campos obrigatórios registrados pelo WooCommerce.
     *
     * @param array $checkout_fields
     * @return array
     */
    public function get_base_requirements( $checkout_fields ) {
        $requirements = array();
        foreach ( (array) $checkout_fields as $section => $section_fields ) {
            if ( ! is_array( $section_fields ) ) {
                continue;
            }

            foreach ( $section_fields as $field_key => $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }

                $key = sanitize_key( isset( $field['key'] ) ? $field['key'] : $field_key );
                if ( '' === $key || empty( $field['required'] ) ) {
                    continue;
                }

                $requirements[] = array(
                    'gateway_id' => '',
                    'variant'    => null,
                    'field_key'  => $key,
                    'requirement' => self::REQUIREMENT_REQUIRED,
                    'source'     => self::SOURCE_WOOCOMMERCE,
                    'confidence' => self::CONFIDENCE_CONFIRMED,
                    'message'    => __( 'Campo marcado como obrigatório pelo WooCommerce.', 'gvn-checkout' ),
                    'when'       => array(),
                );
            }
        }

        return $this->deduplicate_requirements( $requirements );
    }

    /**
     * Permite que integrações externas informem requisitos por gateway.
     *
     * @param string $gateway_id
     * @param object|array|null $gateway
     * @param array  $context
     * @return array
     */
    private function get_extension_requirements( $gateway_id, $gateway, $context ) {
        $raw = apply_filters(
            self::FILTER,
            array(),
            $gateway_id,
            $gateway,
            $context
        );

        if ( ! is_array( $raw ) ) {
            return array();
        }

        $requirements = array();
        foreach ( $raw as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            // O filtro é uma API pública: campos e enums obrigatórios devem
            // estar presentes para que uma declaração incompleta jamais bloqueie
            // o checkout por acidente.
            if (
                ! array_key_exists( 'field_key', $item )
                || ! array_key_exists( 'requirement', $item )
                || ! array_key_exists( 'confidence', $item )
                || ! array_key_exists( 'source', $item )
                || ! is_scalar( $item['field_key'] )
                || ! is_scalar( $item['requirement'] )
                || ! is_scalar( $item['confidence'] )
                || ! is_scalar( $item['source'] )
            ) {
                continue;
            }

            $field_key = sanitize_key( $item['field_key'] );
            $requirement = sanitize_key( $item['requirement'] );
            if ( ! in_array( $requirement, array( self::REQUIREMENT_REQUIRED, self::REQUIREMENT_PRESENT ), true ) ) {
                continue;
            }

            $confidence = sanitize_key( $item['confidence'] );
            if ( ! in_array( $confidence, array( self::CONFIDENCE_CONFIRMED, self::CONFIDENCE_UNKNOWN ), true ) ) {
                continue;
            }

            $source = sanitize_key( $item['source'] );
            if ( ! in_array( $source, array( self::SOURCE_ADAPTER, self::SOURCE_EXTENSION ), true ) ) {
                continue;
            }

            if ( isset( $item['when'] ) && ! is_array( $item['when'] ) ) {
                continue;
            }
            if (
                ( isset( $item['variant'] ) && ! is_scalar( $item['variant'] ) )
                || ( isset( $item['message'] ) && ! is_scalar( $item['message'] ) )
            ) {
                continue;
            }
            $when = isset( $item['when'] ) ? $this->sanitize_conditions( $item['when'] ) : array();
            if ( null === $when || ( isset( $item['when'] ) && empty( $when['rules'] ) && ! empty( $item['when'] ) ) ) {
                continue;
            }

            if ( '' === $field_key ) {
                continue;
            }

            $requirements[] = array(
                'gateway_id' => $gateway_id,
                'variant'    => empty( $item['variant'] ) ? null : sanitize_key( $item['variant'] ),
                'field_key'  => $field_key,
                'requirement' => $requirement,
                'source'     => $source,
                'confidence' => $confidence,
                'message'    => sanitize_text_field( isset( $item['message'] ) ? $item['message'] : __( 'Requisito declarado pela integração do gateway.', 'gvn-checkout' ) ),
                'when'       => $when,
            );
        }

        return $requirements;
    }

    /**
     * Mescla requisitos gerais e específicos sem duplicar a mesma regra.
     *
     * @param string $gateway_id
     * @param object|array|null $gateway
     * @param array  $base_requirements
     * @param array  $posted_data
     * @return array
     */
    private function get_requirements_for_gateway( $gateway_id, $gateway, $base_requirements, $posted_data ) {
        $requirements = array();
        foreach ( $base_requirements as $requirement ) {
            $requirement['gateway_id'] = $gateway_id;
            $requirements[] = $requirement;
        }

        $extension_context = array(
            'gateway_id'     => $gateway_id,
            'posted_data'    => is_array( $posted_data ) ? $posted_data : array(),
            'payment_method' => isset( $posted_data['payment_method'] ) ? sanitize_key( $posted_data['payment_method'] ) : $gateway_id,
            'variant'        => $this->get_active_variant( $gateway_id, $posted_data ),
        );
        $requirements = array_merge(
            $requirements,
            $this->get_extension_requirements( $gateway_id, $gateway, $extension_context )
        );

        return $this->deduplicate_requirements( $requirements );
    }

    /**
     * Normaliza uma regra de condições sem aceitar chaves arbitrárias.
     *
     * @param array $conditions
     * @return array|null
     */
    private function sanitize_conditions( $conditions ) {
        $result = array(
            'logic' => 'and',
            'rules' => array(),
        );
        if ( isset( $conditions['logic'] ) ) {
            if ( ! is_scalar( $conditions['logic'] ) || ! in_array( $conditions['logic'], array( 'and', 'or' ), true ) ) {
                return null;
            }
            $result['logic'] = $conditions['logic'];
        }
        if ( empty( $conditions['rules'] ) || ! is_array( $conditions['rules'] ) ) {
            return $result;
        }

        foreach ( $conditions['rules'] as $rule ) {
            if (
                ! is_array( $rule )
                || ! isset( $rule['field'], $rule['operator'] )
                || ! is_scalar( $rule['field'] )
                || ! is_scalar( $rule['operator'] )
                || ( isset( $rule['value'] ) && ! is_scalar( $rule['value'] ) )
            ) {
                return null;
            }
            $field    = sanitize_key( isset( $rule['field'] ) ? $rule['field'] : '' );
            $operator = sanitize_key( isset( $rule['operator'] ) ? $rule['operator'] : '' );
            if ( '' === $field || ! in_array( $operator, array( 'equals', 'not_equals', 'filled', 'empty', 'contains', 'greater', 'less' ), true ) ) {
                return null;
            }
            $result['rules'][] = array(
                'field'    => $field,
                'operator' => $operator,
                'value'    => sanitize_text_field( isset( $rule['value'] ) ? $rule['value'] : '' ),
            );
        }

        return $result;
    }

    /**
     * Reduz o contexto enviado a integrações a valores escalares sanitizados.
     *
     * @param array $data
     * @return array
     */
    private function sanitize_context( $data ) {
        $sanitized = array();
        if ( ! is_array( $data ) ) {
            return $sanitized;
        }

        foreach ( $data as $key => $value ) {
            if ( ! is_scalar( $value ) ) {
                continue;
            }
            $safe_key = sanitize_key( $key );
            if ( '' === $safe_key ) {
                continue;
            }
            $sanitized[ $safe_key ] = sanitize_text_field( wp_unslash( $value ) );
        }

        return $sanitized;
    }

    /**
     * Determina a variante efetivamente selecionada sem adivinhar requisitos.
     * Integrações que usam um único ID de método devem enviar uma chave explícita
     * (`payment_method_variant` ou `gateway_variant`) no contexto do checkout.
     *
     * @param string $gateway_id
     * @param array  $posted_data
     * @return string
     */
    private function get_active_variant( $gateway_id, $posted_data ) {
        foreach ( array( 'payment_method_variant', 'gateway_variant', 'payment_variant' ) as $key ) {
            if ( isset( $posted_data[ $key ] ) && is_scalar( $posted_data[ $key ] ) && '' !== (string) $posted_data[ $key ] ) {
                return sanitize_key( $posted_data[ $key ] );
            }
        }

        $payment_method = isset( $posted_data['payment_method'] ) ? sanitize_key( $posted_data['payment_method'] ) : '';
        if ( '' !== $payment_method && $payment_method !== $gateway_id ) {
            return $payment_method;
        }

        return '';
    }

    /**
     * Deduplica por gateway, variante, campo e tipo de requisito.
     * A declaração mais específica substitui a informação geral anterior.
     *
     * @param array $requirements
     * @return array
     */
    private function deduplicate_requirements( $requirements ) {
        $indexed = array();
        foreach ( $requirements as $requirement ) {
            $variant = isset( $requirement['variant'] ) ? (string) $requirement['variant'] : '';
            $key = implode( '|', array( $requirement['gateway_id'], $variant, $requirement['field_key'] ) );
            if ( ! isset( $indexed[ $key ] ) ) {
                $indexed[ $key ] = $requirement;
                continue;
            }

            // A regra mais específica/forte vence: confiança confirmada nunca
            // pode ser rebaixada por uma declaração desconhecida; em empate,
            // adapter > extension > Woo e required > present.
            $current = $indexed[ $key ];
            $source_rank = array(
                self::SOURCE_WOOCOMMERCE => 0,
                self::SOURCE_EXTENSION   => 1,
                self::SOURCE_ADAPTER     => 2,
            );
            $current_rank = $source_rank[ $current['source'] ] ?? 0;
            $incoming_rank = $source_rank[ $requirement['source'] ] ?? 0;
            $current_confidence_rank  = self::CONFIDENCE_CONFIRMED === $current['confidence'] ? 1 : 0;
            $incoming_confidence_rank = self::CONFIDENCE_CONFIRMED === $requirement['confidence'] ? 1 : 0;
            if (
                $incoming_confidence_rank > $current_confidence_rank
                || ( $incoming_confidence_rank === $current_confidence_rank && $incoming_rank > $current_rank )
                || ( $incoming_confidence_rank === $current_confidence_rank && $incoming_rank === $current_rank && self::REQUIREMENT_REQUIRED === $requirement['requirement'] && self::REQUIREMENT_REQUIRED !== $current['requirement'] )
            ) {
                $indexed[ $key ] = $requirement;
            }
        }

        return array_values( $indexed );
    }

    /**
     * Acrescenta status de conflito ao item exibido no admin.
     *
     * @param array $requirement
     * @param array $configured_fields
     * @param array $checkout_fields
     * @return array
     */
    private function decorate_requirement( $requirement, $configured_fields, $checkout_fields ) {
        $configured = null;
        foreach ( $configured_fields as $field ) {
            if ( is_array( $field ) && isset( $field['key'] ) && $field['key'] === $requirement['field_key'] ) {
                $configured = $field;
                break;
            }
        }

        $checkout_field = $this->find_checkout_field( $requirement['field_key'], $checkout_fields );
        $status         = 'ok';
        $status_message = __( 'Campo disponível.', 'gvn-checkout' );

        if ( is_array( $configured ) && empty( $configured['enabled'] ) ) {
            $status         = 'disabled';
            $status_message = __( 'O campo está desativado no GVN Checkout.', 'gvn-checkout' );
        } elseif ( $this->is_hidden_checkout_field( $checkout_field ) ) {
            $status         = 'hidden';
            $status_message = __( 'O campo está oculto no checkout efetivo.', 'gvn-checkout' );
        } elseif ( is_array( $configured ) && ! empty( $requirement['when'] ) ) {
            $status         = 'conditional';
            $status_message = __( 'A disponibilidade depende de uma condição declarada pela integração.', 'gvn-checkout' );
        } elseif ( is_array( $configured ) && self::REQUIREMENT_REQUIRED === $requirement['requirement'] && empty( $configured['required'] ) ) {
            $status         = 'not_required';
            $status_message = __( 'O campo existe, mas não está marcado como obrigatório.', 'gvn-checkout' );
        } elseif ( null === $configured && ! is_array( $checkout_field ) ) {
            $status         = 'missing';
            $status_message = __( 'O campo não está disponível no checkout configurado.', 'gvn-checkout' );
        }

        $requirement['field_label'] = $this->get_field_label( $requirement['field_key'], $configured, $checkout_field );
        $requirement['status']      = $status;
        $requirement['status_message'] = $status_message;
        $requirement['configured']  = is_array( $configured );
        return $requirement;
    }

    /**
     * Identifica classes/flags que impedem a renderização efetiva do campo.
     *
     * @param array|null $checkout_field
     * @return bool
     */
    private function is_hidden_checkout_field( $checkout_field ) {
        if ( ! is_array( $checkout_field ) ) {
            return false;
        }
        if ( ! empty( $checkout_field['hidden'] ) ) {
            return true;
        }
        if ( isset( $checkout_field['class'] ) && is_array( $checkout_field['class'] ) && in_array( 'gvn-hidden-field', $checkout_field['class'], true ) ) {
            return true;
        }
        if ( isset( $checkout_field['class'] ) && is_string( $checkout_field['class'] ) && false !== strpos( $checkout_field['class'], 'gvn-hidden-field' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Obtém os campos configurados pelo GVN quando a classe já está carregada.
     *
     * @return array
     */
    private function get_configured_fields() {
        if ( class_exists( '\\GVN_Custom_Fields' ) && method_exists( '\\GVN_Custom_Fields', 'get_fields' ) ) {
            return (array) \GVN_Custom_Fields::get_fields();
        }

        return array();
    }

    /**
     * Obtém campos efetivos do checkout sem executar gateways de pagamento.
     *
     * @return array
     */
    private function get_checkout_fields() {
        if ( function_exists( 'WC' ) && WC() && method_exists( WC()->checkout(), 'get_checkout_fields' ) ) {
            return (array) WC()->checkout()->get_checkout_fields();
        }

        $fields = array(
            'billing'  => array(),
            'shipping' => array(),
            'account'  => array(),
            'order'    => array(),
        );

        return apply_filters( 'woocommerce_checkout_fields', $fields );
    }

    /**
     * Obtém gateways disponíveis/registrados no WooCommerce.
     *
     * @return array
     */
    private function get_gateways() {
        if ( ! function_exists( 'WC' ) || ! WC() || ! method_exists( WC(), 'payment_gateways' ) ) {
            return array();
        }

        $manager = WC()->payment_gateways();
        if ( ! $manager ) {
            return array();
        }

        $gateways = array();
        if ( method_exists( $manager, 'get_available_payment_gateways' ) ) {
            $available = $manager->get_available_payment_gateways();
            if ( is_array( $available ) ) {
                $gateways = $available;
            }
        }
        if ( method_exists( $manager, 'get_payment_gateways' ) ) {
            $registered = $manager->get_payment_gateways();
            if ( is_array( $registered ) ) {
                $gateways = array_merge( $registered, $gateways );
            }
        }
        if ( empty( $gateways ) && isset( $manager->payment_gateways ) && is_array( $manager->payment_gateways ) ) {
            $gateways = $manager->payment_gateways;
        }

        return $gateways;
    }

    /**
     * Procura um gateway pelo ID sem disparar processamento de pagamento.
     *
     * @param string $gateway_id
     * @return object|array|null
     */
    private function find_gateway( $gateway_id ) {
        foreach ( $this->get_gateways() as $key => $gateway ) {
            $metadata = $this->normalize_gateway( $key, $gateway );
            if ( $metadata['id'] === $gateway_id ) {
                return $metadata['object'];
            }
        }

        return null;
    }

    /**
     * Normaliza a identidade pública de um gateway.
     *
     * O `plugin_id` nativo do WooCommerce (`WC_Settings_API::$plugin_id`) vale
     * `woocommerce_` para praticamente todos os gateways, então ele NÃO pode
     * ser usado como identificador do plugin. Quando o valor é genérico, o
     * plugin real é detectado pelo arquivo da classe do gateway via Reflection.
     *
     * @param string|int $key
     * @param object|array $gateway
     * @return array
     */
    private function normalize_gateway( $key, $gateway ) {
        $id = is_object( $gateway ) && isset( $gateway->id ) ? $gateway->id : $key;
        $id = sanitize_key( $id );
        $title = '';
        if ( is_object( $gateway ) && method_exists( $gateway, 'get_title' ) ) {
            $title = $gateway->get_title();
        } elseif ( is_object( $gateway ) && isset( $gateway->title ) ) {
            $title = $gateway->title;
        } elseif ( is_array( $gateway ) && isset( $gateway['title'] ) ) {
            $title = $gateway['title'];
        }
        $title = sanitize_text_field( $title ? $title : $id );

        $method_title = '';
        if ( is_object( $gateway ) && method_exists( $gateway, 'get_method_title' ) ) {
            $method_title = $gateway->get_method_title();
        } elseif ( is_object( $gateway ) && isset( $gateway->method_title ) ) {
            $method_title = $gateway->method_title;
        } elseif ( is_array( $gateway ) && isset( $gateway['method_title'] ) ) {
            $method_title = $gateway['method_title'];
        }
        $method_title = sanitize_text_field( $method_title );

        $raw_plugin_id = '';
        if ( is_object( $gateway ) && isset( $gateway->plugin_id ) ) {
            $raw_plugin_id = $gateway->plugin_id;
        } elseif ( is_array( $gateway ) && isset( $gateway['plugin_id'] ) ) {
            $raw_plugin_id = $gateway['plugin_id'];
        }
        $raw_plugin_id = is_scalar( $raw_plugin_id ) ? sanitize_key( $raw_plugin_id ) : '';

        $plugin_identity = $this->resolve_plugin_identity( $gateway, $id, $raw_plugin_id, $method_title, $title );
        $plugin_id = $plugin_identity['id'];
        $plugin_title = $plugin_identity['title'];
        $plugin_source = $plugin_identity['source'];

        $description = '';
        if ( is_object( $gateway ) && method_exists( $gateway, 'get_description' ) ) {
            $description = $gateway->get_description();
        } elseif ( is_object( $gateway ) && isset( $gateway->description ) ) {
            $description = $gateway->description;
        } elseif ( is_array( $gateway ) && isset( $gateway['description'] ) ) {
            $description = $gateway['description'];
        }

        $active = true;
        if ( is_object( $gateway ) && isset( $gateway->enabled ) ) {
            $active = 'no' !== (string) $gateway->enabled;
        } elseif ( is_object( $gateway ) && isset( $gateway->settings['enabled'] ) ) {
            $active = 'no' !== (string) $gateway->settings['enabled'];
        } elseif ( is_array( $gateway ) && isset( $gateway['enabled'] ) ) {
            $active = 'no' !== (string) $gateway['enabled'];
        }

        $has_fields = false;
        if ( is_object( $gateway ) && method_exists( $gateway, 'has_fields' ) ) {
            $has_fields = (bool) $gateway->has_fields();
        }

        return array(
            'id'          => $id,
            'title'       => $title,
            'description' => sanitize_text_field( $description ),
            'plugin_id'   => $plugin_id,
            'plugin_title' => $plugin_title,
            'plugin_source' => $plugin_source,
            'active'      => $active,
            'has_fields'  => $has_fields,
            'object'      => $gateway,
        );
    }

    /**
     * Resolve a identidade real do plugin que fornece o gateway.
     *
     * Nunca confia em `plugin_id` genérico do WooCommerce (`woocommerce_`,
     * `woocommerce`, `wc`, etc.) nem em valor igual ao ID do gateway — ambos
     * indicam ausência de declaração e causariam o agrupamento indevido de
     * plugins diferentes no painel.
     *
     * @param object|array $gateway
     * @param string $gateway_id
     * @param string $raw_plugin_id
     * @param string $method_title
     * @param string $title
     * @return array{id:string,title:string,source:string}
     */
    private function resolve_plugin_identity( $gateway, $gateway_id, $raw_plugin_id, $method_title, $title ) {
        if ( '' !== $raw_plugin_id && ! $this->is_generic_plugin_id( $raw_plugin_id, $gateway_id ) ) {
            $plugin_title = $method_title ? $method_title : $title;
            return array(
                'id'     => $raw_plugin_id,
                'title'  => $plugin_title ? $plugin_title : $raw_plugin_id,
                'source' => 'declared',
            );
        }

        $detected = $this->detect_plugin_from_gateway_class( $gateway );
        if ( is_array( $detected ) && '' !== $detected['id'] ) {
            return $detected;
        }

        // Fallback honesto: sem evidência do plugin real, cada método forma
        // seu próprio grupo para jamais misturar plugins diferentes. O painel
        // sinaliza essa situação como identificação individual.
        $fallback_title = ( '' !== $method_title && $method_title !== $title ) ? $method_title : $title;
        if ( '' === $fallback_title ) {
            $fallback_title = $gateway_id;
        }

        return array(
            'id'     => $gateway_id ? $gateway_id : 'gateway',
            'title'  => $fallback_title,
            'source' => 'fallback',
        );
    }

    /**
     * Indica se o `plugin_id` é genérico demais para identificar o plugin.
     *
     * @param string $plugin_id
     * @param string $gateway_id
     * @return bool
     */
    private function is_generic_plugin_id( $plugin_id, $gateway_id ) {
        $generic = array( '', 'woocommerce', 'woocommerce_', 'wc', 'wc_', 'wordpress', 'payment', 'payments', 'gateway', 'gateways' );
        if ( in_array( $plugin_id, $generic, true ) ) {
            return true;
        }
        if ( '' !== $gateway_id && $plugin_id === $gateway_id ) {
            return true;
        }

        return false;
    }

    /**
     * Detecta o plugin pelo arquivo da classe do gateway (Reflection).
     *
     * Gateways do mesmo plugin compartilham o mesmo diretório de plugin, então
     * métodos como cartão/boleto/Pix do PagBank são agrupados corretamente sem
     * depender do `plugin_id` genérico do WooCommerce.
     *
     * @param object|array $gateway
     * @return array|null
     */
    private function detect_plugin_from_gateway_class( $gateway ) {
        if ( ! is_object( $gateway ) ) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass( $gateway );
            $file = $reflection->getFileName();
        } catch ( \Exception $e ) {
            return null;
        } catch ( \Throwable $e ) {
            return null;
        }

        if ( ! is_string( $file ) || '' === $file ) {
            return null;
        }

        $normalized_file = $this->normalize_fs_path( $file );

        $plugin_dir = defined( 'WP_PLUGIN_DIR' ) ? $this->normalize_fs_path( (string) WP_PLUGIN_DIR ) : '';
        if ( '' !== $plugin_dir && 0 === strpos( $normalized_file, rtrim( $plugin_dir, '/' ) . '/' ) ) {
            $relative = ltrim( substr( $normalized_file, strlen( rtrim( $plugin_dir, '/' ) . '/' ) ), '/' );
            if ( '' === $relative ) {
                return null;
            }
            $parts = explode( '/', $relative );
            $slug_dir = $parts[0];
            if ( '' === $slug_dir ) {
                return null;
            }
            if ( 1 === count( $parts ) ) {
                $slug = (string) preg_replace( '/\.php$/', '', $slug_dir );
            } else {
                $slug = $slug_dir;
            }
            $slug = sanitize_key( $slug );
            if ( '' === $slug ) {
                return null;
            }

            return array(
                'id'     => $slug,
                'title'  => $this->get_plugin_name_for_slug( $slug, $relative ),
                'source' => 'detected',
            );
        }

        $mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? $this->normalize_fs_path( (string) WPMU_PLUGIN_DIR ) : '';
        if ( '' !== $mu_dir && 0 === strpos( $normalized_file, rtrim( $mu_dir, '/' ) . '/' ) ) {
            $relative = ltrim( substr( $normalized_file, strlen( rtrim( $mu_dir, '/' ) . '/' ) ), '/' );
            $parts = explode( '/', $relative );
            $slug = sanitize_key( (string) preg_replace( '/\.php$/', '', $parts[0] ) );
            if ( '' !== $slug ) {
                return array(
                    'id'     => $slug,
                    'title'  => $this->humanize_slug( $slug ),
                    'source' => 'detected',
                );
            }
        }

        return null;
    }

    /**
     * Resolve o nome de exibição do plugin a partir do slug.
     *
     * @param string $slug
     * @param string $relative_file
     * @return string
     */
    private function get_plugin_name_for_slug( $slug, $relative_file ) {
        if ( 'woocommerce' === $slug ) {
            return 'WooCommerce';
        }

        if ( function_exists( 'get_plugins' ) ) {
            static $all_plugins = null;
            if ( null === $all_plugins ) {
                $all_plugins = get_plugins();
            }
            if ( is_array( $all_plugins ) ) {
                foreach ( $all_plugins as $basename => $data ) {
                    $dir = strpos( $basename, '/' ) !== false ? substr( $basename, 0, strpos( $basename, '/' ) ) : (string) preg_replace( '/\.php$/', '', $basename );
                    if ( $dir === $slug && is_array( $data ) && ! empty( $data['Name'] ) ) {
                        return sanitize_text_field( $data['Name'] );
                    }
                }
            }
        }

        if ( function_exists( 'get_plugin_data' ) ) {
            $candidate = '';
            if ( defined( 'WP_PLUGIN_DIR' ) ) {
                $base = rtrim( (string) WP_PLUGIN_DIR, '/\\' );
                foreach ( array( $slug . '/' . $slug . '.php', $slug . '.php' ) as $try ) {
                    $full = $base . '/' . $try;
                    if ( is_string( $full ) && file_exists( $full ) ) {
                        $candidate = $full;
                        break;
                    }
                }
            }
            if ( '' !== $candidate ) {
                $data = get_plugin_data( $candidate, false, false );
                if ( is_array( $data ) && ! empty( $data['Name'] ) ) {
                    return sanitize_text_field( $data['Name'] );
                }
            }
        }

        return $this->humanize_slug( $slug );
    }

    /**
     * Normaliza caminho de arquivo para comparação (barras + minúsculas no Windows).
     *
     * @param string $path
     * @return string
     */
    private function normalize_fs_path( $path ) {
        if ( function_exists( 'wp_normalize_path' ) ) {
            return wp_normalize_path( $path );
        }

        return str_replace( '\\', '/', (string) $path );
    }

    /**
     * Converte um slug em nome legível ("pagbank-connect" → "Pagbank Connect").
     *
     * @param string $slug
     * @return string
     */
    private function humanize_slug( $slug ) {
        $slug = trim( (string) $slug );
        if ( '' === $slug ) {
            return $slug;
        }
        $label = str_replace( array( '-', '_' ), ' ', $slug );

        if ( function_exists( 'mb_convert_case' ) ) {
            return mb_convert_case( $label, MB_CASE_TITLE, 'UTF-8' );
        }

        return ucwords( $label );
    }

    /**
     * Encontra uma definição de campo em qualquer seção do checkout.
     *
     * @param string $field_key
     * @param array  $checkout_fields
     * @return array|null
     */
    private function find_checkout_field( $field_key, $checkout_fields ) {
        foreach ( (array) $checkout_fields as $section_fields ) {
            if ( is_array( $section_fields ) && isset( $section_fields[ $field_key ] ) && is_array( $section_fields[ $field_key ] ) ) {
                return $section_fields[ $field_key ];
            }
        }

        return null;
    }

    /**
     * Resolve o rótulo seguro exibido no relatório.
     *
     * @param string     $field_key
     * @param array|null $configured
     * @param array|null $checkout_field
     * @return string
     */
    private function get_field_label( $field_key, $configured, $checkout_field ) {
        if ( is_array( $configured ) && ! empty( $configured['label'] ) ) {
            return sanitize_text_field( $configured['label'] );
        }
        if ( is_array( $checkout_field ) && ! empty( $checkout_field['label'] ) ) {
            return sanitize_text_field( $checkout_field['label'] );
        }

        return $field_key;
    }
}
