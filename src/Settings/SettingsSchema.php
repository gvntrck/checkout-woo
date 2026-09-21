<?php

namespace GVN\Checkout\Settings;

/**
 * Schema declarativo, tipos, defaults e sanitizadores para configurações do GVN Checkout.
 */
class SettingsSchema {

    const CURRENT_SCHEMA_VERSION = 1;

    /**
     * Retorna os valores padrão de todas as configurações.
     *
     * @return array<string, mixed>
     */
    public static function get_defaults(): array {
        return [
            'checkout_layout'         => 'classic',
            'header_enabled'          => 'yes',
            'header_text'             => 'EFEAD - Conectando Saberes',
            'header_badge_text'       => 'COMPRA SEGURA',
            'header_bg_color'         => '#3a4759',
            'badge_bg_color'          => '#ff8a22',
            'title_text'              => '',
            'subtitle_text'           => '',
            'primary_color'           => '#0066d4',
            'button_color'            => '#ff8a22',
            'button_text'             => 'Finalizar pedido',
            'privacy_policy_text'     => 'Os seus dados pessoais serão utilizados para processar a sua compra, apoiar a sua experiência em todo este site e para outros fins descritos na nossa',
            'typography_preset'       => 'normal',
            'font_size_body'          => '',
            'font_size_label'         => '',
            'font_size_section_title' => '',
            'font_size_page_title'    => '',
            'font_size_price'         => '',
            'coupon_enabled'          => 'yes',
            'order_bump_enabled'      => 'no',
            'order_bump_product_id'   => 0,
            'order_bump_title'        => 'Oferta Exclusiva',
            'order_bump_description'  => 'Adicione este item ao seu pedido com condições especiais.',
            'order_bump_cta_text'     => 'Sim! Quero adicionar ao meu pedido',
            'order_bump_price'        => '',
            'thankyou_success_title'  => 'Pedido recebido!',
            'thankyou_success_message' => 'Obrigado pela sua compra. Seu pedido foi registrado com sucesso.',
            'thankyou_failed_title'   => 'Pagamento não processado',
            'thankyou_failed_message' => 'Infelizmente seu pagamento não pôde ser processado. Tente novamente ou entre em contato conosco.',
            'thankyou_retry_text'     => 'Tentar novamente',
            'thankyou_not_found_title' => 'Pedido não encontrado',
            'thankyou_not_found_message' => 'Não foi possível localizar seu pedido. Verifique se o link está correto ou entre em contato conosco.',
            'thankyou_not_found_button_text' => 'Voltar à loja',
            'thankyou_payment_title'  => 'INSTRUÇÕES DE PAGAMENTO',
            'thankyou_items_title'    => 'ITENS DO PEDIDO',
            'thankyou_customer_title' => 'SEUS DADOS',
            'thankyou_orders_button_text' => 'Ver meus pedidos',
            'thankyou_shop_button_text' => 'Continuar comprando',
            'thankyou_custom_url'     => '',
            'thankyou_custom_url_enabled' => 'no',
        ];
    }

    /**
     * Retorna o schema padrão dos campos de checkout brasileiros.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_default_fields(): array {
        return [
            [
                'key'         => 'billing_persontype',
                'label'       => 'Tipo de Pessoa',
                'type'        => 'select',
                'required'    => true,
                'width'       => '100',
                'position'    => 'billing',
                'placeholder' => '',
                'enabled'     => true,
                'is_default'  => true,
                'options'     => "1 : Pessoa Física\n2 : Pessoa Jurídica",
                'default_option' => '1',
            ],
            [
                'key'         => 'billing_cpf',
                'label'       => 'CPF',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 'billing',
                'placeholder' => '000.000.000-00',
                'enabled'     => true,
                'mask'        => 'cpf',
                'is_default'  => true,
                'conditions'  => [
                    [
                        'field'    => 'billing_persontype',
                        'operator' => '==',
                        'value'    => '1',
                    ],
                ],
            ],
            [
                'key'         => 'billing_cnpj',
                'label'       => 'CNPJ',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 'billing',
                'placeholder' => '00.000.000/0000-00',
                'enabled'     => true,
                'mask'        => 'cnpj',
                'is_default'  => true,
                'conditions'  => [
                    [
                        'field'    => 'billing_persontype',
                        'operator' => '==',
                        'value'    => '2',
                    ],
                ],
            ],
            [
                'key'         => 'billing_company',
                'label'       => 'Razão Social',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 'billing',
                'placeholder' => '',
                'enabled'     => true,
                'is_default'  => true,
                'conditions'  => [
                    [
                        'field'    => 'billing_persontype',
                        'operator' => '==',
                        'value'    => '2',
                    ],
                ],
            ],
            [
                'key'         => 'billing_ie',
                'label'       => 'Inscrição Estadual',
                'type'        => 'text',
                'required'    => false,
                'width'       => '50',
                'position'    => 'billing',
                'placeholder' => '',
                'enabled'     => true,
                'is_default'  => true,
                'conditions'  => [
                    [
                        'field'    => 'billing_persontype',
                        'operator' => '==',
                        'value'    => '2',
                    ],
                ],
            ],
            [
                'key'         => 'billing_postcode',
                'label'       => 'CEP',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 'billing',
                'placeholder' => '00000-000',
                'enabled'     => true,
                'mask'        => 'cep',
                'is_default'  => true,
            ],
            [
                'key'         => 'billing_city',
                'label'       => 'Cidade',
                'type'        => 'text',
                'required'    => true,
                'width'       => '50',
                'position'    => 'billing',
                'placeholder' => 'Cidade',
                'enabled'     => true,
                'is_default'  => true,
            ],
            [
                'key'         => 'billing_number',
                'label'       => 'Número',
                'type'        => 'text',
                'required'    => true,
                'width'       => '30',
                'position'    => 'billing',
                'placeholder' => 'Nº',
                'enabled'     => true,
                'is_default'  => true,
            ],
            [
                'key'         => 'billing_neighborhood',
                'label'       => 'Bairro',
                'type'        => 'text',
                'required'    => true,
                'width'       => '70',
                'position'    => 'billing',
                'placeholder' => 'Bairro',
                'enabled'     => true,
                'is_default'  => true,
            ],
            [
                'key'         => 'billing_cellphone',
                'label'       => 'Celular / WhatsApp',
                'type'        => 'tel',
                'required'    => true,
                'width'       => '50',
                'position'    => 'billing',
                'placeholder' => '(00) 00000-0000',
                'enabled'     => true,
                'mask'        => 'phone',
                'is_default'  => true,
            ],
        ];
    }

    /**
     * Sanitiza um valor individual de configuração pelo nome da chave.
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    public static function sanitize_setting(string $key, $value) {
        $defaults = self::get_defaults();
        $fallback = $defaults[$key] ?? '';

        switch ($key) {
            case 'header_bg_color':
            case 'badge_bg_color':
            case 'primary_color':
            case 'button_color':
                $sanitized = sanitize_hex_color((string) $value);
                return !empty($sanitized) ? $sanitized : $fallback;

            case 'order_bump_enabled':
            case 'coupon_enabled':
            case 'header_enabled':
            case 'thankyou_custom_url_enabled':
                if (is_bool($value)) {
                    return $value ? 'yes' : 'no';
                }
                $str = strtolower(trim((string) $value));
                return in_array($str, ['yes', '1', 'true'], true) ? 'yes' : 'no';

            case 'order_bump_product_id':
                $id = (int) $value;
                return ($id > 0) ? $id : 0;

            case 'checkout_layout':
                $slug = function_exists( 'sanitize_key' )
                    ? sanitize_key( (string) $value )
                    : strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
                if ( '' === $slug ) {
                    return $fallback;
                }
                if ( class_exists( 'GVN\Checkout\Layouts\LayoutRegistry' ) ) {
                    return \GVN\Checkout\Layouts\LayoutRegistry::resolve( $slug );
                }
                return $slug;

            case 'typography_preset':
                $preset = sanitize_key( (string) $value );
                return in_array( $preset, array( 'compact', 'normal', 'large' ), true ) ? $preset : $fallback;

            case 'font_size_body':
            case 'font_size_label':
            case 'font_size_section_title':
            case 'font_size_page_title':
            case 'font_size_price':
                if ( '' === trim( (string) $value ) ) {
                    return '';
                }
                $size = (float) str_replace( ',', '.', (string) $value );
                return ( $size >= 10 && $size <= 48 ) ? (string) $size : $fallback;

            case 'order_bump_price':
                if (empty($value)) {
                    return '';
                }
                $cleaned = str_replace(',', '.', trim((string) $value));
                return is_numeric($cleaned) && (float) $cleaned >= 0 ? (string) $cleaned : '';

            case 'thankyou_custom_url':
                $url = filter_var((string) $value, FILTER_SANITIZE_URL);
                return (filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) ? $url : '';

            case 'header_text':
            case 'header_badge_text':
            case 'title_text':
            case 'subtitle_text':
            case 'button_text':
            case 'order_bump_title':
            case 'order_bump_description':
            case 'order_bump_cta_text':
            case 'thankyou_success_title':
            case 'thankyou_success_message':
            case 'thankyou_failed_title':
            case 'thankyou_failed_message':
            case 'thankyou_retry_text':
            case 'thankyou_not_found_title':
            case 'thankyou_not_found_message':
            case 'thankyou_not_found_button_text':
            case 'thankyou_payment_title':
            case 'thankyou_items_title':
            case 'thankyou_customer_title':
            case 'thankyou_orders_button_text':
            case 'thankyou_shop_button_text':
            case 'privacy_policy_text':
                return wp_kses_post((string) $value);

            default:
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * Sanitiza e normaliza um array completo de configurações, preenchendo defaults para valores ausentes.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize_all_settings(array $input): array {
        $defaults = self::get_defaults();
        $output   = [];

        foreach ($defaults as $key => $default_value) {
            if (array_key_exists($key, $input)) {
                $output[$key] = self::sanitize_setting($key, $input[$key]);
            } else {
                $output[$key] = $default_value;
            }
        }

        return $output;
    }
}
