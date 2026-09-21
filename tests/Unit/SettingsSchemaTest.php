<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Settings\SettingsSchema;
use PHPUnit\Framework\TestCase;

class SettingsSchemaTest extends TestCase {

    public function test_get_defaults_contains_required_keys() {
        $defaults = SettingsSchema::get_defaults();

        $this->assertArrayHasKey('header_text', $defaults);
        $this->assertArrayHasKey('header_enabled', $defaults);
        $this->assertArrayHasKey('header_bg_color', $defaults);
        $this->assertArrayHasKey('primary_color', $defaults);
        $this->assertArrayHasKey('button_color', $defaults);
        $this->assertArrayHasKey('coupon_enabled', $defaults);
        $this->assertArrayHasKey('order_bump_enabled', $defaults);
        $this->assertArrayHasKey('thankyou_success_title', $defaults);
        $this->assertArrayHasKey('thankyou_shop_button_text', $defaults);
        $this->assertEquals('EFEAD - Conectando Saberes', $defaults['header_text']);
        $this->assertEquals('yes', $defaults['header_enabled']);
        $this->assertEquals('#3a4759', $defaults['header_bg_color']);
        $this->assertEquals('#0066d4', $defaults['primary_color']);
        $this->assertEquals('no', $defaults['order_bump_enabled']);
        $this->assertEquals('yes', $defaults['coupon_enabled']);
        $this->assertEquals('Pedido recebido!', $defaults['thankyou_success_title']);
    }

    public function test_sanitize_hex_colors_with_fallback() {
        // Cores válidas
        $this->assertEquals('#ffffff', SettingsSchema::sanitize_setting('header_bg_color', '#ffffff'));
        $this->assertEquals('#123456', SettingsSchema::sanitize_setting('button_color', '#123456'));

        // Cores inválidas retornam o default seguro
        $this->assertEquals('#3a4759', SettingsSchema::sanitize_setting('header_bg_color', 'invalid-color'));
        $this->assertEquals('#3a4759', SettingsSchema::sanitize_setting('header_bg_color', '<script>alert(1)</script>'));
        $this->assertEquals('#ff8a22', SettingsSchema::sanitize_setting('button_color', '#ZZZ999'));
    }

    public function test_sanitize_order_bump_fields() {
        // ID de produto
        $this->assertEquals(42, SettingsSchema::sanitize_setting('order_bump_product_id', '42'));
        $this->assertEquals(0, SettingsSchema::sanitize_setting('order_bump_product_id', '-10'));
        $this->assertEquals(0, SettingsSchema::sanitize_setting('order_bump_product_id', 'abc'));

        // Toggle enabled
        $this->assertEquals('yes', SettingsSchema::sanitize_setting('order_bump_enabled', true));
        $this->assertEquals('yes', SettingsSchema::sanitize_setting('order_bump_enabled', 'yes'));
        $this->assertEquals('yes', SettingsSchema::sanitize_setting('order_bump_enabled', '1'));
        $this->assertEquals('no', SettingsSchema::sanitize_setting('order_bump_enabled', false));
        $this->assertEquals('no', SettingsSchema::sanitize_setting('order_bump_enabled', 'other'));

        // Preço
        $this->assertEquals('29.90', SettingsSchema::sanitize_setting('order_bump_price', '29,90'));
        $this->assertEquals('19.99', SettingsSchema::sanitize_setting('order_bump_price', '19.99'));
        $this->assertEquals('', SettingsSchema::sanitize_setting('order_bump_price', 'invalid_price'));
    }

    public function test_sanitize_coupon_visibility_toggle() {
        $this->assertEquals('yes', SettingsSchema::sanitize_setting('coupon_enabled', true));
        $this->assertEquals('yes', SettingsSchema::sanitize_setting('coupon_enabled', 'yes'));
        $this->assertEquals('yes', SettingsSchema::sanitize_setting('coupon_enabled', '1'));
        $this->assertEquals('no', SettingsSchema::sanitize_setting('coupon_enabled', false));
        $this->assertEquals('no', SettingsSchema::sanitize_setting('coupon_enabled', 'other'));
    }

    public function test_sanitize_header_visibility_toggle() {
        $this->assertEquals('yes', SettingsSchema::sanitize_setting('header_enabled', true));
        $this->assertEquals('no', SettingsSchema::sanitize_setting('header_enabled', false));
    }

    public function test_sanitize_text_fields_strips_tags() {
        $this->assertEquals('Texto Limpo', SettingsSchema::sanitize_setting('header_text', '<b>Texto Limpo</b>'));
        $this->assertEquals('Sem script', SettingsSchema::sanitize_setting('title_text', '<script>bad</script>Sem script'));
        $this->assertEquals('Olá {primeiro-nome}', SettingsSchema::sanitize_setting('thankyou_success_title', '<b>Olá {primeiro-nome}</b>'));
    }

    public function test_sanitize_all_settings_fills_missing_defaults() {
        $partial = [
            'header_text'     => 'Meu Checkout Novo',
            'header_bg_color' => '#112233',
        ];

        $sanitized = SettingsSchema::sanitize_all_settings($partial);

        $this->assertEquals('Meu Checkout Novo', $sanitized['header_text']);
        $this->assertEquals('#112233', $sanitized['header_bg_color']);
        $this->assertEquals('#0066d4', $sanitized['primary_color']); // Default preservado
        $this->assertEquals('Finalizar pedido', $sanitized['button_text']); // Default preservado
    }

    public function test_get_default_fields_contains_persontype_and_cpf_cnpj() {
        $fields = SettingsSchema::get_default_fields();
        $keys   = array_column($fields, 'key');

        $this->assertContains('billing_persontype', $keys);
        $this->assertContains('billing_cpf', $keys);
        $this->assertContains('billing_cnpj', $keys);
        $this->assertContains('billing_cellphone', $keys);
        $this->assertContains('billing_postcode', $keys);
        $this->assertContains('billing_city', $keys);
        $this->assertContains('billing_number', $keys);
        $this->assertContains('billing_neighborhood', $keys);

        $postcode = $fields[array_search('billing_postcode', $keys, true)];
        $this->assertTrue($postcode['required']);
        $this->assertTrue($postcode['enabled']);
        $this->assertTrue($postcode['is_default']);
        $this->assertEquals('cep', $postcode['mask']);

        $city = $fields[array_search('billing_city', $keys, true)];
        $this->assertEquals('Cidade', $city['label']);
        $this->assertTrue($city['required']);
        $this->assertTrue($city['enabled']);
        $this->assertTrue($city['is_default']);
    }
}
