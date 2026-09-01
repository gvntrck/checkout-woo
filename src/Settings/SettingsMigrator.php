<?php

namespace GVN\Checkout\Settings;

use GVN\Checkout\Support\Features;
use GVN\Checkout\Support\Logger;

/**
 * Migrador seguro, aditivo e idempotente de configurações legadas para o schema unificado.
 */
class SettingsMigrator {

    const OPTION_LOCK            = 'gvn_checkout_migration_lock';
    const OPTION_MIGRATION_STATE = 'gvn_checkout_migration_state';
    const LOCK_TTL_SECONDS       = 60; // Expiração de lock órfão

    /**
     * Executa a migração das configurações legadas para o schema unificado.
     *
     * @return bool True se a migração foi concluída com sucesso ou já estava concluída.
     */
    public static function migrate(): bool {
        // Idempotência: se já está migrado, não reexecuta
        if (SettingsRepository::is_migrated()) {
            return true;
        }

        // Aquisição de lock não bloqueante
        if (!self::acquire_lock()) {
            Logger::warning(Logger::EVENT_MIGRATION_FAIL, 'Migração ignorada: lock ativo em execução concorrente.');
            return false;
        }

        Logger::info(Logger::EVENT_MIGRATION_START, 'Iniciando migração aditiva de configurações legadas.');

        self::set_migration_state([
            'status'     => 'in_progress',
            'started_at' => time(),
            'target'     => SettingsSchema::CURRENT_SCHEMA_VERSION,
        ]);

        try {
            // 1. Lê todas as opções legadas existentes aplicando sanitização e defaults
            $migrated_settings = SettingsRepository::get_all();

            // 2. Valida invariantes essenciais (todos os defaults estão presentes)
            $required_defaults = SettingsSchema::get_defaults();
            foreach ($required_defaults as $key => $default_val) {
                if (!array_key_exists($key, $migrated_settings)) {
                    throw new \RuntimeException("Invariante violada: chave '{$key}' ausente após sanitização.");
                }
            }

            // 3. Grava o container unificado (preservando as opções legadas intactas para rollback)
            update_option(SettingsRepository::OPTION_SETTINGS, $migrated_settings);

            // 4. Read-back de verificação
            $read_back = get_option(SettingsRepository::OPTION_SETTINGS, null);
            if (!is_array($read_back) || empty($read_back)) {
                throw new \RuntimeException('Falha no read-back: container unificado não foi persistido corretamente.');
            }

            // 5. Garante que os campos também existam
            if (false === get_option(SettingsRepository::OPTION_FIELDS)) {
                update_option(SettingsRepository::OPTION_FIELDS, SettingsSchema::get_default_fields());
            }

            // 6. Atualiza a versão do schema e ativa a feature flag de novo schema
            update_option(SettingsRepository::OPTION_SCHEMA_VERSION, SettingsSchema::CURRENT_SCHEMA_VERSION);
            Features::set(Features::FLAG_NEW_SETTINGS_SCHEMA, true);

            // 7. Registra estado de sucesso
            self::set_migration_state([
                'status'       => 'completed',
                'completed_at' => time(),
                'version'      => SettingsSchema::CURRENT_SCHEMA_VERSION,
                'checksum'     => md5(serialize($read_back)),
            ]);

            SettingsRepository::flush_cache();
            Logger::info(Logger::EVENT_MIGRATION_SUCCESS, 'Migração de configurações concluída com sucesso.');

            self::release_lock();
            return true;

        } catch (\Throwable $e) {
            // Em falha: mantém checkpoint retomável, libera o lock e preserva o caminho legado
            self::set_migration_state([
                'status'     => 'failed',
                'failed_at'  => time(),
                'error_code' => $e->getCode(),
                'error_msg'  => $e->getMessage(),
            ]);

            Logger::error(Logger::EVENT_MIGRATION_FAIL, 'Falha durante a migração de configurações: ' . $e->getMessage());
            self::release_lock();
            return false;
        }
    }

    /**
     * Tenta adquirir o lock de migração com expiração segura contra travamento.
     *
     * @return bool
     */
    private static function acquire_lock(): bool {
        $now       = time();
        $lock_time = (int) get_option(self::OPTION_LOCK, 0);

        if ($lock_time > 0 && ($now - $lock_time) < self::LOCK_TTL_SECONDS) {
            return false; // Lock ativo
        }

        return update_option(self::OPTION_LOCK, $now);
    }

    /**
     * Libera o lock de migração.
     */
    private static function release_lock(): void {
        delete_option(self::OPTION_LOCK);
    }

    /**
     * Grava o estado da migração sem PII.
     *
     * @param array<string, mixed> $state
     */
    private static function set_migration_state(array $state): void {
        update_option(self::OPTION_MIGRATION_STATE, $state);
    }

    /**
     * Retorna o estado atual da migração.
     *
     * @return array<string, mixed>|null
     */
    public static function get_migration_state(): ?array {
        $state = get_option(self::OPTION_MIGRATION_STATE, null);
        return is_array($state) ? $state : null;
    }
}
