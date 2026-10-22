<?php

namespace GVN\Checkout\Tests\Unit;

use GVN_Custom_Fields;
use PHPUnit\Framework\TestCase;

class FieldGroupsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['wp_mock_options'] = [];
        $GLOBALS['wp_mock_actions'] = [];
        $GLOBALS['wp_mock_filters'] = [];
        $GLOBALS['wp_mock_user_caps'] = ['manage_woocommerce' => true];
        $GLOBALS['mock_woocommerce_instance'] = null;
        $_POST = [];
    }

    protected function tearDown(): void {
        $_POST = [];
    }

    public function test_groups_are_optional_and_unsafe_styles_are_rejected(): void {
        $this->assertSame([], GVN_Custom_Fields::get_groups());
        $groups = GVN_Custom_Fields::sanitize_groups([
            ['id' => 'aluno', 'title' => '<b>Aluno</b>', 'border_color' => 'red;display:none', 'background' => ['bad'], 'border_style' => 'url(x)', 'border_width' => 200, 'padding' => -1, 'radius' => 99],
            ['id' => 'aluno', 'title' => 'Duplicado'],
            ['id' => ''], ['id' => []], 'invalid',
        ]);
        $this->assertCount(1, $groups);
        $this->assertSame('Aluno', $groups[0]['title']);
        $this->assertSame('#d1d5db', $groups[0]['border_color']);
        $this->assertSame('#f8f9fa', $groups[0]['background']);
        $this->assertSame('solid', $groups[0]['border_style']);
        $this->assertSame(8, $groups[0]['border_width']);
        $this->assertSame(0, $groups[0]['padding']);
        $this->assertSame(40, $groups[0]['radius']);
    }

    public function test_boundaries_preserve_order_and_close_at_step_changes(): void {
        $groups = GVN_Custom_Fields::sanitize_groups([['id' => 'aluno', 'title' => 'Aluno & comprador']]);
        $open = '';
        $this->assertSame('', GVN_Custom_Fields::group_boundary_html(['key' => 'unwrapped'], $groups, $open));
        $field = ['group_id' => 'aluno', 'step_id' => 'a'];
        $html = GVN_Custom_Fields::group_boundary_html($field, $groups, $open);
        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('Aluno &amp; comprador', $html);
        $this->assertSame('', GVN_Custom_Fields::group_boundary_html($field, $groups, $open));
        $field['step_id'] = 'b';
        $this->assertStringStartsWith('</div><div', GVN_Custom_Fields::group_boundary_html($field, $groups, $open));
        $this->assertSame('</div>', GVN_Custom_Fields::group_boundary_html(['group_id' => 'deleted'], $groups, $open));
        $this->assertSame('', $open);
    }

    public function test_ajax_saves_membership_and_deleting_group_preserves_fields(): void {
        $_POST['fields'] = json_encode([
            ['key' => 'billing_first_name', 'enabled' => true, 'group_id' => 'aluno'],
            ['key' => 'billing_last_name', 'enabled' => true, 'group_id' => 'unknown'],
        ]);
        $_POST['groups'] = json_encode([['id' => 'aluno', 'border_color' => '#abc', 'border_style' => 'dashed', 'border_width' => 2]]);
        ob_start();
        GVN_Custom_Fields::get_instance()->ajax_save_fields();
        $response = json_decode(ob_get_clean(), true);
        $this->assertTrue($response['success']);
        $this->assertSame('aluno', $response['data']['fields'][0]['group_id']);
        $this->assertSame('', $response['data']['fields'][1]['group_id']);
        $this->assertSame('#abc', GVN_Custom_Fields::get_groups()[0]['border_color']);
        $_POST['groups'] = '[]';
        ob_start();
        GVN_Custom_Fields::get_instance()->ajax_save_fields();
        $response = json_decode(ob_get_clean(), true);
        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['data']['fields']);
        $this->assertSame('', $response['data']['fields'][0]['group_id']);
        $this->assertSame([], GVN_Custom_Fields::get_groups());
    }

    public function test_ajax_rejects_malformed_groups_without_overwriting_settings(): void {
        $GLOBALS['wp_mock_options'][GVN_Custom_Fields::GROUPS_OPTION_KEY] = [['id' => 'existing']];
        $_POST['fields'] = '[]';
        $_POST['groups'] = ['invalid'];
        ob_start();
        GVN_Custom_Fields::get_instance()->ajax_save_fields();
        $response = json_decode(ob_get_clean(), true);
        $this->assertFalse($response['success']);
        $this->assertSame('existing', GVN_Custom_Fields::get_groups()[0]['id']);
    }

    public function test_both_layouts_wrap_only_selected_fields_and_keep_ids_and_conditions(): void {
        $GLOBALS['wp_mock_options'][GVN_Custom_Fields::GROUPS_OPTION_KEY] = [['id' => 'aluno', 'title' => 'Dados do aluno', 'border_color' => '#aabbcc', 'border_width' => 2]];
        $GLOBALS['wp_mock_options'][GVN_Custom_Fields::OPTION_KEY] = [
            ['key' => 'billing_first_name', 'label' => 'Nome', 'enabled' => true, 'position' => 1, 'group_id' => 'aluno'],
            ['key' => 'billing_last_name', 'label' => 'Sobrenome', 'enabled' => true, 'position' => 2, 'group_id' => 'aluno', 'conditions' => ['rules' => [['field' => 'billing_first_name', 'operator' => 'filled']]]],
            ['key' => 'billing_email', 'label' => 'Email', 'enabled' => true, 'position' => 3],
        ];
        foreach (['templates/checkout-template.php', 'templates/checkout/layout-split.php'] as $template) {
            $checkout = WC()->checkout();
            ob_start();
            include GVN_CHECKOUT_PLUGIN_DIR . $template;
            $html = ob_get_clean();
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//div[@class="gvn-field-group"]')->length);
            $this->assertSame(2, $xpath->query('//div[@class="gvn-field-group"]/div[@data-field-key]')->length);
            $this->assertSame(0, $xpath->query('//div[@class="gvn-field-group"]//input[@id="billing_email"]')->length);
            $this->assertSame(1, $xpath->query('//input[@id="billing_first_name" and @name="billing_first_name"]')->length);
            $this->assertStringContainsString('data-conditions=', $html);
            $this->assertStringContainsString('border:2px solid #aabbcc', $html);
        }
    }

    public function test_final_group_closes_before_gateway_fields_in_both_layouts(): void {
        $GLOBALS['wp_mock_options'][GVN_Custom_Fields::GROUPS_OPTION_KEY] = [['id' => 'all', 'title' => 'Todos os dados']];
        $fields = GVN_Custom_Fields::get_default_fields();
        foreach ($fields as &$field) {
            $field['group_id'] = 'all';
        }
        unset($field);
        $GLOBALS['wp_mock_options'][GVN_Custom_Fields::OPTION_KEY] = $fields;
        foreach (['templates/checkout-template.php', 'templates/checkout/layout-split.php'] as $template) {
            $checkout = WC()->checkout();
            ob_start();
            include GVN_CHECKOUT_PLUGIN_DIR . $template;
            $html = ob_get_clean();
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//div[@class="gvn-field-group"]')->length);
            $this->assertSame(1, $xpath->query('//div[@class="gvn-field-group"]//textarea[@name="order_comments"]')->length);
            $this->assertSame(0, $xpath->query('//div[@class="gvn-fields-dynamic"]//*[@id="payment" or @id="place_order"]')->length);
            $this->assertSame(0, $xpath->query('//div[@class="gvn-field-group"]//input[@type="hidden"]')->length);
            $this->assertSame(1, $xpath->query('//form[@name="checkout"]//*[@id="place_order"]')->length);
        }
    }

    public function test_no_groups_adds_no_wrappers_and_keeps_checkout_contract(): void {
        foreach (['templates/checkout-template.php', 'templates/checkout/layout-split.php'] as $template) {
            $checkout = WC()->checkout();
            ob_start();
            include GVN_CHECKOUT_PLUGIN_DIR . $template;
            $html = ob_get_clean();
            $this->assertStringNotContainsString('class="gvn-field-group"', $html);
            $this->assertStringContainsString('name="billing_first_name"', $html);
            $this->assertStringContainsString('id="place_order"', $html);
        }
    }
}
