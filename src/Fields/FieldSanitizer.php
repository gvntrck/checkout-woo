<?php

namespace GVN\Checkout\Fields;

/**
 * Sanitizador tipado e contextual para valores de campos de formulário.
 */
class FieldSanitizer {

    /**
     * Sanitiza um valor conforme o tipo do campo.
     *
     * @param string               $type
     * @param mixed                $value
     * @param array<string, mixed> $field
     * @return string
     */
    public static function sanitize(string $type, $value, array $field = []): string {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        $str_val = (string) $value;

        switch ($type) {
            case 'checkbox':
                return $str_val === '1' ? '1' : '0';

            case 'email':
                return function_exists('sanitize_email') ? sanitize_email($str_val) : (string) filter_var($str_val, FILTER_SANITIZE_EMAIL);

            case 'textarea':
                return function_exists('sanitize_textarea_field') ? sanitize_textarea_field($str_val) : trim(strip_tags($str_val));

            case 'select':
                $sanitized = function_exists('sanitize_text_field') ? sanitize_text_field($str_val) : trim(strip_tags($str_val));
                if (!empty($field['options'])) {
                    $parsed = self::parse_select_options((string) $field['options']);
                    if (!empty($parsed) && !array_key_exists($sanitized, $parsed)) {
                        return '';
                    }
                }
                return $sanitized;

            case 'number':
                $cleaned = preg_replace('/[^0-9.,\-]/', '', $str_val);
                return function_exists('sanitize_text_field') ? sanitize_text_field($cleaned ?? '') : trim(strip_tags($cleaned ?? ''));

            case 'tel':
            case 'date':
            case 'password':
            case 'text':
            default:
                return function_exists('sanitize_text_field') ? sanitize_text_field($str_val) : trim(strip_tags($str_val));
        }
    }

    /**
     * Converte a string de opções (uma por linha) em array associativo [valor => rótulo].
     *
     * @param string $options_string
     * @return array<string, string>
     */
    public static function parse_select_options(string $options_string): array {
        $options = [];
        if (empty($options_string)) {
            return $options;
        }

        $lines = array_filter(array_map('trim', explode("\n", $options_string)));

        foreach ($lines as $line) {
            if (strpos($line, '|') !== false) {
                [$val, $label] = array_map('trim', explode('|', $line, 2));
            } elseif (strpos($line, ' : ') !== false) {
                [$val, $label] = array_map('trim', explode(' : ', $line, 2));
            } else {
                $val   = function_exists('sanitize_title') ? sanitize_title($line) : strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $line));
                $label = $line;
                if ($val === '') {
                    $val = 'opt_' . substr(md5($line), 0, 8);
                }
            }

            if ($val !== '' && $label !== '') {
                $options[$val] = $label;
            }
        }

        return $options;
    }
}
