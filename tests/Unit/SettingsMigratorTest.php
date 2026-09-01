<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Settings\SettingsMigrator;
use GVN\Checkout\Settings\SettingsRepository;
use GVN\Checkout\Settings\SettingsSchema;
use GVN\Checkout\Support\Features;
use PHPUnit\Framework\TestCase;

class SettingsMigratorTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options;
        $wp_mock_options = [];
        SettingsRepository::flush_cache();
    }

    public function test_migrate_converts_legacy_options_to_unified_container_and_bumps_schema_version() {
        global $wp_mock_options;

        // Cenário legado existente
        $wp_mock_options['gvn_checkout_header_text'] = 'Loja de Cursos Online';
        $wp_mock_options['gvn_checkout_header_bg_color'] = '#223344';
        $wp_mock_options['gvn_checkout_order_bump_enabled'] = 'yes';
        $wp_mock_options['gvn_checkout_order_bump_price'] = '39.90';

        $this->assertFalse(SettingsRepository::is_migrated());

        $success = SettingsMigrator::migrate();

        $this->assertTrue($success);
        $this->assertTrue(SettingsRepository::is_migrated());
        $this->assertEquals(1, SettingsRepository::get_schema_version());
        $this->assertTrue(Features::is_enabled(Features::FLAG_NEW_SETTINGS_SCHEMA));

        // Verifica valores no container unificado
        $unified = $wp_mock_options[SettingsRepository::OPTION_SETTINGS];
        $this->assertIsArray($unified);
        $this->assertEquals('Loja de Cursos Online', $unified['header_text']);
        $this->assertEquals('#223344', $unified['header_bg_color']);
        $this->assertEquals('yes', $unified['order_bump_enabled']);
        $this->assertEquals('39.90', $unified['order_bump_price']);

        // Invariante de rollback: opções legadas NÃO foram apagadas
        $this->assertEquals('Loja de Cursos Online', $wp_mock_options['gvn_checkout_header_text']);
        $this->assertEquals('#223344', $wp_mock_options['gvn_checkout_header_bg_color']);

        // Verifica registro de estado
        $state = SettingsMigrator::get_migration_state();
        $this->assertIsArray($state);
        $this->assertEquals('completed', $state['status']);
        $this->assertEquals(1, $state['version']);
    }

    public function test_migrate_is_idempotent() {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_header_text'] = 'Loja Teste';

        // 1ª execução
        $this->assertTrue(SettingsMigrator::migrate());
        $this->assertEquals(1, SettingsRepository::get_schema_version());

        // 2ª execução não altera nem falha
        $this->assertTrue(SettingsMigrator::migrate());
        $this->assertEquals(1, SettingsRepository::get_schema_version());
    }

    public function test_migrate_handles_incomplete_or_corrupted_legacy_options_safely() {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_header_bg_color'] = 'not-a-color';
        $wp_mock_options['gvn_checkout_button_color'] = '#ZZZ999';

        $this->assertTrue(SettingsMigrator::migrate());

        $unified = $wp_mock_options[SettingsRepository::OPTION_SETTINGS];
        $this->assertEquals('#3a4759', $unified['header_bg_color']); // Default seguro
        $this->assertEquals('#ff8a22', $unified['button_color']);   // Default seguro
    }

    public function test_acquire_lock_prevents_concurrent_migration() {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_header_text'] = 'Loja Concorrente';

        // Simula lock ativo adquirido por outra requisição há 5 segundos
        $wp_mock_options[SettingsMigrator::OPTION_LOCK] = time() - 5;

        $result = SettingsMigrator::migrate();

        // Deve recusar execução concorrente
        $this->assertFalse($result);
        $this->assertFalse(SettingsRepository::is_migrated());
    }
}
