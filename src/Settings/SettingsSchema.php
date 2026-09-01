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
            'header_text'             => 'EFEAD - Conectando Saberes',
            'header_badge_text'       => 'COMPRA SEGURA',
            'header_bg_color'         => '#3a4759',
            'badge_bg_color'          => '#ff8a22',
            'title_text'              => '',
            'subtitle_text'           => '',
            'primary_color'           => '#0066d4',
            'button_color'            => '#ff8a22',
            'button_text'             => 'Finalizar pedido',
            'order_bump_enabled'      => 'no',
            'order_bump_product_id'   => 0,
            'order_bump_title'        => 'Oferta Exclusiva',
            'order_bump_description'  => 'Adicione este item ao seu pedido com condições especiais.',
            'order_bump_cta_text'     => 'Sim! Quero adicionar ao meu pedido',
            'order_bump_price'        => '',
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
            case 'thankyou_custom_url_enabled':
                if (is_bool($value)) {
                    return $value ? 'yes' : 'no';
                }
                $str = strtolower(trim((string) $value));
                return in_array($str, ['yes', '1', 'true'], true) ? 'yes' : 'no';

            case 'order_bump_product_id':
                $id = (int) $value;
                return ($id > 0) ? $id : 0;

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
