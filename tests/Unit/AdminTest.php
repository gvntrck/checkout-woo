<?php

namespace GVN\Checkout\Tests\Unit;

use GVN_Admin;
use GVN_Custom_Fields;
use GVN\Checkout\Settings\SettingsRepository;
use GVN\Checkout\Settings\SettingsSchema;
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

    public function test_legacy_direct_conditions_are_normalized_before_woocommerce_registration(): void {
        global $wp_mock_options;
        $wp_mock_options[GVN_Custom_Fields::OPTION_KEY] = SettingsSchema::get_default_fields();

        $fields = GVN_Custom_Fields::get_fields();
        $cnpj = $fields[array_search('billing_cnpj', array_column($fields, 'key'), true)];

        $this->assertSame('and', $cnpj['conditions']['logic']);
        $this->assertSame('equals', $cnpj['conditions']['rules'][0]['operator']);
        $this->assertTrue(GVN_Custom_Fields::has_conditions($cnpj));

        $checkout_fields = GVN_Custom_Fields::get_instance()->register_custom_fields_with_woo([
            'billing' => [], 'shipping' => [], 'account' => [], 'order' => [],
        ]);
        $this->assertArrayHasKey('1', $checkout_fields['billing']['billing_persontype']['options']);
        $this->assertFalse($checkout_fields['billing']['billing_cnpj']['required']);
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

    public function test_settings_include_coupon_visibility_option(): void {
        $admin = GVN_Admin::get_instance();
        $method = new \ReflectionMethod($admin, 'get_settings');
        $method->setAccessible(true);

        $settings = $method->invoke($admin);
        $coupon_setting = null;

        foreach ($settings as $setting) {
            if (isset($setting['id']) && 'gvn_checkout_coupon_enabled' === $setting['id']) {
                $coupon_setting = $setting;
                break;
            }
        }

        $this->assertIsArray($coupon_setting);
        $this->assertSame('checkbox', $coupon_setting['type']);
        $this->assertSame('yes', $coupon_setting['default']);
        $this->assertStringContainsString('Tem um cupom de desconto?', $coupon_setting['desc']);
    }

    public function test_save_coupon_visibility_syncs_the_unified_settings_container(): void {
        global $wp_mock_options;

        $_GET['subtab'] = 'settings';
        $_POST['gvn_checkout_coupon_enabled'] = 'no';

        GVN_Admin::get_instance()->save_settings();

        $this->assertSame('no', $wp_mock_options['gvn_checkout_coupon_enabled']);
        $this->assertSame('no', SettingsRepository::get('coupon_enabled'));
        $this->assertSame('no', $wp_mock_options[SettingsRepository::OPTION_SETTINGS]['coupon_enabled']);
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

    public function test_admin_css_file_has_valid_syntax_and_balanced_braces(): void {
        $css_path = GVN_CHECKOUT_PLUGIN_DIR . 'assets/css/gvn-admin-fields.css';
        $this->assertFileExists($css_path);

        $content = file_get_contents($css_path);
        $this->assertNotEmpty($content);

        // Remove comentários CSS
        $clean = preg_replace('!/\*.*?\*/!s', '', $content);

        $depth = 0;
        $max_depth = 0;
        $lines = explode("\n", $clean);
        $errors = [];

        foreach ($lines as $line_num => $line) {
            $len = strlen($line);
            for ($i = 0; $i < $len; $i++) {
                if ($line[$i] === '{') {
                    $depth++;
                    if ($depth > $max_depth) {
                        $max_depth = $depth;
                    }
                } elseif ($line[$i] === '}') {
                    $depth--;
                    if ($depth < 0) {
                        $errors[] = "Chave de fechamento prematura/órfã na linha " . ($line_num + 1);
                        $depth = 0;
                    }
                }
            }
        }

        if ($depth !== 0) {
            $errors[] = "Chaves não fechadas ao final do arquivo (profundidade restante: {$depth})";
        }

        $this->assertEmpty($errors, 'Erros de sintaxe/chaves no CSS do admin: ' . implode('; ', $errors));
        $this->assertStringNotContainsString('font-style: italic;}', preg_replace('/\s+/', '', $content), 'CSS contém fragmento de declaração órfã');
    }

    public function test_enqueue_admin_assets_supports_various_wc_settings_hook_formats(): void {
        global $wp_mock_enqueued_styles, $wp_mock_enqueued_scripts;
        $wp_mock_enqueued_styles = [];
        $wp_mock_enqueued_scripts = [];

        $_GET['page'] = 'wc-settings';
        $_GET['tab'] = 'gvn_checkout';

        $admin = GVN_Admin::get_instance();

        // Cenário onde o hook não é exatamente a string woocommerce_page_wc-settings, mas o usuário está na aba do plugin
        $admin->enqueue_admin_assets('settings_page_wc-settings');

        $this->assertArrayHasKey(
            'gvn-admin-fields-css',
            $wp_mock_enqueued_styles,
            'O estilo gvn-admin-fields-css deve ser enfileirado quando o usuário está na aba gvn_checkout das configurações do WooCommerce'
        );
    }
}
