<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Settings\SettingsRepository;
use GVN\Checkout\Settings\SettingsSchema;
use GVN\Checkout\Support\Features;
use PHPUnit\Framework\TestCase;

class SettingsRepositoryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options;
        $wp_mock_options = [];
        SettingsRepository::flush_cache();
    }

    public function test_get_fallback_to_defaults_when_empty() {
        $header = SettingsRepository::get('header_text');
        $this->assertEquals('EFEAD - Conectando Saberes', $header);

        $color = SettingsRepository::get('header_bg_color');
        $this->assertEquals('#3a4759', $color);
    }

    public function test_get_reads_legacy_options_when_unversioned() {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_header_text'] = 'Loja Legada Ativa';
        $wp_mock_options['gvn_checkout_header_bg_color'] = '#121212';
        $wp_mock_options['gvn_checkout_order_bump_enabled'] = 'yes';

        $this->assertEquals('Loja Legada Ativa', SettingsRepository::get('header_text'));
        $this->assertEquals('#121212', SettingsRepository::get('header_bg_color'));
        $this->assertEquals('yes', SettingsRepository::get('order_bump_enabled'));
        $this->assertEquals('#ff8a22', SettingsRepository::get('button_color')); // Default seguro para chave não definida
    }

    public function test_get_sanitizes_corrupted_legacy_options() {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_header_bg_color'] = 'not-a-valid-hex-color';
        $wp_mock_options['gvn_checkout_header_text'] = '<script>alert(1)</script>Texto Sanitizado';
        $wp_mock_options['gvn_checkout_order_bump_product_id'] = '-99';

        // Deve aplicar fallback seguro de cor e sanitizar tags
        $this->assertEquals('#3a4759', SettingsRepository::get('header_bg_color'));
        $this->assertEquals('Texto Sanitizado', SettingsRepository::get('header_text'));
        $this->assertEquals(0, SettingsRepository::get('order_bump_product_id'));
    }

    public function test_update_all_saves_unified_and_mirrors_legacy_for_rollback_safety() {
        global $wp_mock_options;

        $new_data = [
            'header_text'     => 'Nova Loja 2026',
            'header_bg_color' => '#001122',
            'primary_color'   => '#334455',
        ];

        SettingsRepository::update_all($new_data);

        // Verifica container unificado
        $unified = $wp_mock_options[SettingsRepository::OPTION_SETTINGS] ?? null;
        $this->assertIsArray($unified);
        $this->assertEquals('Nova Loja 2026', $unified['header_text']);
        $this->assertEquals('#001122', $unified['header_bg_color']);

        // Verifica espelhamento legado para rollback
        $this->assertEquals('Nova Loja 2026', $wp_mock_options['gvn_checkout_header_text']);
        $this->assertEquals('#001122', $wp_mock_options['gvn_checkout_header_bg_color']);
    }

    public function test_get_reads_unified_container_when_flag_and_schema_are_active() {
        global $wp_mock_options;

        // Ativa flag e schema version
        Features::set(Features::FLAG_NEW_SETTINGS_SCHEMA, true);
        $wp_mock_options[SettingsRepository::OPTION_SCHEMA_VERSION] = 1;
        $wp_mock_options[SettingsRepository::OPTION_SETTINGS] = [
            'header_text'     => 'Texto Unificado V1',
            'header_bg_color' => '#556677',
        ];

        $this->assertEquals('Texto Unificado V1', SettingsRepository::get('header_text'));
        $this->assertEquals('#556677', SettingsRepository::get('header_bg_color'));
    }

    public function test_get_fields_returns_default_fields_when_empty() {
        $fields = SettingsRepository::get_fields();
        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $keys = array_column($fields, 'key');
        $this->assertContains('billing_cpf', $keys);
        $this->assertContains('billing_persontype', $keys);
    }
}
