<?php

namespace {
    function is_product() { return $GLOBALS['gvn_direct_context']['product']; }
    function is_preview() { return $GLOBALS['gvn_direct_context']['preview']; }
    function is_checkout() { return $GLOBALS['gvn_direct_context']['checkout']; }
    function post_password_required() { return $GLOBALS['gvn_direct_context']['password']; }
    function get_queried_object_id() { return 2; }
    function wc_get_page_id($page) { return $GLOBALS['gvn_direct_context']['checkout_id']; }
    function nocache_headers() {}
    function wp_safe_redirect($url) { throw new \RuntimeException($url); }
}

namespace GVN\Checkout\Tests\Unit {

    use GVN\Checkout\Settings\SettingsRepository;
    use GVN\Checkout\Settings\SettingsSchema;
    use PHPUnit\Framework\TestCase;

    class DirectProductCheckoutTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['mock_woocommerce_instance'] = new \Mock_WooCommerce();
            $GLOBALS['wp_mock_options'] = [
                'gvn_checkout_direct_product_checkout' => 'yes',
                'gvn_checkout_single_product_checkout' => 'yes',
            ];
            $GLOBALS['gvn_direct_context'] = [
                'product' => true, 'preview' => false, 'checkout' => false,
                'password' => false, 'checkout_id' => 10,
            ];
            $GLOBALS['wc_mock_products'] = [2 => new \Mock_WC_Product(2)];
            $GLOBALS['wc_mock_notices'] = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            SettingsRepository::flush_cache();
        }

        protected function tearDown(): void {
            unset($GLOBALS['wc_mock_products'], $GLOBALS['gvn_direct_context']);
            unset($_SERVER['REQUEST_METHOD']);
            SettingsRepository::flush_cache();
        }

        private function buy(): void {
            try {
                \GVN_Checkout::get_instance()->redirect_product_to_checkout();
                $this->fail('Expected checkout redirect');
            } catch (\RuntimeException $exception) {
                $this->assertSame(wc_get_checkout_url(), $exception->getMessage());
            }
        }

        public function test_adds_one_unit_and_keeps_last_product(): void {
            \WC()->cart->add_to_cart(1, 3);
            $this->buy();
            $this->assertSame([2], array_column(\WC()->cart->get_cart(), 'product_id'));
            $this->assertSame([1], array_column(\WC()->cart->get_cart(), 'quantity'));
        }

        public function test_revisiting_existing_product_reuses_line(): void {
            $key = \WC()->cart->add_to_cart(2, 3);
            \WC()->cart->add_to_cart(1, 1);
            $this->buy();
            $this->buy();
            $this->assertSame([$key], array_keys(\WC()->cart->get_cart()));
            $this->assertSame(1, \WC()->cart->get_cart_item($key)['quantity']);
        }

        public function test_preserves_other_products_when_single_product_mode_is_off(): void {
            $GLOBALS['wp_mock_options']['gvn_checkout_single_product_checkout'] = 'no';
            SettingsRepository::flush_cache();
            \WC()->cart->add_to_cart(1, 3);
            $this->buy();
            $this->assertSame([1, 2], array_column(\WC()->cart->get_cart(), 'product_id'));
        }

        public function test_disabled_by_default_and_sanitized(): void {
            $this->assertSame('no', SettingsSchema::get_defaults()['direct_product_checkout']);
            $this->assertSame('yes', SettingsSchema::sanitize_setting('direct_product_checkout', 'yes'));
            $this->assertSame('no', SettingsSchema::sanitize_setting('direct_product_checkout', 'other'));
            $GLOBALS['wp_mock_options']['gvn_checkout_direct_product_checkout'] = 'no';
            SettingsRepository::flush_cache();
            \GVN_Checkout::get_instance()->redirect_product_to_checkout();
            $this->assertTrue(\WC()->cart->is_empty());
        }

        public function test_unsupported_product_keeps_single_page(): void {
            $GLOBALS['wc_mock_products'][2]->type = 'variable';
            \GVN_Checkout::get_instance()->redirect_product_to_checkout();
            $this->assertTrue(\WC()->cart->is_empty());
            $this->assertSame([], $GLOBALS['wc_mock_notices']);
        }

        public function test_unavailable_product_preserves_cart_and_shows_error(): void {
            $key = \WC()->cart->add_to_cart(1, 3);
            $GLOBALS['wc_mock_products'][2]->set_in_stock(false);
            \GVN_Checkout::get_instance()->redirect_product_to_checkout();
            $this->assertSame([$key], array_keys(\WC()->cart->get_cart()));
            $this->assertSame('error', $GLOBALS['wc_mock_notices'][0]['type']);
        }

        public function test_validation_failure_preserves_cart(): void {
            $key = \WC()->cart->add_to_cart(1, 3);
            $validation = static function () { return false; };
            add_filter('woocommerce_add_to_cart_validation', $validation);
            try {
                \GVN_Checkout::get_instance()->redirect_product_to_checkout();
                $this->assertSame([$key], array_keys(\WC()->cart->get_cart()));
                $this->assertNotEmpty($GLOBALS['wc_mock_notices']);
            } finally {
                remove_filter('woocommerce_add_to_cart_validation', $validation);
            }
        }

        public function test_non_purchasable_product_shows_error(): void {
            $GLOBALS['wc_mock_products'][2]->set_purchasable(false);
            \GVN_Checkout::get_instance()->redirect_product_to_checkout();
            $this->assertTrue(\WC()->cart->is_empty());
            $this->assertNotEmpty($GLOBALS['wc_mock_notices']);
        }

        public function test_cart_rejection_preserves_existing_product(): void {
            \WC()->cart = new class extends \Mock_WC_Cart {
                public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variation = [], $cart_item_data = []) {
                    return 2 === $product_id ? false : parent::add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
                }
            };
            $key = \WC()->cart->add_to_cart(1, 3);
            \GVN_Checkout::get_instance()->redirect_product_to_checkout();
            $this->assertSame([$key], array_keys(\WC()->cart->get_cart()));
            $this->assertNotEmpty($GLOBALS['wc_mock_notices']);
        }

        public function test_preview_checkout_password_and_missing_checkout_do_not_buy(): void {
            foreach (['product' => false, 'preview' => true, 'checkout' => true, 'password' => true, 'checkout_id' => 0] as $key => $value) {
                $original = $GLOBALS['gvn_direct_context'][$key];
                $GLOBALS['gvn_direct_context'][$key] = $value;
                \GVN_Checkout::get_instance()->redirect_product_to_checkout();
                $this->assertTrue(\WC()->cart->is_empty());
                $GLOBALS['gvn_direct_context'][$key] = $original;
            }
            $_SERVER['REQUEST_METHOD'] = 'POST';
            \GVN_Checkout::get_instance()->redirect_product_to_checkout();
            $this->assertTrue(\WC()->cart->is_empty());
        }
    }
}
