<?php

namespace GVN\Checkout\Fields;

/**
 * Persistência atômica e HPOS-safe de campos customizados no objeto WC_Order.
 */
class FieldOrderPersister {

    /**
     * Persiste campos customizados no pedido respeitando segurança, allowlist e visibilidade condicional.
     *
     * @param object                           $order Instância de WC_Order.
     * @param array<int, array<string, mixed>> $fields Lista de campos configurados.
     * @param array<string, mixed>             $posted_data Dados submetidos da requisição.
     * @return array<string, string> Mapa dos metadados persistidos [meta_key => valor_salvo].
     */
    public static function persist(object $order, array $fields, array $posted_data): array {
        $persisted = [];

        // Identifica o pedido como originado no GVN Checkout para isolamento da Thank You
        if (method_exists($order, 'update_meta_data')) {
            $version = defined('GVN_CHECKOUT_VERSION') ? GVN_CHECKOUT_VERSION : '1.0.0';
            $order->update_meta_data('_gvn_checkout_version', $version);
            $order->update_meta_data('_gvn_checkout', 'yes');
        }

        foreach ($fields as $field) {
            if (!FieldSecurityPolicy::is_persistable_custom_field($field)) {
                continue;
            }

            $raw_key  = (string) ($field['key'] ?? '');
            $key      = FieldSecurityPolicy::sanitize_field_key($raw_key);
            $meta_key = '_' . $key;

            // 1. Remove valores residuais dos campos ocultos por condição.
            // Passada separada: com chaves duplicadas (mesmo destino, exibição
            // alternada), a exclusão nunca pode acontecer depois da persistência
            // da ocorrência visível.
            if (!FieldConditionEvaluator::is_field_visible($field, $posted_data)) {
                if (method_exists($order, 'delete_meta_data')) {
                    $order->delete_meta_data($meta_key);
                }
                continue;
            }
        }

        foreach ($fields as $field) {
            if (!FieldSecurityPolicy::is_persistable_custom_field($field)) {
                continue;
            }

            $raw_key  = (string) ($field['key'] ?? '');
            $key      = FieldSecurityPolicy::sanitize_field_key($raw_key);
            $meta_key = '_' . $key;
            $type     = (string) ($field['type'] ?? 'text');

            // 2. Se visível e enviado, sanitiza e persiste (a última ocorrência
            // visível vence em caso de duplicadas visíveis por erro de config).
            if (!FieldConditionEvaluator::is_field_visible($field, $posted_data)) {
                continue;
            }

            if (array_key_exists($key, $posted_data) || $type === 'checkbox') {
                $sanitized_value = FieldSanitizer::sanitize($type, $posted_data[$key] ?? '', $field);

                if (method_exists($order, 'update_meta_data')) {
                    $order->update_meta_data($meta_key, $sanitized_value);
                    $persisted[$meta_key] = $sanitized_value;
                }
            }
        }

        return $persisted;
    }
}
