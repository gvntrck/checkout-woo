<?php

namespace GVN\Checkout\Tests\Unit;

use GVN_Address_Validation;
use Mock_WC_Order_Complete;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Testes unitários para a Fase F8: Validação de CEP, consistência de endereço, cache e fallback manual.
 */
class AddressValidationTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_transients, $wp_mock_http_responses;
        $wp_mock_transients = [];
        $wp_mock_http_responses = [];
        $_POST = [];
    }

    public function test_ajax_cep_lookup_validates_numeric_8_digits(): void {
        $validator = GVN_Address_Validation::get_instance();

        // CEP inválido (poucos dígitos)
        $_POST['nonce'] = 'mock_nonce';
        $_POST['cep'] = '12345';

        ob_start();
        $validator->ajax_cep_lookup();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('8 dígitos', $data['data']['message']);
    }

    public function test_ajax_cep_lookup_success_with_normalization_and_cache(): void {
        $validator = GVN_Address_Validation::get_instance();

        $_POST['nonce'] = 'mock_nonce';
        $_POST['cep'] = '01001-000'; // com máscara

        ob_start();
        $validator->ajax_cep_lookup();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertEquals('01001000', $data['data']['cep']);
        $this->assertEquals('Praça da Sé', $data['data']['logradouro']);
        $this->assertEquals('São Paulo', $data['data']['cidade']);
        $this->assertEquals('SP', $data['data']['uf']);
        $this->assertTrue($data['data']['consistente']);

        // Verifica se foi cacheado em transient
        $cached = get_transient('gvn_cep_01001000');
        $this->assertIsArray($cached);
        $this->assertEquals('São Paulo', $cached['cidade']);
    }

    public function test_ajax_cep_lookup_handles_corrupted_cache_safely(): void {
        $validator = GVN_Address_Validation::get_instance();

        // Insere dado corrompido no transient
        set_transient('gvn_cep_01001000', 'valor_corrompido_string_invalida');

        $_POST['nonce'] = 'mock_nonce';
        $_POST['cep'] = '01001000';

        ob_start();
        $validator->ajax_cep_lookup();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertTrue($data['success'], 'Cache corrompido deve ser ignorado e a API consultada com sucesso.');
        $this->assertEquals('São Paulo', $data['data']['cidade']);
    }

    public function test_ajax_cep_lookup_returns_manual_fallback_on_api_error(): void {
        $validator = GVN_Address_Validation::get_instance();

        global $wp_mock_http_responses;
        $wp_mock_http_responses['https://viacep.com.br/ws/99999999/json/'] = new WP_Error('timeout', 'Connection timed out');

        $_POST['nonce'] = 'mock_nonce';
        $_POST['cep'] = '99999999';

        ob_start();
        $validator->ajax_cep_lookup();
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertTrue($data['data']['manual_entry']);
        $this->assertStringContainsString('manualmente', $data['data']['message']);
    }

    public function test_validate_address_fields_enforces_cep_uf_consistency_for_br(): void {
        $validator = GVN_Address_Validation::get_instance();
        $errors = new WP_Error();

        // CEP de SP (01001-000) mas UF informada RJ
        $checkout_data = [
            'billing_country'  => 'BR',
            'billing_postcode' => '01001-000',
            'billing_state'    => 'RJ',
            'billing_city'     => 'Rio de Janeiro',
        ];

        $validator->validate_address_fields($checkout_data, $errors);

        $this->assertTrue($errors->has_errors());
        $messages = $errors->get_error_messages('gvn_cep_uf_mismatch');
        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('São Paulo', $messages[0]);
    }

    public function test_validate_address_fields_skips_br_rules_for_international_orders(): void {
        $validator = GVN_Address_Validation::get_instance();
        $errors = new WP_Error();

        // Endereço nos Estados Unidos
        $checkout_data = [
            'billing_country'  => 'US',
            'billing_postcode' => '90210',
            'billing_state'    => 'CA',
            'billing_city'     => 'Beverly Hills',
        ];

        $validator->validate_address_fields($checkout_data, $errors);

        $this->assertFalse($errors->has_errors(), 'Pedidos internacionais não devem sofrer validação de CEP/UF brasileiro.');
    }

    public function test_normalize_order_address_cleans_and_formats(): void {
        $validator = GVN_Address_Validation::get_instance();

        $order = new class extends Mock_WC_Order_Complete {
            public $billing_state = 'sp';
            public $billing_city = '  são paulo  ';
            public $billing_postcode = '01001000';
            public $billing_address_1 = '  Praça da Sé, 100  ';

            public function get_billing_state() { return $this->billing_state; }
            public function set_billing_state($val) { $this->billing_state = $val; }
            public function get_shipping_state() { return ''; }
            public function set_shipping_state($val) {}
            public function get_billing_city() { return $this->billing_city; }
            public function set_billing_city($val) { $this->billing_city = $val; }
            public function get_shipping_city() { return ''; }
            public function set_shipping_city($val) {}
            public function get_billing_postcode() { return $this->billing_postcode; }
            public function set_billing_postcode($val) { $this->billing_postcode = $val; }
            public function get_shipping_postcode() { return ''; }
            public function set_shipping_postcode($val) {}
            public function get_billing_address_1() { return $this->billing_address_1; }
            public function set_billing_address_1($val) { $this->billing_address_1 = $val; }
            public function get_shipping_address_1() { return ''; }
            public function set_shipping_address_1($val) {}
        };

        $validator->normalize_order_address($order, []);

        $this->assertEquals('SP', $order->get_billing_state());
        $this->assertEquals('São Paulo', $order->get_billing_city());
        $this->assertEquals('01001-000', $order->get_billing_postcode());
        $this->assertEquals('Praça da Sé, 100', $order->get_billing_address_1());
    }
}
