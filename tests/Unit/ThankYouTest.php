<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Fields\FieldOrderPersister;
use Mock_WC_Order_Complete;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para a Fase F9: Isolamento da Thank You Page, segurança de enumeração e hooks nativos.
 */
class ThankYouTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_actions, $wp_mock_filters, $wp_mock_options, $wp_mock_orders, $wp;
        $wp_mock_actions = [];
        $wp_mock_filters = [];
        $wp_mock_options = [];
        $wp_mock_orders = [];
        $wp = (object) ['query_vars' => []];
        $_GET = [];
    }

    public function test_override_returns_default_template_for_non_gvn_orders(): void {
        $checkout_module = \GVN_Checkout::get_instance();

        $order = new Mock_WC_Order_Complete(101, 'key_101');
        // Não define _gvn_checkout_version nem _gvn_checkout (pedido criado por outro checkout)

        global $wp, $wp_mock_orders;
        $wp->query_vars['order-received'] = 101;
        $wp_mock_orders[101] = $order;
        $_GET['key'] = 'key_101';

        $default_template = '/wp-content/plugins/woocommerce/templates/checkout/thankyou.php';
        $result = $checkout_module->override_thankyou_template($default_template, 'checkout/thankyou.php', [], '', '');

        $this->assertEquals($default_template, $result, 'Pedidos não originados pelo GVN Checkout devem preservar o template padrão do WooCommerce.');
    }

    public function test_override_returns_custom_template_for_gvn_orders(): void {
        $checkout_module = \GVN_Checkout::get_instance();

        $order = new Mock_WC_Order_Complete(102, 'key_102');
        $order->update_meta_data('_gvn_checkout_version', '1.13.26');
        $order->update_meta_data('_gvn_checkout', 'yes');

        global $wp, $wp_mock_orders;
        $wp->query_vars['order-received'] = 102;
        $wp_mock_orders[102] = $order;
        $_GET['key'] = 'key_102';

        $default_template = '/wp-content/plugins/woocommerce/templates/checkout/thankyou.php';
        $result = $checkout_module->override_thankyou_template($default_template, 'checkout/thankyou.php', [], '', '');

        $this->assertStringContainsString('templates/thankyou-template.php', $result);
    }

    public function test_override_rejects_mismatched_order_key(): void {
        $checkout_module = \GVN_Checkout::get_instance();

        $order = new Mock_WC_Order_Complete(103, 'real_secret_key');
        $order->update_meta_data('_gvn_checkout_version', '1.13.26');

        global $wp, $wp_mock_orders;
        $wp->query_vars['order-received'] = 103;
        $wp_mock_orders[103] = $order;
        $_GET['key'] = 'wrong_attacker_key';

        $default_template = '/wp-content/plugins/woocommerce/templates/checkout/thankyou.php';
        $result = $checkout_module->override_thankyou_template($default_template, 'checkout/thankyou.php', [], '', '');

        $this->assertEquals($default_template, $result, 'Chave de pedido inválida não deve renderizar o template customizado com dados.');
    }

    public function test_thankyou_template_fires_canonical_hooks(): void {
        global $wp_mock_actions, $order;

        $order = new Mock_WC_Order_Complete(104, 'key_104');
        $order->update_meta_data('_gvn_checkout_version', '1.13.26');
        add_action('woocommerce_thankyou_pix', static function (): void {
            echo '<p>Instruções Pix</p>';
        });

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/thankyou-template.php';
        $html = ob_get_clean();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Pedido recebido!', $html);
        $this->assertStringContainsString('#104', $html);
        $this->assertStringContainsString('INSTRUÇÕES DE PAGAMENTO', $html);
        $this->assertTrue(
            strpos($html, 'INSTRUÇÕES DE PAGAMENTO') < strpos($html, 'ITENS DO PEDIDO'),
            'As instruções de pagamento devem aparecer antes dos itens do pedido.'
        );

        $fired_tags = array_column($wp_mock_actions, 'tag');
        $this->assertContains('woocommerce_before_thankyou', $fired_tags, 'woocommerce_before_thankyou deve ser disparado.');
        $this->assertContains('woocommerce_thankyou_pix', $fired_tags, 'woocommerce_thankyou_{gateway} deve ser disparado.');
        $this->assertContains('woocommerce_thankyou', $fired_tags, 'woocommerce_thankyou deve ser disparado.');
    }

    public function test_thankyou_template_preserves_gateway_inline_scripts(): void {
        global $order;

        $order = new Mock_WC_Order_Complete(107, 'key_107');
        add_action('woocommerce_thankyou_pix', static function (): void {
            echo '<div class="pix-payment"><textarea id="pix-code">000201010212</textarea></div>';
            echo '<script type="text/javascript">const order_id = "107";</script>';
        });

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/thankyou-template.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('<textarea id="pix-code">000201010212</textarea>', $html);
        $this->assertMatchesRegularExpression('/<script[^>]*>\s*const order_id = "107";<\/script>/', $html);
    }

    public function test_thankyou_template_renders_failed_status_when_order_failed(): void {
        global $order;

        $order = new Mock_WC_Order_Complete(105, 'key_105');
        $order->set_status('failed');

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/thankyou-template.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('Pagamento não processado', $html);
        $this->assertStringContainsString('Tentar novamente', $html);
        $this->assertStringContainsString('https://example.com/checkout/pay/105', $html);
    }

    public function test_field_order_persister_tags_order_with_gvn_version(): void {
        $order = new Mock_WC_Order_Complete(106, 'key_106');

        FieldOrderPersister::persist($order, [], []);

        $this->assertEquals(GVN_CHECKOUT_VERSION, $order->get_meta('_gvn_checkout_version'));
        $this->assertEquals('yes', $order->get_meta('_gvn_checkout'));
    }
}
