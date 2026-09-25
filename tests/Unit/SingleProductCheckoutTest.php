<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

class SingleProductCheckoutTest extends TestCase {

    protected function setUp(): void {
        global $mock_woocommerce_instance, $wp_mock_options;
        $mock_woocommerce_instance = new \Mock_WooCommerce();
        $wp_mock_options = ['gvn_checkout_single_product_checkout' => 'yes'];
        SettingsRepository::flush_cache();
    }

    public function test_keeps_last_added_product_even_when_it_was_already_in_cart(): void {
        $checkout = \GVN_Checkout::get_instance();
        $cart = \WC()->cart;
        $first = $cart->add_to_cart(1, 1);
        $cart->add_to_cart(2, 1);
        $cart->set_quantity($first, 3);
        $checkout->remember_last_cart_item($first);

        $checkout->keep_only_last_cart_item();

        $this->assertSame([$first], array_keys($cart->get_cart()));
        $this->assertSame(1, $cart->get_cart_item($first)['quantity']);
    }

    public function test_falls_back_to_last_regular_product_and_skips_order_bump(): void {
        $checkout = \GVN_Checkout::get_instance();
        $cart = \WC()->cart;
        $cart->add_to_cart(1, 1);
        $last = $cart->add_to_cart(2, 2);
        $bump = $cart->add_to_cart(3, 1, 0, [], ['gvn_order_bump' => true]);
        $checkout->remember_last_cart_item($bump);

        $checkout->keep_only_last_cart_item();

        $this->assertSame([$last], array_keys($cart->get_cart()));
        $this->assertSame(1, $cart->get_cart_item($last)['quantity']);
    }

    public function test_disabled_mode_does_not_change_cart(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_single_product_checkout'] = 'no';
        SettingsRepository::flush_cache();
        $cart = \WC()->cart;
        $cart->add_to_cart(1, 2);
        $cart->add_to_cart(2, 3);

        \GVN_Checkout::get_instance()->keep_only_last_cart_item();

        $this->assertSame([2, 3], array_column($cart->get_cart(), 'quantity'));
    }
}
