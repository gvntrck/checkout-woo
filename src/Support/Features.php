<?php

namespace GVN\Checkout\Support;

/**
 * Gerenciador de Feature Flags internas do GVN Checkout for WooCommerce.
 */
class Features {

    const FLAG_NEW_SETTINGS_SCHEMA        = 'gvn_flag_new_settings_schema';
    const FLAG_CANONICAL_FIELD_MANAGER    = 'gvn_flag_canonical_field_manager';
    const FLAG_STRICT_ADDRESS_VALIDATION  = 'gvn_flag_strict_address_validation';
    const FLAG_CUSTOM_THANK_YOU_OVERRIDE  = 'gvn_flag_custom_thank_you_override';
    const FLAG_DIAGNOSTIC_MODE            = 'gvn_flag_diagnostic_mode';

    /**
     * Retorna os defaults para uma instalação limpa (Clean Install).
     *
     * @return array<string, bool>
     */
    public static function get_clean_install_defaults(): array {
        return [
            self::FLAG_NEW_SETTINGS_SCHEMA        => true,
            self::FLAG_CANONICAL_FIELD_MANAGER    => true,
            self::FLAG_STRICT_ADDRESS_VALIDATION  => false,
            self::FLAG_CUSTOM_THANK_YOU_OVERRIDE  => false,
            self::FLAG_DIAGNOSTIC_MODE            => false,
        ];
    }

    /**
     * Retorna os defaults para um upgrade de versão legada.
     * Flags estruturais permanecem desligadas até que a migração de dados seja concluída com sucesso.
     *
     * @return array<string, bool>
     */
    public static function get_upgrade_defaults(): array {
        return [
            self::FLAG_NEW_SETTINGS_SCHEMA        => false,
            self::FLAG_CANONICAL_FIELD_MANAGER    => false,
            self::FLAG_STRICT_ADDRESS_VALIDATION  => false,
            self::FLAG_CUSTOM_THANK_YOU_OVERRIDE  => false,
            self::FLAG_DIAGNOSTIC_MODE            => false,
        ];
    }

    /**
     * Verifica se uma flag específica está habilitada.
     *
     * @param string $flag_name
     * @param bool   $default
     * @return bool
     */
    public static function is_enabled(string $flag_name, bool $default = false): bool {
        $value = get_option($flag_name, null);
        if ($value === null) {
            return $default;
        }
        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Habilita ou desabilita uma flag.
     *
     * @param string $flag_name
     * @param bool   $enabled
     * @return bool
     */
    public static function set(string $flag_name, bool $enabled): bool {
        return update_option($flag_name, $enabled ? 'yes' : 'no');
    }
}
