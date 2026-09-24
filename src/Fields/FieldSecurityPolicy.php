<?php

namespace GVN\Checkout\Fields;

/**
 * Política de segurança, allowlists de tipos e bloqueio de metadados reservados.
 */
class FieldSecurityPolicy {

    /**
     * @var array<string> Tipos válidos permitidos para campos de checkout.
     */
    private const ALLOWED_TYPES = [
        'text',
        'email',
        'tel',
        'number',
        'textarea',
        'select',
        'date',
        'password',
        'html',
    ];

    /**
     * @var array<string> Chaves e metadados reservados do WooCommerce e do pedido que nunca devem ser sobrescritos por campos customizados.
     */
    private const RESERVED_KEYS = [
        '_order_total',
        '_order_subtotal',
        '_order_tax',
        '_order_shipping',
        '_order_discount',
        '_order_currency',
        '_order_version',
        '_prices_include_tax',
        '_payment_method',
        '_payment_method_title',
        '_transaction_id',
        '_customer_user',
        '_order_key',
        '_order_stock_reduced',
        '_date_paid',
        '_date_completed',
        '_status',
        '_created_via',
        '_cart_hash',
        '_customer_ip_address',
        '_customer_user_agent',
        '_recorded_sales',
        '_recorded_coupon_usage_counts',
        '_download_permissions_granted',
        'total',
        'subtotal',
        'status',
        'payment_method',
        'transaction_id',
        'order_key',
        'id',
        'order_id',
        'customer_user',
        'currency',
        'date_created',
        'date_modified',
        'date_paid',
        'date_completed',
    ];

    /**
     * @var array<string> Chaves nativas do formulário do WooCommerce que são persistidas diretamente pelos métodos core.
     */
    private const NATIVE_WOO_KEYS = [
        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',
        'billing_email',
        'billing_phone',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'order_comments',
    ];

    /**
     * Verifica se o tipo do campo pertence à allowlist de tipos seguros.
     *
     * @param string $type
     * @return bool
     */
    public static function is_valid_type(string $type): bool {
        return in_array(strtolower(trim($type)), self::ALLOWED_TYPES, true);
    }

    /**
     * Verifica se a chave fornecida é uma chave reservada ou perigosa para persistência.
     *
     * @param string $key
     * @return bool
     */
    public static function is_reserved_key(string $key): bool {
        $normalized = strtolower(trim($key));
        if ($normalized === '') {
            return true;
        }

        // Chaves que começam com sublinhado ou contêm metadados internos
        if (in_array($normalized, self::RESERVED_KEYS, true)) {
            return true;
        }

        // Também verifica sem o prefixo '_' se estiver na lista reservada
        $unprefixed = ltrim($normalized, '_');
        if (in_array($unprefixed, self::RESERVED_KEYS, true)) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se a chave é um campo nativo do WooCommerce.
     *
     * @param string $key
     * @return bool
     */
    public static function is_native_woo_key(string $key): bool {
        return in_array(strtolower(trim($key)), self::NATIVE_WOO_KEYS, true);
    }

    /**
     * Sanitiza a chave do campo garantindo formato seguro (apenas [a-z0-9_\-]).
     *
     * @param string $key
     * @return string
     */
    public static function sanitize_field_key(string $key): string {
        $raw = strtolower(trim($key));
        $sanitized = preg_replace('/[^a-z0-9_\-]/', '', $raw);
        return $sanitized ?? '';
    }

    /**
     * Determina se um campo é válido e persistível como metadado customizado de pedido.
     *
     * @param array<string, mixed> $field
     * @return bool
     */
    public static function is_persistable_custom_field(array $field): bool {
        if (($field['type'] ?? '') === 'html') {
            return false;
        }
        if (empty($field['enabled'])) {
            return false;
        }

        $key = self::sanitize_field_key((string) ($field['key'] ?? ''));
        if ($key === '') {
            return false;
        }

        if (self::is_reserved_key($key)) {
            return false;
        }

        if (self::is_native_woo_key($key)) {
            return false;
        }

        $type = (string) ($field['type'] ?? 'text');
        if (!self::is_valid_type($type)) {
            return false;
        }

        return true;
    }
}
