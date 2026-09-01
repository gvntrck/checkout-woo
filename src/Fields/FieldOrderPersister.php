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

        foreach ($fields as $field) {
            if (!FieldSecurityPolicy::is_persistable_custom_field($field)) {
                continue;
            }

            $raw_key  = (string) ($field['key'] ?? '');
            $key      = FieldSecurityPolicy::sanitize_field_key($raw_key);
            $meta_key = '_' . $key;
            $type     = (string) ($field['type'] ?? 'text');

            // 1. Avalia se o campo está efetivamente visível no momento do submit
            $is_visible = FieldConditionEvaluator::is_field_visible($field, $posted_data);

            if (!$is_visible) {
                // Remove qualquer valor residual no pedido caso o campo tenha ficado oculto
                if (method_exists($order, 'delete_meta_data')) {
                    $order->delete_meta_data($meta_key);
                }
                continue;
            }

            // 2. Se visível e enviado, sanitiza e persiste
            if (array_key_exists($key, $posted_data)) {
                $sanitized_value = FieldSanitizer::sanitize($type, $posted_data[$key], $field);

                if (method_exists($order, 'update_meta_data')) {
                    $order->update_meta_data($meta_key, $sanitized_value);
                    $persisted[$meta_key] = $sanitized_value;
                }
            }
        }

        return $persisted;
    }
}
