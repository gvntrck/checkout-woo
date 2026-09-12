<?php

declare(strict_types=1);

namespace GVN\Checkout\Lifecycle;

use GVN\Checkout\Settings\SettingsRepository;
use GVN\Checkout\Settings\SettingsSchema;
use GVN\Checkout\Support\Features;

/**
 * Gerenciador de desinstalação segura do plugin GVN Checkout.
 * Remove configurações, opções legadas e transients do plugin sem tocar em pedidos ou dados de clientes.
 */
final class Uninstaller
{
    /**
     * Lista de chaves de opções legadas para remoção.
     *
     * @return string[]
     */
    public static function get_legacy_option_keys(): array
    {
        $keys = [
            SettingsRepository::OPTION_SETTINGS,
            SettingsRepository::OPTION_FIELDS,
            SettingsRepository::OPTION_SCHEMA_VERSION,
            'gvn_checkout_default_fields_config',
            'gvn_checkout_version',
            'gvn_checkout_migrated_from_legacy',
            'gvn_checkout_migration_in_progress',
            'gvn_checkout_migration_lock',
            'gvn_checkout_migration_state',
            Features::FLAG_NEW_SETTINGS_SCHEMA,
            Features::FLAG_CANONICAL_FIELD_MANAGER,
            Features::FLAG_STRICT_ADDRESS_VALIDATION,
            Features::FLAG_CUSTOM_THANK_YOU_OVERRIDE,
            Features::FLAG_DIAGNOSTIC_MODE,
        ];

        foreach (array_keys(SettingsSchema::get_defaults()) as $setting_key) {
            $keys[] = 'gvn_checkout_' . $setting_key;
        }

        return array_values(array_unique($keys));
    }

    /**
     * Executa a limpeza completa na desinstalação.
     */
    public static function uninstall(): void
    {
        // 1. Remove todas as opções canônicas e legadas
        foreach (self::get_legacy_option_keys() as $option_key) {
            if (function_exists('delete_option')) {
                delete_option($option_key);
            }
        }

        // 2. Limpa cache em memória do SettingsRepository se disponível
        if (class_exists(SettingsRepository::class)) {
            SettingsRepository::flush_cache();
        }

        // 3. Limpeza segura de transients
        self::cleanup_transients();
    }

    /**
     * Limpa transients do plugin sem depender exclusivamente de queries SQL brutas não preparadas.
     */
    public static function cleanup_transients(): void
    {
        // Se houver suporte a wp_cache_flush (Redis/Memcached/APCu), invoca
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        global $wpdb;
        if (isset($wpdb) && is_object($wpdb) && !empty($wpdb->options) && method_exists($wpdb, 'query') && method_exists($wpdb, 'prepare')) {
            $prefix = '_transient_gvn_cep_%';
            $timeout_prefix = '_transient_timeout_gvn_cep_%';

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $prefix,
                    $timeout_prefix
                )
            );
        }
    }
}
