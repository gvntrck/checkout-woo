<?php

declare(strict_types=1);

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Lifecycle\Uninstaller;
use GVN\Checkout\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para a Fase F12: Ciclo de vida, desinstalação segura e limpeza de options/transients.
 */
class UninstallerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options;
        $wp_mock_options = [];
        SettingsRepository::flush_cache();
    }

    public function test_get_legacy_option_keys_contains_all_core_and_setting_keys(): void {
        $keys = Uninstaller::get_legacy_option_keys();

        $this->assertContains('gvn_checkout_settings', $keys);
        $this->assertContains('gvn_checkout_fields', $keys);
        $this->assertContains('gvn_checkout_header_text', $keys);
        $this->assertContains('gvn_checkout_order_bump_enabled', $keys);
        $this->assertContains('gvn_checkout_order_bump_price', $keys);
        $this->assertContains('gvn_checkout_thankyou_success_title', $keys);
        $this->assertContains('gvn_checkout_thankyou_shop_button_text', $keys);
    }

    public function test_uninstall_removes_all_options_and_resets_cache(): void {
        global $wp_mock_options;

        // Configura opções salvas
        update_option('gvn_checkout_settings', ['header_text' => 'Loja Teste']);
        update_option('gvn_checkout_header_text', 'Loja Teste Legada');
        update_option('gvn_checkout_fields', [['key' => 'billing_cpf']]);
        update_option('woocommerce_currency', 'BRL'); // Opção de terceiro (WooCommerce) que NÃO deve ser deletada

        $this->assertEquals('Loja Teste Legada', SettingsRepository::get('header_text'));

        // Executa uninstallation
        Uninstaller::uninstall();

        // Assegura que opções do plugin foram removidas
        $this->assertFalse(get_option('gvn_checkout_settings'));
        $this->assertFalse(get_option('gvn_checkout_header_text'));
        $this->assertFalse(get_option('gvn_checkout_fields'));

        // Assegura que opções externas foram preservadas
        $this->assertEquals('BRL', get_option('woocommerce_currency'));

        // Assegura que SettingsRepository retorna defaults após flush
        $this->assertEquals('EFEAD - Conectando Saberes', SettingsRepository::get('header_text'));
    }

    public function test_cleanup_transients_runs_safely(): void {
        Uninstaller::cleanup_transients();
        $this->assertTrue(true, 'Limpeza de transients deve ser executada sem exceções.');
    }
}
