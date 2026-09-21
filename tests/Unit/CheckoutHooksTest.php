<?php

namespace GVN\Checkout\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para a Fase F5: Restauração dos hooks nativos do WooCommerce e Termos de Serviço no Checkout.
 */
class CheckoutHooksTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_actions, $wp_mock_filters, $wp_mock_options, $mock_woocommerce_instance;
        $wp_mock_actions = [];
        $wp_mock_filters = [];
        $wp_mock_options = [];
        $mock_woocommerce_instance = null;
    }

    /**
     * Renderiza o template de checkout e captura seu output HTML.
     */
    private function renderCheckoutTemplate(): string {
        global $checkout;
        $checkout = WC()->checkout();
        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/checkout-template.php';
        return ob_get_clean();
    }

    public function test_all_canonical_woocommerce_hooks_fire_in_correct_order() {
        global $wp_mock_actions;

        $html = $this->renderCheckoutTemplate();

        $this->assertNotEmpty($html);

        $fired_tags = array_column($wp_mock_actions, 'tag');

        $expected_canonical_hooks = [
            'woocommerce_before_checkout_form',
            'woocommerce_checkout_before_customer_details',
            'woocommerce_before_checkout_billing_form',
            'woocommerce_after_checkout_billing_form',
            'woocommerce_checkout_after_customer_details',
            'woocommerce_checkout_before_order_review_heading',
            'woocommerce_checkout_before_order_review',
            'woocommerce_checkout_after_order_review',
            'woocommerce_review_order_before_payment',
            'woocommerce_checkout_terms_and_conditions',
            'woocommerce_after_checkout_terms_and_conditions',
            'woocommerce_review_order_before_submit',
            'woocommerce_review_order_after_submit',
            'woocommerce_review_order_after_payment',
            'woocommerce_after_checkout_form',
        ];

        foreach ($expected_canonical_hooks as $hook) {
            $this->assertContains($hook, $fired_tags, "O hook canônico '{$hook}' deve ser disparado.");
            $this->assertSame(1, did_action($hook), "O hook canônico '{$hook}' deve ser disparado exatamente uma única vez.");
        }

        // Verifica a ordem sequencial dos hooks disparados
        $last_index = -1;
        foreach ($expected_canonical_hooks as $hook) {
            $current_index = array_search($hook, $fired_tags, true);
            $this->assertNotFalse($current_index, "Hook {$hook} não encontrado.");
            $this->assertGreaterThan($last_index, $current_index, "Hook {$hook} disparado fora de ordem canônica.");
            $last_index = $current_index;
        }
    }

    public function test_duplicate_keys_render_unique_ids_with_same_name(): void {
        global $wp_mock_options;
        $wp_mock_options[\GVN_Custom_Fields::OPTION_KEY] = [
            [
                'key' => 'billing_first_name',
                'label' => 'Nome do aluno',
                'type' => 'text',
                'required' => true,
                'width' => '100',
                'position' => 1,
                'placeholder' => '',
                'enabled' => true,
                'mask' => '',
                'is_default' => false,
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'gvn_para_quem', 'operator' => 'equals', 'value' => 'mim']]],
            ],
            [
                'key' => 'billing_first_name',
                'label' => 'Nome do comprador',
                'type' => 'text',
                'required' => true,
                'width' => '100',
                'position' => 2,
                'placeholder' => '',
                'enabled' => true,
                'mask' => '',
                'is_default' => false,
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'gvn_para_quem', 'operator' => 'equals', 'value' => 'outra']]],
            ],
        ];

        $html = $this->renderCheckoutTemplate();

        // Mesmo destino (name igual), ids únicos, rótulos próprios.
        $this->assertSame(2, substr_count($html, 'name="billing_first_name"'));
        $this->assertStringContainsString('id="billing_first_name"', $html);
        $this->assertStringContainsString('id="billing_first_name--2"', $html);
        $this->assertStringContainsString('Nome do aluno', $html);
        $this->assertStringContainsString('Nome do comprador', $html);
    }

    public function test_payment_panel_is_marked_for_multistep_navigation(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_field_steps'] = array(
            array(
                'id'    => 'dados-pessoais',
                'title' => 'Dados pessoais',
            ),
            array(
                'id'    => 'dados-atletas',
                'title' => 'Dados atletas',
            ),
        );

        $html = $this->renderCheckoutTemplate();

        $this->assertStringContainsString('data-gvn-payment-panel', $html);
        $this->assertStringContainsString('data-finish="Ir para pagamento"', $html);
    }

    public function test_header_can_be_hidden(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_header_enabled'] = 'no';
        \GVN\Checkout\Settings\SettingsRepository::flush_cache();

        $html = $this->renderCheckoutTemplate();

        $this->assertStringNotContainsString('class="gvn-header"', $html);
    }

    public function test_checkout_fragments_do_not_replace_payment_gateway_fields(): void {
        $fragments = \GVN_Checkout::get_instance()->refresh_checkout_fragments([]);

        $this->assertArrayHasKey('#gvn-order-items', $fragments);
        $this->assertArrayHasKey('#gvn-order-totals', $fragments);
        $this->assertArrayNotHasKey('#payment', $fragments);
    }

    public function test_remove_coupon_returns_error_when_coupon_was_not_applied(): void {
        global $mock_woocommerce_instance;

        $mock_woocommerce_instance = new \Mock_WooCommerce();
        $mock_woocommerce_instance->cart = new class extends \Mock_WC_Cart {
            public function remove_coupon($coupon_code) {
                return false;
            }
        };
        $_POST['coupon_code'] = 'nao-aplicado';

        ob_start();
        \GVN_Checkout::get_instance()->ajax_remove_coupon();
        $response = ob_get_clean();
        unset($_POST['coupon_code']);

        $this->assertJson($response);
        $this->assertFalse(json_decode($response, true)['success']);
    }

    public function test_terms_and_conditions_checkbox_rendered_when_configured() {
        global $wp_mock_options;
        $wp_mock_options['woocommerce_terms_page_id'] = 42;

        $html = $this->renderCheckoutTemplate();

        $this->assertStringContainsString('name="terms"', $html, 'O input name="terms" deve estar presente quando os termos estão configurados.');
        $this->assertStringContainsString('name="terms-field"', $html, 'O input hidden name="terms-field" deve estar presente.');
        $this->assertStringContainsString('woocommerce-terms-and-conditions-wrapper', $html);
        $this->assertStringContainsString('Li e concordo com os termos e condições do site', $html);
    }

    public function test_privacy_policy_rendered_when_terms_not_configured() {
        global $wp_mock_options;
        $wp_mock_options['woocommerce_terms_page_id'] = 0;

        $html = $this->renderCheckoutTemplate();

        $this->assertStringNotContainsString('name="terms"', $html, 'O input name="terms" não deve ser renderizado se os termos estiverem desativados.');
        $this->assertStringContainsString('woocommerce-privacy-policy-text', $html, 'A política de privacidade deve ser renderizada.');
    }

    public function test_order_button_text_and_html_filters_are_applied() {
        add_filter('woocommerce_order_button_text', function ($text) {
            return 'Confirmar e Pagar Agora';
        });

        add_filter('woocommerce_order_button_html', function ($button_html) {
            return str_replace('class="gvn-submit__btn"', 'class="gvn-submit__btn custom-filter-btn"', $button_html);
        });

        $html = $this->renderCheckoutTemplate();

        $this->assertStringContainsString('Confirmar e Pagar Agora', $html);
        $this->assertStringContainsString('custom-filter-btn', $html);
    }

    public function test_coupon_field_is_rendered_by_default(): void {
        $html = $this->renderCheckoutTemplate();

        $this->assertStringContainsString('Tem um cupom de desconto?', $html);
        $this->assertStringContainsString('id="gvn-coupon-form"', $html);
    }

    public function test_coupon_field_can_be_hidden_from_checkout(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_coupon_enabled'] = 'no';
        \GVN\Checkout\Settings\SettingsRepository::flush_cache();

        $html = $this->renderCheckoutTemplate();

        $this->assertStringNotContainsString('Tem um cupom de desconto?', $html);
        $this->assertStringNotContainsString('class="gvn-coupon"', $html);
        $this->assertStringNotContainsString('id="gvn-coupon-form"', $html);
    }

    public function test_configured_text_placeholders_are_resolved_and_escaped(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_header_text'] = 'Você escolheu {qtd-produto}x {produto}';
        $wp_mock_options['gvn_checkout_title_text'] = 'Seu pedido na {nome-loja}';
        \GVN\Checkout\Settings\SettingsRepository::flush_cache();

        WC()->cart->items = [
            'course' => [
                'data'     => new \Mock_WC_Product(10, '<b>Curso Seguro</b>', '120.00'),
                'quantity' => 2,
            ],
        ];

        $html = $this->renderCheckoutTemplate();

        $this->assertStringContainsString('Você escolheu 2x Curso Seguro', $html);
        $this->assertStringContainsString('Seu pedido na 6.7', $html);
        $this->assertStringNotContainsString('<b>Curso Seguro</b>', $html);
    }

    public function test_form_has_native_action_and_nonce() {
        $html = $this->renderCheckoutTemplate();

        $this->assertStringContainsString('action="https://example.com/checkout"', $html);
        $this->assertStringContainsString('name="woocommerce-process-checkout-nonce"', $html);
        $this->assertStringContainsString('value="mock_nonce_woocommerce-process_checkout"', $html);
        $this->assertStringContainsString('name="woocommerce_checkout_place_order"', $html);
    }
}
