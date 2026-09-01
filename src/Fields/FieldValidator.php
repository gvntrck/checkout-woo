<?php

namespace GVN\Checkout\Fields;

/**
 * Validador server-side de campos de checkout respeitando visibilidade condicional e obrigatoriedade.
 */
class FieldValidator {

    /**
     * Valida um conjunto de campos configurados contra os dados submetidos.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed>             $posted_data
     * @param object|null                      $errors Instância de WP_Error quando executado em hook do WooCommerce.
     * @return array<string, string> Lista de erros encontrados [chave_campo => mensagem_erro].
     */
    public static function validate(array $fields, array $posted_data, $errors = null): array {
        $validation_errors = [];

        foreach ($fields as $field) {
            if (empty($field['enabled'])) {
                continue;
            }

            $key   = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $type  = (string) ($field['type'] ?? 'text');

            if ($key === '') {
                continue;
            }

            // 1. Avalia visibilidade efetiva no servidor
            $is_visible = FieldConditionEvaluator::is_field_visible($field, $posted_data);

            // Campos ocultos por condição nunca devem ser exigidos
            if (!$is_visible) {
                continue;
            }

            $raw_value = $posted_data[$key] ?? '';
            $value_str = is_scalar($raw_value) ? trim((string) $raw_value) : '';

            // 2. Validação de obrigatoriedade em campos visíveis
            $is_required = !empty($field['required']);
            if ($is_required && $value_str === '') {
                $message = sprintf(
                    /* translators: %s: Nome do campo */
                    __('O campo "%s" é obrigatório.', 'gvn-checkout'),
                    $label
                );
                $validation_errors[$key] = $message;
                self::add_error($errors, $key, $message);
                continue;
            }

            // 3. Validações específicas por tipo quando preenchido
            if ($value_str !== '') {
                if ($type === 'email' && function_exists('is_email') && !is_email($value_str)) {
                    $message = sprintf(
                        /* translators: %s: Nome do campo */
                        __('O campo "%s" deve conter um e-mail válido.', 'gvn-checkout'),
                        $label
                    );
                    $validation_errors[$key] = $message;
                    self::add_error($errors, $key, $message);
                }
            }
        }

        return $validation_errors;
    }

    /**
     * Adiciona o erro ao objeto WP_Error ou notice do WooCommerce quando aplicável.
     *
     * @param object|null $errors
     * @param string      $field_key
     * @param string      $message
     */
    private static function add_error($errors, string $field_key, string $message): void {
        if (is_object($errors) && method_exists($errors, 'add')) {
            $errors->add('gvn_' . $field_key . '_required', $message);
        } elseif (function_exists('wc_add_notice')) {
            wc_add_notice($message, 'error');
        }
    }
}
