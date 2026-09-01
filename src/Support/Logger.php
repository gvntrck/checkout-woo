<?php

namespace GVN\Checkout\Support;

/**
 * Observabilidade e Logger seguro para o GVN Checkout for WooCommerce.
 * Mascara PII e credenciais por construção (allowlist e redaction).
 */
class Logger {

    const SOURCE = 'gvn-checkout';

    /**
     * Catálogo de códigos de eventos operacionais.
     */
    const EVENT_MIGRATION_START   = 'MIGRATION_START';
    const EVENT_MIGRATION_SUCCESS = 'MIGRATION_SUCCESS';
    const EVENT_MIGRATION_FAIL    = 'MIGRATION_FAIL';
    const EVENT_CONFIG_FALLBACK   = 'CONFIG_FALLBACK';
    const EVENT_TEMPLATE_OUTDATED = 'TEMPLATE_OUTDATED';
    const EVENT_CART_REJECTED     = 'CART_REJECTED';
    const EVENT_BUMP_UNAVAILABLE  = 'BUMP_UNAVAILABLE';
    const EVENT_CEP_FAIL          = 'CEP_FAIL';
    const EVENT_FLAG_CHANGE       = 'FLAG_CHANGE';

    /**
     * @var string|null Request ID efêmero por requisição.
     */
    private static $request_id = null;

    /**
     * Chaves proibidas que devem ser mascaradas se presentes no contexto.
     */
    private static $sensitive_keys = [
        'password', 'cpf', 'cnpj', 'cvv', 'card', 'token', 'secret',
        'order_key', 'nonce', 'authorization', 'email', 'phone', 'address_1'
    ];

    public static function get_request_id(): string {
        if (self::$request_id === null) {
            self::$request_id = bin2hex(random_bytes(8));
        }
        return self::$request_id;
    }

    /**
     * Registra mensagem no logger do WooCommerce se o modo diagnóstico ou log estiver ativo.
     *
     * @param string               $level   emergency|alert|critical|error|warning|notice|info|debug
     * @param string               $event   Código do evento
     * @param string               $message Mensagem descritiva
     * @param array<string, mixed> $context Contexto técnico adicional
     */
    public static function log(string $level, string $event, string $message, array $context = []): void {
        // Apenas erros críticos/warning são gravados por padrão; debug e info requerem flag de diagnóstico.
        if (in_array($level, ['debug', 'info'], true) && !Features::is_enabled(Features::FLAG_DIAGNOSTIC_MODE)) {
            return;
        }

        if (!function_exists('wc_get_logger')) {
            return;
        }

        $sanitized_context = self::sanitize_context(array_merge([
            'event_code' => $event,
            'request_id' => self::get_request_id(),
            'version'    => defined('GVN_CHECKOUT_VERSION') ? GVN_CHECKOUT_VERSION : 'unknown',
        ], $context));

        $logger = wc_get_logger();
        $formatted_message = sprintf('[%s] %s', $event, $message);

        $logger->log($level, $formatted_message, array_merge(['source' => self::SOURCE], $sanitized_context));
    }

    public static function info(string $event, string $message, array $context = []): void {
        self::log('info', $event, $message, $context);
    }

    public static function warning(string $event, string $message, array $context = []): void {
        self::log('warning', $event, $message, $context);
    }

    public static function error(string $event, string $message, array $context = []): void {
        self::log('error', $event, $message, $context);
    }

    /**
     * Sanitiza e remove campos sensíveis do contexto.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function sanitize_context(array $context): array {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $lower_key = strtolower($key);
            $is_sensitive = false;
            foreach (self::$sensitive_keys as $secret) {
                if (strpos($lower_key, $secret) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize_context($value);
            } elseif (is_scalar($value) || is_null($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = '[OBJECT]';
            }
        }
        return $sanitized;
    }
}
