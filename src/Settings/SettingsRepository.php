<?php

namespace GVN\Checkout\Settings;

use GVN\Checkout\Support\Features;

/**
 * Repositório central de leitura e persistência de configurações com cache por requisição e fallback seguro.
 */
class SettingsRepository {

    const OPTION_SETTINGS       = 'gvn_checkout_settings';
    const OPTION_FIELDS         = 'gvn_checkout_fields';
    const OPTION_SCHEMA_VERSION = 'gvn_checkout_schema_version';

    /**
     * @var array<string, mixed>|null Cache em memória das configurações na requisição atual.
     */
    private static $settings_cache = null;

    /**
     * @var array<int, array<string, mixed>>|null Cache em memória dos campos na requisição atual.
     */
    private static $fields_cache = null;

    /**
     * Limpa o cache em memória (útil para testes ou após gravações).
     */
    public static function flush_cache(): void {
        self::$settings_cache = null;
        self::$fields_cache   = null;
    }

    /**
     * Obtém a versão atual do schema de dados persistido no banco.
     *
     * @return int 0 se não versionado (legado), ou >= 1.
     */
    public static function get_schema_version(): int {
        return (int) get_option(self::OPTION_SCHEMA_VERSION, 0);
    }

    /**
     * Verifica se o banco já utiliza o schema unificado migrado.
     *
     * @return bool
     */
    public static function is_migrated(): bool {
        return self::get_schema_version() >= SettingsSchema::CURRENT_SCHEMA_VERSION;
    }

    /**
     * Obtém uma configuração específica com sanitização e fallback seguro.
     *
     * @param string $key
     * @param mixed  $default Valor padrão customizado caso não exista no schema.
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        $all = self::get_all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        $defaults = SettingsSchema::get_defaults();
        return $defaults[$key] ?? $default;
    }

    /**
     * Retorna todas as configurações estruturadas e sanitizadas.
     * Se a flag de novo schema estiver desligada ou as opções unificadas não existirem,
     * lê as opções individuais legadas aplicando sanitização e defaults seguros.
     *
     * @return array<string, mixed>
     */
    public static function get_all(): array {
        if (self::$settings_cache !== null) {
            return self::$settings_cache;
        }

        $use_unified = Features::is_enabled(Features::FLAG_NEW_SETTINGS_SCHEMA, false) && self::is_migrated();

        if ($use_unified) {
            $raw_settings = get_option(self::OPTION_SETTINGS, null);
            if (is_array($raw_settings)) {
                self::$settings_cache = SettingsSchema::sanitize_all_settings($raw_settings);
                return self::$settings_cache;
            }
        }

        // Leitura do caminho legado com sanitização centralizada e preenchimento de defaults
        $legacy_data = [];
        $defaults    = SettingsSchema::get_defaults();

        foreach ($defaults as $key => $default_val) {
            $val = get_option('gvn_checkout_' . $key, null);
            if ($val !== null) {
                $legacy_data[$key] = SettingsSchema::sanitize_setting($key, $val);
            } else {
                $legacy_data[$key] = $default_val;
            }
        }

        self::$settings_cache = $legacy_data;
        return self::$settings_cache;
    }

    /**
     * Retorna a lista de campos do checkout.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_fields(): array {
        if (self::$fields_cache !== null) {
            return self::$fields_cache;
        }

        $raw_fields = get_option(self::OPTION_FIELDS, null);
        if (is_array($raw_fields) && !empty($raw_fields)) {
            self::$fields_cache = $raw_fields;
            return self::$fields_cache;
        }

        self::$fields_cache = SettingsSchema::get_default_fields();
        return self::$fields_cache;
    }

    /**
     * Atualiza uma configuração individual.
     *
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public static function update(string $key, $value): bool {
        $current = self::get_all();
        $current[$key] = SettingsSchema::sanitize_setting($key, $value);

        return self::update_all($current);
    }

    /**
     * Persiste todas as configurações de forma segura e aditiva.
     *
     * @param array<string, mixed> $new_settings
     * @return bool
     */
    public static function update_all(array $new_settings): bool {
        $sanitized = SettingsSchema::sanitize_all_settings($new_settings);

        // Sempre atualiza o container unificado
        $saved = update_option(self::OPTION_SETTINGS, $sanitized);

        // Durante o período de transição, espelha nas opções legadas para preservar rollback seguro
        foreach ($sanitized as $key => $val) {
            update_option('gvn_checkout_' . $key, $val);
        }

        self::flush_cache();
        return (bool) $saved || true; // update_option retorna false se os valores forem idênticos
    }

    /**
     * Sincroniza opções individuais gravadas pela API de settings do WooCommerce
     * com o container unificado, preservando chaves não presentes no formulário.
     */
    public static function sync_from_legacy_options(): bool {
        $settings = self::get_all();

        foreach (SettingsSchema::get_defaults() as $key => $default_value) {
            $legacy_value = get_option('gvn_checkout_' . $key, null);
            if ($legacy_value !== null) {
                $settings[$key] = SettingsSchema::sanitize_setting($key, $legacy_value);
            } elseif (!array_key_exists($key, $settings)) {
                $settings[$key] = $default_value;
            }
        }

        return self::update_all($settings);
    }

    /**
     * Atualiza os campos do checkout.
     *
     * @param array<int, array<string, mixed>> $fields
     * @return bool
     */
    public static function update_fields(array $fields): bool {
        $saved = update_option(self::OPTION_FIELDS, $fields);
        self::flush_cache();
        return (bool) $saved || true;
    }
}
