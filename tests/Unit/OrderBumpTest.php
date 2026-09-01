<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Settings\SettingsRepository;
use GVN_Order_Bump;
use Mock_WC_Cart;
use Mock_WC_Product;
use PHPUnit\Framework\TestCase;
use WC_Cart;

/**
 * Testes unitários para a Fase F7: Idempotência de Order Bump, manipulação segura do carrinho e validação de preços.
 */
class OrderBumpTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options, $wp_mock_actions, $wp_mock_filters, $mock_woocommerce_instance;
        $wp_mock_options = [];
        $wp_mock_actions = [];
        $wp_mock_filters = [];
        $mock_woocommerce_instance = null;
        SettingsRepository::flush_cache();
        $_POST = [];
    }

    public function test_toggle_order_bump_rejects_when_disabled(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_order_bump_enabled'] = 'no';
        $wp_mock_options['gvn_checkout_order_bump_product_id'] = 42;

        $_POST['nonce'] = 'mock_nonce';
        $_POST['bump_action'] = 'add';

        // Redireciona wp_send_json_error para captura em teste
        $bump = GVN_Order_Bump::get_instance();

        ob_start();
        try {
            $bump->toggle_order_bump();
        } catch (\Exception $e) {
            // Se wp_send_json_error interromper a execução
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Order bump não está habilitado.', $data['data']['message']);
    }

    public function test_toggle_order_bump_add_is_idempotent(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_order_bump_enabled'] = 'yes';
        $wp_mock_options['gvn_checkout_order_bump_product_id'] = 55;
        $wp_mock_options['gvn_checkout_order_bump_price'] = '29.90';

        $_POST['nonce'] = 'mock_nonce';
        $_POST['bump_action'] = 'add';

        $bump = GVN_Order_Bump::get_instance();

        // 1ª adição: adiciona ao carrinho
        ob_start();
        $bump->toggle_order_bump();
        $output1 = ob_get_clean();

        $data1 = json_decode($output1, true);
        $this->assertTrue($data1['success']);
        $this->assertCount(1, WC()->cart->get_cart());

        // 2ª adição consecutiva (clique duplo / repetição): deve ser idempotente e não duplicar item
        ob_start();
        $bump->toggle_order_bump();
        $output2 = ob_get_clean();

        $data2 = json_decode($output2, true);
        $this->assertTrue($data2['success']);
        $this->assertCount(1, WC()->cart->get_cart(), 'Repetição da ação add não deve duplicar o produto de order bump no carrinho.');
    }

    public function test_toggle_order_bump_remove_is_idempotent(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_order_bump_enabled'] = 'yes';
        $wp_mock_options['gvn_checkout_order_bump_product_id'] = 55;

        // Pré-popula o carrinho com o bump
        WC()->cart->add_to_cart(55, 1, 0, [], ['gvn_order_bump' => true]);
        $this->assertCount(1, WC()->cart->get_cart());

        $_POST['nonce'] = 'mock_nonce';
        $_POST['bump_action'] = 'remove';

        $bump = GVN_Order_Bump::get_instance();

        // 1ª remoção
        ob_start();
        $bump->toggle_order_bump();
        $output1 = ob_get_clean();

        $data1 = json_decode($output1, true);
        $this->assertTrue($data1['success']);
        $this->assertCount(0, WC()->cart->get_cart());

        // 2ª remoção: deve ser idempotente
        ob_start();
        $bump->toggle_order_bump();
        $output2 = ob_get_clean();

        $data2 = json_decode($output2, true);
        $this->assertTrue($data2['success']);
        $this->assertCount(0, WC()->cart->get_cart());
    }

    public function test_apply_bump_price_sets_custom_price_only_for_bump_items(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_order_bump_enabled'] = 'yes';
        $wp_mock_options['gvn_checkout_order_bump_product_id'] = 77;
        $wp_mock_options['gvn_checkout_order_bump_price'] = '19.90';

        $cart = new WC_Cart();
        // Item comum da loja (ID 10, preço original 150.00)
        $cart->add_to_cart(10, 1, 0, [], []);
        // Item de Order Bump (ID 77, preço original 99.00)
        $cart->add_to_cart(77, 1, 0, [], ['gvn_order_bump' => true]);

        $bump = GVN_Order_Bump::get_instance();
        $bump->apply_bump_price($cart);

        $cart_items = array_values($cart->get_cart());

        // Item 1: permanece com preço original 99.00
        $this->assertEquals('99.00', $cart_items[0]['data']->get_price());

        // Item 2 (bump): atualizado com precisão para 19.9
        $this->assertEquals('19.9', $cart_items[1]['data']->get_price());
    }
}
