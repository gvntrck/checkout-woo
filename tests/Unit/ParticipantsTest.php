<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Checkout\Participants;
use PHPUnit\Framework\TestCase;

class ParticipantsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['wp_mock_options'] = [];
        $GLOBALS['wp_mock_filters'] = [];
        $GLOBALS['wp_mock_actions'] = [];
        $GLOBALS['wp_mock_enqueued_styles'] = [];
        $GLOBALS['wp_mock_enqueued_scripts'] = [];
        $GLOBALS['wc_mock_notices'] = [];
        $GLOBALS['wp_mock_user_caps'] = ['manage_woocommerce' => true];
        $GLOBALS['mock_woocommerce_instance'] = new \Mock_WooCommerce();
        $GLOBALS['post'] = new \WP_Post(['post_content' => '[gvn-checkout]']);
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void {
        $_POST = [];
        $_GET = [];
        unset($GLOBALS['post']);
    }

    private function enable(): Participants {
        update_option(Participants::PREFIX . 'enabled', 'yes');
        update_option(Participants::PREFIX . 'products', [1]);
        return new Participants();
    }

    private function person(): array {
        return ['name' => 'Maria Silva', 'email' => 'maria@example.com', 'cpf' => '529.982.247-25'];
    }

    private function payload(string $key, int $quantity = 1): array {
        return ['gvn_participants_nonce' => 'mock_nonce_gvn_participants',
            'gvn_participants' => [$key => ['people' => array_fill(0, $quantity, $this->person())]]];
    }

    public function test_disabled_has_no_checkout_hooks_assets_output_or_validation(): void {
        WC()->cart->add_to_cart(1, 2);
        $module = new Participants();
        $this->assertSame([], $module->groups());
        $this->assertSame('', $module->html());
        $module->enqueue();
        $this->assertSame([], $GLOBALS['wp_mock_enqueued_scripts']);
        $this->assertSame([], $GLOBALS['wp_mock_enqueued_styles']);
        $this->assertArrayNotHasKey('woocommerce_after_checkout_validation', $GLOBALS['wp_mock_filters']);
        $errors = new \WP_Error();
        $module->validate([], $errors);
        $this->assertFalse($errors->has_errors());
    }

    public function test_enabled_without_matching_products_does_nothing(): void {
        $module = $this->enable();
        WC()->cart->add_to_cart(2, 4);
        $module->enqueue();
        ob_start();
        $module->render();
        $this->assertSame('', ob_get_clean());
        $this->assertSame([], $GLOBALS['wp_mock_enqueued_scripts']);
        $errors = new \WP_Error();
        $module->validate([], $errors);
        $this->assertFalse($errors->has_errors());
        $this->assertSame(['keep' => 'value'], $module->fragments(['keep' => 'value']));
        update_option(Participants::PREFIX . 'products', ['', 0, 'invalid', ['bad']]);
        $this->assertSame([], $module->groups());
    }

    public function test_quantities_are_scoped_per_item_including_variations_and_excluding_bumps(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1, 3);
        WC()->cart->add_to_cart(2, 9);
        WC()->cart->items['variation'] = ['product_id' => 1, 'variation_id' => 7, 'quantity' => 2, 'data' => new \Mock_WC_Product(7)];
        WC()->cart->items['bump'] = ['product_id' => 1, 'quantity' => 4, 'data' => new \Mock_WC_Product(1), 'gvn_order_bump' => true];
        $this->assertSame([$key, 'variation'], array_keys($module->groups()));
        $this->assertSame([3, 2], array_column($module->groups(), 'quantity'));
        update_option(Participants::PREFIX . 'products', [7]);
        $this->assertSame(['variation'], array_keys($module->groups()));
    }

    public function test_cart_quantity_is_authoritative_and_missing_people_are_errors(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1, 3);
        $data = $this->payload($key);
        $data['gvn_participants']['fake'] = ['people' => [$this->person()]];
        $errors = new \WP_Error();
        $people = $module->collect($data, $errors);
        $this->assertSame([$key], array_keys($people));
        $this->assertCount(3, $people[$key]);
        $this->assertCount(6, $errors->get_error_messages());
        WC()->cart->set_quantity($key, 1);
        $this->assertCount(1, $module->collect($this->payload($key, 5))[$key]);
        $this->assertSame([], $GLOBALS['wc_mock_notices']);
    }

    public function test_buyer_uses_billing_identity_not_forged_first_participant(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1, 2);
        $data = $this->payload($key, 2);
        $data['gvn_participants'][$key]['buyer'] = '1';
        $data += ['billing_first_name' => 'João', 'billing_last_name' => 'Silva', 'billing_email' => 'joao@example.com', 'billing_cpf' => '529.982.247-25'];
        $errors = new \WP_Error();
        $people = $module->collect($data, $errors)[$key];
        $this->assertFalse($errors->has_errors());
        $this->assertSame('João Silva', $people[0]['name']);
        $this->assertSame('joao@example.com', $people[0]['email']);
        $this->assertSame('Maria Silva', $people[1]['name']);
        update_option(Participants::PREFIX . 'buyer', 'no');
        $this->assertSame('Maria Silva', $module->collect($data)[$key][0]['name']);
    }

    public function test_email_cpf_and_malformed_values_are_validated(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1);
        foreach ([['name' => ['bad'], 'email' => 'invalid', 'cpf' => '11111111111'], 'invalid', ['name' => 'Maria', 'email' => ['bad'], 'cpf' => ['bad']]] as $person) {
            $data = $this->payload($key);
            $data['gvn_participants'][$key]['people'][0] = $person;
            $errors = new \WP_Error();
            $module->collect($data, $errors);
            $this->assertTrue($errors->has_errors());
        }
    }

    public function test_optional_and_hidden_fields_and_sanitization(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1);
        update_option(Participants::PREFIX . 'cpf', 'hidden');
        update_option(Participants::PREFIX . 'email', 'optional');
        $data = $this->payload($key);
        $data['gvn_participants'][$key]['people'][0]['name'] = '<b>Maria</b>';
        $data['gvn_participants'][$key]['people'][0]['email'] = '';
        $errors = new \WP_Error();
        $this->assertSame(['name' => 'Maria', 'email' => ''], $module->collect($data, $errors)[$key][0]);
        $this->assertFalse($errors->has_errors());
        $this->assertStringNotContainsString('[cpf]', $module->html());
    }

    public function test_nonce_required_and_normalized_billing_wins(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1);
        $_POST = $this->payload($key);
        $_POST['gvn_participants_nonce'] = ['invalid'];
        $errors = new \WP_Error();
        $module->validate([], $errors);
        $this->assertTrue($errors->has_errors());
        $_POST = $this->payload($key);
        $errors = new \WP_Error();
        $module->validate([], $errors);
        $this->assertFalse($errors->has_errors());
        $_POST['gvn_participants'][$key]['buyer'] = '1';
        $_POST += ['billing_first_name' => 'Maria', 'billing_email' => 'valid@example.com', 'billing_cpf' => '52998224725'];
        $errors = new \WP_Error();
        $module->validate(['billing_email' => 'invalid'], $errors);
        $this->assertTrue($errors->has_errors());
    }

    public function test_ajax_fragment_preserves_values_and_reflects_new_quantity(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1);
        $_POST['post_data'] = http_build_query($this->payload($key));
        WC()->cart->set_quantity($key, 2);
        $fragment = $module->fragments(['unchanged' => 'value']);
        $this->assertSame('value', $fragment['unchanged']);
        $this->assertStringContainsString('value="Maria Silva"', $fragment['#gvn-participants']);
        $this->assertStringContainsString('Participante 2', $fragment['#gvn-participants']);
        WC()->cart->remove_cart_item($key);
        $this->assertStringNotContainsString('Participante 1', $module->fragments([])['#gvn-participants']);
    }

    public function test_both_layouts_render_inside_checkout_without_changing_billing_fields(): void {
        $this->enable();
        WC()->cart->add_to_cart(1, 2);
        foreach (['templates/checkout-template.php', 'templates/checkout/layout-split.php'] as $template) {
            $checkout = WC()->checkout();
            ob_start();
            include GVN_CHECKOUT_PLUGIN_DIR . $template;
            $html = ob_get_clean();
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//form[@name="checkout"]//section[@id="gvn-participants"]')->length);
            $this->assertSame(2, $xpath->query('//fieldset[contains(@class,"gvn-participants__person")]')->length);
            $this->assertSame(1, $xpath->query('//input[@name="billing_first_name"]')->length);
        }
    }

    public function test_save_private_item_metadata_and_admin_visibility_after_disable(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1, 2);
        $_POST = $this->payload($key, 2);
        $item = new class {
            public $meta = [];
            public function add_meta_data($key, $value, $unique) { $this->meta[$key] = $value; }
            public function get_meta($key, $single) { return $this->meta[$key] ?? ''; }
        };
        $module->save($item, 'unrelated', [], new \Mock_WC_Order_Complete());
        $this->assertSame([], $item->meta);
        unset($_POST['gvn_participants_nonce']);
        try {
            $module->save($item, $key, [], new \Mock_WC_Order_Complete());
            $this->fail('Saving without nonce must fail.');
        } catch (\Exception $exception) {
            $this->assertSame([], $item->meta);
        }
        $_POST = $this->payload($key, 2);
        $module->save($item, $key, [], new \Mock_WC_Order_Complete());
        $this->assertCount(2, $item->meta['_gvn_participants']);
        $this->assertSame('52998224725', $item->meta['_gvn_participants'][0]['cpf']);
        update_option(Participants::PREFIX . 'enabled', 'no');
        ob_start();
        $module->admin_details(1, $item, null);
        $this->assertStringContainsString('Maria Silva', ob_get_clean());
        $GLOBALS['wp_mock_user_caps'] = [];
        ob_start();
        $module->admin_details(1, $item, null);
        $this->assertSame('', ob_get_clean());
    }

    public function test_disabled_module_preserves_existing_layout_output_byte_for_byte(): void {
        WC()->cart->add_to_cart(1, 2);
        foreach (['templates/checkout-template.php', 'templates/checkout/layout-split.php'] as $template) {
            $checkout = WC()->checkout();
            ob_start();
            include GVN_CHECKOUT_PLUGIN_DIR . $template;
            $before = ob_get_clean();
            new Participants();
            ob_start();
            include GVN_CHECKOUT_PLUGIN_DIR . $template;
            $this->assertSame($before, ob_get_clean());
        }
    }

    public function test_output_escapes_raw_draft_values(): void {
        $module = $this->enable();
        $key = WC()->cart->add_to_cart(1);
        $data = $this->payload($key);
        $data['gvn_participants'][$key]['people'][0]['name'] = '"><script>alert(1)</script>';
        $html = $module->html($data);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_admin_settings_offer_safe_defaults_without_manual_field_configuration(): void {
        $settings = array_column(Participants::settings(), null, 'id');
        $this->assertSame('no', $settings[Participants::PREFIX . 'enabled']['default']);
        $this->assertSame('multiselect', $settings[Participants::PREFIX . 'products']['type']);
        foreach (['name', 'email', 'cpf'] as $field) {
            $this->assertSame('required', $settings[Participants::PREFIX . $field]['default']);
            $this->assertSame(['required', 'optional', 'hidden'], array_keys($settings[Participants::PREFIX . $field]['options']));
        }
    }

    public function test_admin_save_is_nonce_and_capability_protected_and_preserves_existing_settings(): void {
        $_GET['subtab'] = 'participants';
        update_option('gvn_checkout_button_text', 'Não alterar');
        $_POST = [Participants::PREFIX . 'enabled' => 'yes'];
        try {
            \GVN_Admin::get_instance()->save_settings();
            $this->fail('Missing admin nonce must fail.');
        } catch (\Exception $exception) {
            $this->assertSame('no', get_option(Participants::PREFIX . 'enabled', 'no'));
        }
        $_POST['_wpnonce'] = 'mock_nonce_woocommerce-settings';
        $GLOBALS['wp_mock_user_caps'] = [];
        \GVN_Admin::get_instance()->save_settings();
        $this->assertSame('no', get_option(Participants::PREFIX . 'enabled', 'no'));
        $GLOBALS['wp_mock_user_caps'] = ['manage_woocommerce' => true];
        \GVN_Admin::get_instance()->save_settings();
        $this->assertSame('yes', get_option(Participants::PREFIX . 'enabled'));
        $this->assertSame('Não alterar', get_option('gvn_checkout_button_text'));
    }
}
