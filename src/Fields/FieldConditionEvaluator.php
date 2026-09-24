<?php

namespace GVN\Checkout\Fields;

/**
 * Avaliador server-side de condições lógicas de exibição e obrigatoriedade de campos.
 */
class FieldConditionEvaluator {

    /**
     * Avalia uma regra individual contra o valor fornecido.
     *
     * @param array<string, mixed> $rule
     * @param mixed                $field_value
     * @return bool
     */
    public static function evaluate_rule(array $rule, $field_value): bool {
        $operator     = strtolower(trim((string) ($rule['operator'] ?? 'equals')));
        $target_value = (string) ($rule['value'] ?? '');
        $str_val      = is_scalar($field_value) ? (string) $field_value : '';

        switch ($operator) {
            case 'equals':
            case '==':
            case 'eq':
                return $str_val === $target_value;

            case 'not_equals':
            case '!=':
            case 'neq':
                return $str_val !== $target_value;

            case 'filled':
                return trim($str_val) !== '';

            case 'empty':
                return trim($str_val) === '';

            case 'contains':
                if ($target_value === '') {
                    return true;
                }
                return self::contains_case_insensitive($str_val, $target_value);

            case 'greater':
            case '>':
                return is_numeric($str_val) && is_numeric($target_value) && ((float) $str_val > (float) $target_value);

            case 'less':
            case '<':
                return is_numeric($str_val) && is_numeric($target_value) && ((float) $str_val < (float) $target_value);

            default:
                return false;
        }
    }

    /**
     * Avalia a estrutura de condições completa contra os dados submetidos.
     *
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function evaluate(array $conditions, array $data): bool {
        if (empty($conditions)) {
            return true;
        }

        // Suporte a lista direta de regras (compatibilidade com schemas em array numérico)
        $rules = [];
        $logic = 'and';

        if (isset($conditions['rules']) && is_array($conditions['rules'])) {
            $rules = $conditions['rules'];
            $logic = strtolower((string) ($conditions['logic'] ?? 'and'));
        } elseif (isset($conditions[0]) && is_array($conditions[0])) {
            $rules = $conditions;
        }

        if (empty($rules)) {
            return true;
        }

        $is_or = ($logic === 'or');

        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['field'])) {
                continue;
            }

            $trigger_key = (string) $rule['field'];
            $field_val   = $trigger_key === 'cart_item_count'
                ? (function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0)
                : ($data[$trigger_key] ?? '');
            $matched     = self::evaluate_rule($rule, $field_val);

            if ($is_or && $matched) {
                return true;
            }

            if (!$is_or && !$matched) {
                return false;
            }
        }

        return !$is_or;
    }

    /**
     * Verifica se um campo está visível segundo suas condições e os dados informados.
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $data
     * @return bool
     */
    public static function is_field_visible(array $field, array $data): bool {
        $conditions = $field['conditions'] ?? null;
        if (empty($conditions) || !is_array($conditions)) {
            return true;
        }

        return self::evaluate($conditions, $data);
    }

    /**
     * Mantém a avaliação de condições disponível em instalações sem mbstring.
     */
    private static function contains_case_insensitive(string $value, string $needle): bool {
        if (function_exists('mb_stripos')) {
            return mb_stripos($value, $needle) !== false;
        }

        return stripos($value, $needle) !== false;
    }
}
