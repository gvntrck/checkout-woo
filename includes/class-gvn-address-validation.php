<?php
/**
 * Validação e autocomplete de endereço robusto com fallback manual e cache seguro.
 *
 * Proxy server-side para ViaCEP com cache (transients),
 * normalização de UF/cidade, mapeamento CEP→UF e
 * validação de consistência no checkout.
 *
 * @package GVN_Checkout
 * @version 1.13.35
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GVN_Address_Validation {

    private static $instance = null;

    /**
     * Lista completa de UFs brasileiras válidas.
     */
    const VALID_UFS = array(
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
        'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    );

    /**
     * Mapeamento de faixas de CEP → UF.
     * Cada entrada: array( cep_inicio, cep_fim, 'UF' ).
     */
    private static $cep_ranges = array(
        array(  1000000, 19999999, 'SP' ),
        array( 20000000, 28999999, 'RJ' ),
        array( 29000000, 29999999, 'ES' ),
        array( 30000000, 39999999, 'MG' ),
        array( 40000000, 48999999, 'BA' ),
        array( 49000000, 49999999, 'SE' ),
        array( 50000000, 56999999, 'PE' ),
        array( 57000000, 57999999, 'AL' ),
        array( 58000000, 58999999, 'PB' ),
        array( 59000000, 59999999, 'RN' ),
        array( 60000000, 63999999, 'CE' ),
        array( 64000000, 64999999, 'PI' ),
        array( 65000000, 65999999, 'MA' ),
        array( 66000000, 68899999, 'PA' ),
        array( 68900000, 68999999, 'AP' ),
        array( 69000000, 69299999, 'AM' ),
        array( 69300000, 69399999, 'RR' ),
        array( 69400000, 69899999, 'AM' ),
        array( 69900000, 69999999, 'AC' ),
        array( 70000000, 72799999, 'DF' ),
        array( 72800000, 76799999, 'GO' ),
        array( 76800000, 76999999, 'RO' ),
        array( 77000000, 77999999, 'TO' ),
        array( 78000000, 78999999, 'MT' ),
        array( 79000000, 79999999, 'MS' ),
        array( 80000000, 87999999, 'PR' ),
        array( 88000000, 89999999, 'SC' ),
        array( 90000000, 99999999, 'RS' ),
    );

    /**
     * Nomes completos das UFs para exibição.
     */
    const UF_NAMES = array(
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    );

    /** Cache TTL: 7 dias em segundos. */
    const CACHE_TTL = 604800;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX: proxy de consulta CEP (logado e não-logado)
        add_action( 'wp_ajax_gvn_cep_lookup', array( $this, 'ajax_cep_lookup' ) );
        add_action( 'wp_ajax_nopriv_gvn_cep_lookup', array( $this, 'ajax_cep_lookup' ) );

        // Validação server-side no checkout
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_address_fields' ), 10, 2 );

        // Normalização antes de salvar o pedido
        add_action( 'woocommerce_checkout_create_order', array( $this, 'normalize_order_address' ), 10, 2 );
    }

    /* =========================================================================
       AJAX: Proxy CEP com cache e fallback
       ========================================================================= */

    /**
     * Endpoint AJAX para consulta de CEP via servidor.
     * Retorna dados normalizados e cacheia com transients de forma segura.
     */
    public function ajax_cep_lookup() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        $cep_raw = isset( $_POST['cep'] ) ? sanitize_text_field( wp_unslash( $_POST['cep'] ) ) : '';
        $cep     = preg_replace( '/\D/', '', $cep_raw );

        if ( ! preg_match( '/^[0-9]{8}$/', $cep ) ) {
            wp_send_json_error( array( 'message' => __( 'CEP deve conter exatamente 8 dígitos numéricos.', 'gvn-checkout' ) ) );
            return;
        }

        // Verificar cache existente e validar seu schema
        $cache_key = 'gvn_cep_' . $cep;
        $cached    = function_exists( 'get_transient' ) ? get_transient( $cache_key ) : false;

        if ( is_array( $cached ) && ! empty( $cached['cep'] ) && isset( $cached['cidade'], $cached['uf'] ) ) {
            wp_send_json_success( $cached );
            return;
        }

        // Consultar ViaCEP utilizando endpoint fixo e seguro
        $request_args = array(
            'timeout'     => 8,
            'redirection' => 2,
            'httpversion' => '1.1',
            'user-agent'  => 'GVN-Checkout/' . ( defined( 'GVN_CHECKOUT_VERSION' ) ? GVN_CHECKOUT_VERSION : '1.0.0' ) . '; WordPress/' . ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '6.0' ),
            'headers'     => array(
                'Accept' => 'application/json',
            ),
        );

        $url      = 'https://viacep.com.br/ws/' . $cep . '/json/';
        $response = function_exists( 'wp_safe_remote_get' ) ? wp_safe_remote_get( $url, $request_args ) : wp_remote_get( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array(
                'message'      => __( 'Não foi possível consultar o CEP automaticamente. Por favor, preencha o endereço manualmente.', 'gvn-checkout' ),
                'manual_entry' => true,
            ) );
            return;
        }

        $status_code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 200;
        if ( 200 !== $status_code ) {
            wp_send_json_error( array(
                'message'      => __( 'Serviço de CEP temporariamente indisponível. Por favor, preencha o endereço manualmente.', 'gvn-checkout' ),
                'manual_entry' => true,
            ) );
            return;
        }

        $body = function_exists( 'wp_remote_retrieve_body' ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : array();

        if ( empty( $body ) || ! is_array( $body ) || ! empty( $body['erro'] ) ) {
            wp_send_json_error( array(
                'message'      => __( 'CEP não encontrado. Por favor, confira o número ou preencha o endereço manualmente.', 'gvn-checkout' ),
                'manual_entry' => true,
            ) );
            return;
        }

        // Normalizar dados
        $data = self::normalize_viacep_data( $body, $cep );

        // Cachear resultado válido
        if ( function_exists( 'set_transient' ) ) {
            set_transient( $cache_key, $data, self::CACHE_TTL );
        }

        wp_send_json_success( $data );
    }

    /* =========================================================================
       Normalização
       ========================================================================= */

    /**
     * Normaliza os dados retornados pelo ViaCEP.
     *
     * @param array  $raw Resposta crua do ViaCEP.
     * @param string $cep CEP numérico (8 dígitos).
     * @return array Dados normalizados.
     */
    public static function normalize_viacep_data( $raw, $cep ) {
        $uf          = isset( $raw['uf'] ) ? self::uppercase( trim( (string) $raw['uf'] ) ) : '';
        $uf_esperada = self::get_uf_from_cep( $cep );

        return array(
            'cep'         => $cep,
            'logradouro'  => self::normalize_text( isset( $raw['logradouro'] ) ? (string) $raw['logradouro'] : '' ),
            'complemento' => self::normalize_text( isset( $raw['complemento'] ) ? (string) $raw['complemento'] : '' ),
            'bairro'      => self::normalize_text( isset( $raw['bairro'] ) ? (string) $raw['bairro'] : '' ),
            'cidade'      => self::normalize_city( isset( $raw['localidade'] ) ? (string) $raw['localidade'] : '' ),
            'uf'          => $uf,
            'uf_nome'     => isset( self::UF_NAMES[ $uf ] ) ? self::UF_NAMES[ $uf ] : $uf,
            'ibge'        => isset( $raw['ibge'] ) ? sanitize_text_field( (string) $raw['ibge'] ) : '',
            'uf_esperada' => $uf_esperada,
            'consistente' => ( $uf === $uf_esperada ),
        );
    }

    /**
     * Normaliza texto genérico (trim).
     */
    public static function normalize_text( $text ) {
        $text = trim( (string) $text );
        if ( empty( $text ) ) {
            return '';
        }
        return $text;
    }

    /**
     * Normaliza nome de cidade: trim, capitalização adequada.
     * Preserva preposições minúsculas (de, da, do, das, dos, e).
     */
    public static function normalize_city( $city ) {
        $city = trim( (string) $city );
        if ( empty( $city ) ) {
            return '';
        }

        $prepositions = array( 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na', 'nos', 'nas' );
        $words        = explode( ' ', self::lowercase( $city ) );
        $result       = array();

        foreach ( $words as $i => $word ) {
            if ( $i > 0 && in_array( $word, $prepositions, true ) ) {
                $result[] = $word;
            } else {
                $result[] = self::uppercase( self::substring( $word, 0, 1 ) ) . self::substring( $word, 1 );
            }
        }

        return implode( ' ', $result );
    }

    /**
     * Normaliza UF para maiúsculas e valida.
     *
     * @param string $uf
     * @return string UF normalizada ou vazio se inválida.
     */
    public static function normalize_uf( $uf ) {
        $uf = self::uppercase( trim( (string) $uf ) );
        return in_array( $uf, self::VALID_UFS, true ) ? $uf : '';
    }

    /**
     * Formata CEP com máscara (XXXXX-XXX).
     */
    public static function format_cep( $cep ) {
        $cep = preg_replace( '/\D/', '', (string) $cep );
        if ( strlen( $cep ) === 8 ) {
            return substr( $cep, 0, 5 ) . '-' . substr( $cep, 5 );
        }
        return $cep;
    }

    /* =========================================================================
       Mapeamento CEP → UF
       ========================================================================= */

    /**
     * Retorna a UF esperada com base na faixa do CEP.
     *
     * @param string $cep CEP numérico (8 dígitos).
     * @return string UF ou string vazia se fora de faixa.
     */
    public static function get_uf_from_cep( $cep ) {
        $cep_num = absint( $cep );
        if ( $cep_num < 1000000 || $cep_num > 99999999 ) {
            return '';
        }

        foreach ( self::$cep_ranges as $range ) {
            if ( $cep_num >= $range[0] && $cep_num <= $range[1] ) {
                return $range[2];
            }
        }

        return '';
    }

    /**
     * Verifica se um CEP é consistente com a UF informada.
     *
     * @param string $cep CEP numérico.
     * @param string $uf  UF informada.
     * @return bool
     */
    public static function is_cep_consistent_with_uf( $cep, $uf ) {
        $expected = self::get_uf_from_cep( $cep );
        if ( empty( $expected ) ) {
            return true; // Não mapeado, não bloqueia
        }
        return ( self::uppercase( trim( (string) $uf ) ) === $expected );
    }

    /**
     * Valida formato de CEP (8 dígitos numéricos).
     */
    public static function is_valid_cep_format( $cep ) {
        $cep = preg_replace( '/\D/', '', (string) $cep );
        return (bool) preg_match( '/^[0-9]{8}$/', $cep );
    }

    /**
     * Valida se UF é brasileira válida.
     */
    public static function is_valid_uf( $uf ) {
        return in_array( self::uppercase( trim( (string) $uf ) ), self::VALID_UFS, true );
    }

    /**
     * Retorna lista de UFs para uso em selects.
     *
     * @return array Associativo UF => Nome.
     */
    public static function get_uf_options() {
        return self::UF_NAMES;
    }

    /* =========================================================================
       Validação server-side no Checkout
       ========================================================================= */

    /**
     * Valida campos de endereço durante o checkout do WooCommerce.
     *
     * @param array    $data   Dados do checkout.
     * @param WP_Error $errors Objeto de erros.
     */
    public function validate_address_fields( $data, $errors ) {
        $country = isset( $data['billing_country'] ) ? (string) $data['billing_country'] : 'BR';

        // Regras específicas de CEP/UF aplicam-se apenas para endereços no Brasil
        if ( ! empty( $country ) && 'BR' !== $country ) {
            return;
        }

        // --- Validação de CEP ---
        if ( isset( $data['billing_postcode'] ) ) {
            $cep       = (string) $data['billing_postcode'];
            $cep_clean = preg_replace( '/\D/', '', $cep );

            if ( ! empty( $cep_clean ) && ! self::is_valid_cep_format( $cep_clean ) ) {
                $errors->add( 'gvn_invalid_cep', sprintf( '<strong>%s</strong> %s', esc_html__( 'CEP', 'gvn-checkout' ), esc_html__( 'deve conter exatamente 8 dígitos.', 'gvn-checkout' ) ) );
            }
        }

        // --- Validação de UF ---
        if ( isset( $data['billing_state'] ) ) {
            $uf = (string) $data['billing_state'];

            if ( ! empty( $uf ) && ! self::is_valid_uf( $uf ) ) {
                $errors->add( 'gvn_invalid_uf', sprintf( '<strong>%s</strong> %s', esc_html__( 'Estado (UF)', 'gvn-checkout' ), esc_html__( 'inválido. Selecione um estado brasileiro válido.', 'gvn-checkout' ) ) );
            }

            // --- Consistência CEP ↔ UF ---
            if ( isset( $data['billing_postcode'] ) ) {
                $cep       = (string) $data['billing_postcode'];
                $cep_clean = preg_replace( '/\D/', '', $cep );

                if ( ! empty( $cep_clean ) && strlen( $cep_clean ) === 8 && ! empty( $uf ) && self::is_valid_uf( $uf ) ) {
                    if ( ! self::is_cep_consistent_with_uf( $cep_clean, $uf ) ) {
                        $expected_uf   = self::get_uf_from_cep( $cep_clean );
                        $expected_name = isset( self::UF_NAMES[ $expected_uf ] ) ? self::UF_NAMES[ $expected_uf ] : $expected_uf;
                        $errors->add(
                            'gvn_cep_uf_mismatch',
                            sprintf(
                                '<strong>%s</strong> %s %s <strong>%s</strong>, %s',
                                esc_html__( 'CEP', 'gvn-checkout' ),
                                esc_html( self::format_cep( $cep_clean ) ),
                                esc_html__( 'pertence ao estado', 'gvn-checkout' ),
                                esc_html( $expected_name ),
                                esc_html__( 'mas o estado informado é diferente. Verifique o endereço.', 'gvn-checkout' )
                            )
                        );
                    }
                }
            }
        }

        // --- Validação de cidade ---
        if ( isset( $data['billing_city'] ) ) {
            $city = trim( (string) $data['billing_city'] );
            if ( ! empty( $city ) && self::string_length( $city ) < 2 ) {
                $errors->add( 'gvn_invalid_city', sprintf( '<strong>%s</strong> %s', esc_html__( 'Cidade', 'gvn-checkout' ), esc_html__( 'deve ter ao menos 2 caracteres.', 'gvn-checkout' ) ) );
            }
        }
    }

    /**
     * Funções de texto com fallback para instalações de PHP sem mbstring.
     */
    private static function uppercase( $value ) {
        return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value ) : strtoupper( $value );
    }

    private static function lowercase( $value ) {
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
    }

    private static function substring( $value, $start, $length = null ) {
        if ( function_exists( 'mb_substr' ) ) {
            return null === $length ? mb_substr( $value, $start ) : mb_substr( $value, $start, $length );
        }

        return null === $length ? substr( $value, $start ) : substr( $value, $start, $length );
    }

    private static function string_length( $value ) {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
    }

    /* =========================================================================
       Normalização ao criar o pedido
       ========================================================================= */

    /**
     * Normaliza dados de endereço antes de salvar o pedido.
     *
     * @param WC_Order $order Pedido.
     * @param array    $data  Dados do checkout.
     */
    public function normalize_order_address( $order, $data ) {
        if ( ! is_object( $order ) ) {
            return;
        }

        // Normalizar UF (billing e shipping)
        if ( method_exists( $order, 'get_billing_state' ) && method_exists( $order, 'set_billing_state' ) ) {
            $billing_uf = $order->get_billing_state();
            if ( ! empty( $billing_uf ) ) {
                $normalized_uf = self::normalize_uf( $billing_uf );
                if ( ! empty( $normalized_uf ) ) {
                    $order->set_billing_state( $normalized_uf );
                }
            }
        }

        if ( method_exists( $order, 'get_shipping_state' ) && method_exists( $order, 'set_shipping_state' ) ) {
            $shipping_uf = $order->get_shipping_state();
            if ( ! empty( $shipping_uf ) ) {
                $normalized_uf = self::normalize_uf( $shipping_uf );
                if ( ! empty( $normalized_uf ) ) {
                    $order->set_shipping_state( $normalized_uf );
                }
            }
        }

        // Normalizar cidade (billing e shipping)
        if ( method_exists( $order, 'get_billing_city' ) && method_exists( $order, 'set_billing_city' ) ) {
            $billing_city = $order->get_billing_city();
            if ( ! empty( $billing_city ) ) {
                $order->set_billing_city( self::normalize_city( $billing_city ) );
            }
        }

        if ( method_exists( $order, 'get_shipping_city' ) && method_exists( $order, 'set_shipping_city' ) ) {
            $shipping_city = $order->get_shipping_city();
            if ( ! empty( $shipping_city ) ) {
                $order->set_shipping_city( self::normalize_city( $shipping_city ) );
            }
        }

        // Normalizar CEP (formato com máscara) — billing e shipping
        if ( method_exists( $order, 'get_billing_postcode' ) && method_exists( $order, 'set_billing_postcode' ) ) {
            $billing_postcode = $order->get_billing_postcode();
            if ( ! empty( $billing_postcode ) ) {
                $cep_clean = preg_replace( '/\D/', '', $billing_postcode );
                if ( strlen( $cep_clean ) === 8 ) {
                    $order->set_billing_postcode( self::format_cep( $cep_clean ) );
                }
            }
        }

        if ( method_exists( $order, 'get_shipping_postcode' ) && method_exists( $order, 'set_shipping_postcode' ) ) {
            $shipping_postcode = $order->get_shipping_postcode();
            if ( ! empty( $shipping_postcode ) ) {
                $cep_clean = preg_replace( '/\D/', '', $shipping_postcode );
                if ( strlen( $cep_clean ) === 8 ) {
                    $order->set_shipping_postcode( self::format_cep( $cep_clean ) );
                }
            }
        }

        // Normalizar endereço (trim) — billing e shipping
        if ( method_exists( $order, 'get_billing_address_1' ) && method_exists( $order, 'set_billing_address_1' ) ) {
            $billing_address = $order->get_billing_address_1();
            if ( ! empty( $billing_address ) ) {
                $order->set_billing_address_1( trim( $billing_address ) );
            }
        }

        if ( method_exists( $order, 'get_shipping_address_1' ) && method_exists( $order, 'set_shipping_address_1' ) ) {
            $shipping_address = $order->get_shipping_address_1();
            if ( ! empty( $shipping_address ) ) {
                $order->set_shipping_address_1( trim( $shipping_address ) );
            }
        }
    }
}
