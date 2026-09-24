<?php

namespace GVN\Checkout\Fields;

use GVN\Checkout\Payments\GatewayRequirementsResolver;

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
            if (($field['type'] ?? '') === 'html') {
                continue;
            }
            if (empty($field['enabled'])) {
                continue;
            }

            $key   = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $type  = (string) ($field['type'] ?? 'text');
            $mask  = (string) ($field['mask'] ?? '');

            if ($key === '') {
                continue;
            }

            // Chaves duplicadas (mesmo destino, exibição alternada): se a primeira
            // ocorrência já registrou erro, não repete a notice para a mesma chave.
            if (isset($validation_errors[$key])) {
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
                } elseif ($mask === 'cpf' && !self::is_valid_cpf($value_str)) {
                    $message = sprintf(
                        /* translators: %s: Field label */
                        __('O campo "%s" deve conter um CPF válido.', 'gvn-checkout'),
                        $label
                    );
                    $validation_errors[$key] = $message;
                    self::add_error($errors, $key, $message);
                }
            }
        }

        self::validate_gateway_requirements($fields, $posted_data, $errors, $validation_errors);

        return $validation_errors;
    }

    /**
     * Validates Brazilian CPF check digits after removing visual mask characters.
     */
    private static function is_valid_cpf(string $value): bool {
        $cpf = preg_replace('/\D/', '', $value);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * (($position + 1) - $index);
            }

            $digit = ($sum * 10) % 11;
            if ($digit === 10) {
                $digit = 0;
            }
            if ($digit !== (int) $cpf[$position]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida requisitos confirmados por uma integração de gateway.
     *
     * Requisitos desconhecidos nunca bloqueiam o checkout. Quando um gateway
     * declara um campo como obrigatório, a validação continua server-side mesmo
     * que o campo tenha sido marcado como opcional no editor do GVN.
     *
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed>                 $posted_data
     * @param object|null                          $errors
     * @param array<string, string>                $validation_errors
     * @return void
     */
    private static function validate_gateway_requirements(array $fields, array $posted_data, $errors, array &$validation_errors): void {
        $gateway_id = sanitize_key((string) ($posted_data['payment_method'] ?? ''));
        if ($gateway_id === '') {
            return;
        }

        $resolver     = new GatewayRequirementsResolver();
        $requirements = $resolver->get_active_requirements_for_gateway($gateway_id, $posted_data);
        if (empty($requirements)) {
            return;
        }

        $fields_by_key = [];
        foreach ($fields as $field) {
            if (is_array($field) && !empty($field['key'])) {
                $fields_by_key[(string) $field['key']] = $field;
            }
        }

        foreach ($requirements as $requirement) {
            if (($requirement['requirement'] ?? '') !== GatewayRequirementsResolver::REQUIREMENT_REQUIRED) {
                continue;
            }

            // A requirement confirmed for the selected gateway takes
            // precedence over the editor's visibility condition. The resolver
            // already evaluated the gateway's own `when` rule; hiding the
            // configured field here would make the server-side protection
            // bypassable.
            $key   = (string) ($requirement['field_key'] ?? '');
            $field = $fields_by_key[$key] ?? null;
            $raw_value = $posted_data[$key] ?? '';
            $value_str = is_scalar($raw_value) ? trim((string) $raw_value) : '';
            if ($value_str !== '' || isset($validation_errors[$key])) {
                continue;
            }

            $label = is_array($field) && !empty($field['label'])
                ? (string) $field['label']
                : (string) ($requirement['field_key'] ?? $key);
            $message = sprintf(
                /* translators: 1: Field label, 2: payment method ID. */
                __('O campo "%1$s" é obrigatório para o método de pagamento "%2$s".', 'gvn-checkout'),
                $label,
                $gateway_id
            );

            $validation_errors[$key] = $message;
            self::add_error($errors, $key . '_gateway', $message);
        }
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
