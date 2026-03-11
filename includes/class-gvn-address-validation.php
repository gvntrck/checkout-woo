<?php
/**
 * Validação e autocomplete de endereço robusto.
 *
 * Proxy server-side para ViaCEP com cache (transients),
 * normalização de UF/cidade, mapeamento CEP→UF e
 * validação de consistência no checkout.
 *
 * @package GVN_Checkout
 * @version 1.13.0
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
       AJAX: Proxy CEP com cache
       ========================================================================= */

    /**
     * Endpoint AJAX para consulta de CEP via servidor.
     * Retorna dados normalizados e cacheia com transients.
     */
    public function ajax_cep_lookup() {
        check_ajax_referer( 'gvn_checkout_nonce', 'nonce' );

        $cep = isset( $_POST['cep'] ) ? sanitize_text_field( wp_unslash( $_POST['cep'] ) ) : '';
        $cep = preg_replace( '/\D/', '', $cep );

        if ( strlen( $cep ) !== 8 ) {
            wp_send_json_error( array( 'message' => 'CEP deve conter 8 dígitos.' ) );
        }

        // Verificar cache
        $cache_key = 'gvn_cep_' . $cep;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            wp_send_json_success( $cached );
        }

        // Consultar ViaCEP
        $response = wp_remote_get( 'https://viacep.com.br/ws/' . $cep . '/json/', array(
            'timeout' => 10,
            'headers' => array( 'Accept' => 'application/json' ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Erro ao consultar o CEP. Tente novamente.' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ! empty( $body['erro'] ) ) {
            wp_send_json_error( array( 'message' => 'CEP não encontrado.' ) );
        }

        // Normalizar dados
        $data = self::normalize_viacep_data( $body, $cep );

        // Cachear resultado
        set_transient( $cache_key, $data, self::CACHE_TTL );

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
        $uf = isset( $raw['uf'] ) ? mb_strtoupper( trim( $raw['uf'] ) ) : '';

        return array(
            'cep'          => $cep,
            'logradouro'   => self::normalize_text( isset( $raw['logradouro'] ) ? $raw['logradouro'] : '' ),
            'complemento'  => self::normalize_text( isset( $raw['complemento'] ) ? $raw['complemento'] : '' ),
            'bairro'       => self::normalize_text( isset( $raw['bairro'] ) ? $raw['bairro'] : '' ),
            'cidade'       => self::normalize_city( isset( $raw['localidade'] ) ? $raw['localidade'] : '' ),
            'uf'           => $uf,
            'uf_nome'      => isset( self::UF_NAMES[ $uf ] ) ? self::UF_NAMES[ $uf ] : $uf,
            'ibge'         => isset( $raw['ibge'] ) ? sanitize_text_field( $raw['ibge'] ) : '',
            'uf_esperada'  => self::get_uf_from_cep( $cep ),
            'consistente'  => ( $uf === self::get_uf_from_cep( $cep ) ),
        );
    }

    /**
     * Normaliza texto genérico (trim + title case para nomes próprios).
     */
    public static function normalize_text( $text ) {
        $text = trim( $text );
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
        $city = trim( $city );
        if ( empty( $city ) ) {
            return '';
        }

        $prepositions = array( 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na', 'nos', 'nas' );
        $words        = explode( ' ', mb_strtolower( $city ) );
        $result       = array();

        foreach ( $words as $i => $word ) {
            if ( $i > 0 && in_array( $word, $prepositions, true ) ) {
                $result[] = $word;
            } else {
                $result[] = mb_strtoupper( mb_substr( $word, 0, 1 ) ) . mb_substr( $word, 1 );
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
        $uf = mb_strtoupper( trim( $uf ) );
        return in_array( $uf, self::VALID_UFS, true ) ? $uf : '';
    }

    /**
     * Formata CEP com máscara (XXXXX-XXX).
     */
    public static function format_cep( $cep ) {
        $cep = preg_replace( '/\D/', '', $cep );
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
            return true; // não conseguimos mapear, não bloqueia
        }
        return ( mb_strtoupper( trim( $uf ) ) === $expected );
    }

    /**
     * Valida formato de CEP (8 dígitos numéricos).
     */
    public static function is_valid_cep_format( $cep ) {
        $cep = preg_replace( '/\D/', '', $cep );
        return ( strlen( $cep ) === 8 );
    }

    /**
     * Valida se UF é brasileira válida.
     */
    public static function is_valid_uf( $uf ) {
        return in_array( mb_strtoupper( trim( $uf ) ), self::VALID_UFS, true );
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
        // Verificar se campos de endereço estão habilitados no plugin
        $enabled_fields = GVN_Custom_Fields::get_enabled_fields();
        $enabled_keys   = wp_list_pluck( $enabled_fields, 'key' );

        // --- Validação de CEP ---
        if ( in_array( 'billing_postcode', $enabled_keys, true ) ) {
            $cep = isset( $data['billing_postcode'] ) ? $data['billing_postcode'] : '';
            $cep_clean = preg_replace( '/\D/', '', $cep );

            if ( ! empty( $cep_clean ) && ! self::is_valid_cep_format( $cep_clean ) ) {
                $errors->add( 'gvn_invalid_cep', '<strong>CEP</strong> deve conter exatamente 8 dígitos.' );
            }

            // --- Validação de UF ---
            if ( in_array( 'billing_state', $enabled_keys, true ) ) {
                $uf = isset( $data['billing_state'] ) ? $data['billing_state'] : '';

                if ( ! empty( $uf ) && ! self::is_valid_uf( $uf ) ) {
                    $errors->add( 'gvn_invalid_uf', '<strong>Estado (UF)</strong> inválido. Selecione um estado brasileiro válido.' );
                }

                // --- Consistência CEP ↔ UF ---
                if ( ! empty( $cep_clean ) && strlen( $cep_clean ) === 8 && ! empty( $uf ) && self::is_valid_uf( $uf ) ) {
                    if ( ! self::is_cep_consistent_with_uf( $cep_clean, $uf ) ) {
                        $expected_uf = self::get_uf_from_cep( $cep_clean );
                        $expected_name = isset( self::UF_NAMES[ $expected_uf ] ) ? self::UF_NAMES[ $expected_uf ] : $expected_uf;
                        $errors->add(
                            'gvn_cep_uf_mismatch',
                            sprintf(
                                '<strong>CEP</strong> %s pertence ao estado <strong>%s</strong>, mas o estado informado é diferente. Verifique o endereço.',
                                self::format_cep( $cep_clean ),
                                $expected_name
                            )
                        );
                    }
                }
            }
        }

        // --- Validação de cidade (não vazia se campo habilitado) ---
        if ( in_array( 'billing_city', $enabled_keys, true ) ) {
            $city = isset( $data['billing_city'] ) ? trim( $data['billing_city'] ) : '';
            if ( ! empty( $city ) && strlen( $city ) < 2 ) {
                $errors->add( 'gvn_invalid_city', '<strong>Cidade</strong> deve ter ao menos 2 caracteres.' );
            }
        }
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
        // Normalizar UF
        $uf = $order->get_billing_state();
        if ( ! empty( $uf ) ) {
            $normalized_uf = self::normalize_uf( $uf );
            if ( ! empty( $normalized_uf ) ) {
                $order->set_billing_state( $normalized_uf );
            }
        }

        // Normalizar cidade
        $city = $order->get_billing_city();
        if ( ! empty( $city ) ) {
            $order->set_billing_city( self::normalize_city( $city ) );
        }

        // Normalizar CEP (formato com máscara)
        $cep = $order->get_billing_postcode();
        if ( ! empty( $cep ) ) {
            $cep_clean = preg_replace( '/\D/', '', $cep );
            if ( strlen( $cep_clean ) === 8 ) {
                $order->set_billing_postcode( self::format_cep( $cep_clean ) );
            }
        }

        // Normalizar endereço (trim)
        $address = $order->get_billing_address_1();
        if ( ! empty( $address ) ) {
            $order->set_billing_address_1( trim( $address ) );
        }
    }
}
