<?php

namespace GVN\Checkout\Tests\Unit;

use GVN_Admin;
use GVN_Custom_Fields;
use GVN\Checkout\Settings\SettingsRepository;
use GVN\Checkout\Support\Features;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para a Fase F10: Segurança do Admin, verificação de capabilities, nonces e flush de cache.
 */
class AdminTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options, $wp_mock_user_caps;
        $wp_mock_options = [];
        $wp_mock_user_caps = [
            'manage_woocommerce' => true,
            'manage_options' => true,
        ];
        $_GET = [];
        $_POST = [];
        SettingsRepository::flush_cache();
    }

    public function test_saved_fields_receive_required_default_address_fields(): void {
        global $wp_mock_options;
        $wp_mock_options[GVN_Custom_Fields::OPTION_KEY] = [
            [
                'key' => 'billing_first_name',
                'label' => 'Nome',
                'enabled' => true,
            ],
        ];

        $fields = GVN_Custom_Fields::get_fields();
        $keys = array_column($fields, 'key');
        $postcode = $fields[array_search('billing_postcode', $keys, true)];
        $city = $fields[array_search('billing_city', $keys, true)];

        $this->assertTrue($postcode['required']);
        $this->assertTrue($postcode['enabled']);
        $this->assertTrue($postcode['is_default']);
        $this->assertSame('Cidade', $city['label']);
        $this->assertTrue($city['required']);
        $this->assertTrue($city['enabled']);
        $this->assertTrue($city['is_default']);

        $checkoutFields = GVN_Custom_Fields::get_instance()->register_custom_fields_with_woo([
            'billing' => [
                'billing_postcode' => ['class' => []],
                'billing_city' => ['class' => []],
            ],
        ]);
        $this->assertTrue($checkoutFields['billing']['billing_postcode']['required']);
        $this->assertNotContains('gvn-hidden-field', $checkoutFields['billing']['billing_postcode']['class']);
        $this->assertTrue($checkoutFields['billing']['billing_city']['required']);
        $this->assertNotContains('gvn-hidden-field', $checkoutFields['billing']['billing_city']['class']);
    }

    public function test_render_settings_page_denies_unauthorized_users(): void {
        global $wp_mock_user_caps;
        $wp_mock_user_caps = []; // Sem nenhuma capacidade

        $admin = GVN_Admin::get_instance();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Você não tem permissão');

        $admin->render_settings_page();
    }

    public function test_render_settings_page_allows_authorized_users(): void {
        $admin = GVN_Admin::get_instance();

        ob_start();
        $admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('gvn-subtabs', $output);
        $this->assertStringContainsString('Configurações', $output);
    }

    public function test_save_settings_denies_unauthorized_users(): void {
        global $wp_mock_user_caps, $wp_mock_options;
        $wp_mock_user_caps = [];

        $_GET['subtab'] = 'settings';
        $_POST['gvn_checkout_button_text'] = 'Hack Button Text';

        $admin = GVN_Admin::get_instance();
        $admin->save_settings();

        $this->assertArrayNotHasKey('gvn_checkout_button_text', $wp_mock_options);
    }

    public function test_save_settings_updates_options_and_flushes_cache(): void {
        global $wp_mock_options;

        $_GET['subtab'] = 'settings';
        $_POST['gvn_checkout_header_text'] = 'Minha Loja Premium';

        $admin = GVN_Admin::get_instance();
        $admin->save_settings();

        $this->assertEquals('Minha Loja Premium', get_option('gvn_checkout_header_text'));
        $this->assertEquals('Minha Loja Premium', SettingsRepository::get('header_text'));
    }

    public function test_settings_include_customizable_thankyou_texts(): void {
        $admin = GVN_Admin::get_instance();
        $method = new \ReflectionMethod($admin, 'get_settings');
        $method->setAccessible(true);

        $settings = $method->invoke($admin);
        $ids = array_column($settings, 'id');

        $this->assertContains('gvn_checkout_thankyou_text_section', $ids);
        $this->assertContains('gvn_checkout_thankyou_success_title', $ids);
        $this->assertContains('gvn_checkout_thankyou_failed_message', $ids);
        $this->assertContains('gvn_checkout_thankyou_not_found_title', $ids);
        $this->assertContains('gvn_checkout_thankyou_payment_title', $ids);
        $this->assertContains('gvn_checkout_thankyou_shop_button_text', $ids);
    }

    public function test_save_thankyou_text_syncs_the_unified_settings_container(): void {
        global $wp_mock_options;

        $wp_mock_options[Features::FLAG_NEW_SETTINGS_SCHEMA] = 'yes';
        $wp_mock_options[SettingsRepository::OPTION_SCHEMA_VERSION] = 1;
        $wp_mock_options[SettingsRepository::OPTION_SETTINGS] = [
            'header_text' => 'Header preservado',
            'thankyou_success_title' => 'Título anterior',
        ];
        $_GET['subtab'] = 'settings';
        $_POST['gvn_checkout_thankyou_success_title'] = 'Obrigado, {primeiro-nome}!';

        GVN_Admin::get_instance()->save_settings();

        $this->assertSame('Obrigado, {primeiro-nome}!', SettingsRepository::get('thankyou_success_title'));
        $this->assertSame('Header preservado', SettingsRepository::get('header_text'));
        $this->assertSame(
            'Obrigado, {primeiro-nome}!',
            $wp_mock_options[SettingsRepository::OPTION_SETTINGS]['thankyou_success_title']
        );
    }

    public function test_ajax_save_fields_denies_unauthorized_users(): void {
        global $wp_mock_user_caps;
        $wp_mock_user_caps = [];

        $_POST['nonce'] = 'mock_nonce';
        $_POST['fields'] = json_encode([['key' => 'billing_cpf', 'label' => 'CPF']]);

        $custom_fields = GVN_Custom_Fields::get_instance();

        ob_start();
        $custom_fields->ajax_save_fields();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Permissão negada', $data['data']['message']);
    }

    public function test_ajax_save_fields_validates_and_sanitizes_payload(): void {
        $_POST['nonce'] = 'mock_nonce';
        $_POST['fields'] = json_encode([
            [
                'key' => 'billing_cpf',
                'label' => 'CPF do Comprador',
                'type' => 'text',
                'required' => true,
                'width' => '50',
                'mask' => 'cpf',
                'enabled' => true,
            ]
        ]);

        $custom_fields = GVN_Custom_Fields::get_instance();

        ob_start();
        $custom_fields->ajax_save_fields();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']['fields']);
        $this->assertEquals('billing_cpf', $data['data']['fields'][0]['key']);
        $this->assertEquals('CPF do Comprador', $data['data']['fields'][0]['label']);
        $this->assertTrue($data['data']['fields'][0]['required']);
    }

    public function test_add_plugin_links(): void {
        $admin = GVN_Admin::get_instance();
        $links = $admin->add_plugin_links(['<a href="#">Desativar</a>']);

        $this->assertCount(2, $links);
        $this->assertStringContainsString('page=wc-settings&tab=gvn_checkout', $links[0]);
        $this->assertStringContainsString('Configurações', $links[0]);
    }
}
