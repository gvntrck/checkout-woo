<?php

namespace GVN\Checkout\Tests\Unit;

use GVN_Custom_Fields;
use GVN\Checkout\Fields\FieldSanitizer;
use GVN\Checkout\Fields\FieldValidator;
use PHPUnit\Framework\TestCase;

class RadioFieldTest extends TestCase {
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

    public function test_radio_saves_registers_and_renders_in_both_layouts(): void {
        $_POST['fields'] = addslashes(json_encode([[
            'key' => 'course_choice', 'label' => 'Curso', 'type' => 'radio',
            'enabled' => true, 'required' => true, 'options' => "0|Primeiro\n1|Segundo", 'default_option' => '0',
        ]]));
        ob_start();
        GVN_Custom_Fields::get_instance()->ajax_save_fields();
        $response = json_decode(ob_get_clean(), true);
        $this->assertTrue($response['success']);
        $this->assertSame('radio', $response['data']['fields'][0]['type']);
        $registered = GVN_Custom_Fields::get_instance()->register_custom_fields_with_woo([]);
        $this->assertSame('radio', $registered['billing']['course_choice']['type']);
        $this->assertSame('0', $registered['billing']['course_choice']['default']);
        $this->assertCount(2, $registered['billing']['course_choice']['options']);

        foreach (['templates/checkout-template.php', 'templates/checkout/layout-split.php'] as $template) {
            $checkout = WC()->checkout();
            ob_start();
            include GVN_CHECKOUT_PLUGIN_DIR . $template;
            $html = ob_get_clean();
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(2, $xpath->query('//input[@type="radio" and @name="course_choice" and @required]')->length);
            $this->assertSame(1, $xpath->query('//input[@name="course_choice" and @value="0" and @checked]')->length);
            $this->assertSame(1, $xpath->query('//input[@id="course_choice"]')->length);
            $this->assertSame(1, $xpath->query('//input[@id="course_choice-radio-1"]')->length);
        }
    }

    public function test_radio_accepts_only_configured_choices_and_enforces_required(): void {
        $field = ['key' => 'course_choice', 'label' => 'Curso', 'type' => 'radio', 'enabled' => true, 'required' => true, 'options' => "0|Primeiro\n1|Segundo"];
        $this->assertSame('0', FieldSanitizer::sanitize('radio', '0', $field));
        $this->assertSame('', FieldSanitizer::sanitize('radio', 'invalid', $field));
        $this->assertSame('', FieldSanitizer::sanitize('radio', ['0'], $field));
        $this->assertSame([], FieldValidator::validate([$field], ['course_choice' => '0']));
        $this->assertArrayHasKey('course_choice', FieldValidator::validate([$field], []));
        $this->assertArrayHasKey('course_choice', FieldValidator::validate([$field], ['course_choice' => 'invalid']));
    }
}
