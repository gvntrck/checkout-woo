<?php

namespace GVN\Checkout\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PluginBasicsTest extends TestCase {

    public function test_plugin_version_constant_is_defined() {
        $this->assertTrue(defined('GVN_CHECKOUT_VERSION'));
        $this->assertNotEmpty(GVN_CHECKOUT_VERSION);
    }

    public function test_multistep_visibility_uses_boolean_toggle_class_state() {
        $script = file_get_contents(GVN_CHECKOUT_PLUGIN_DIR . 'assets/js/gvn-checkout.js');

        $this->assertStringContainsString("toggleClass('gvn-step-hidden', $(this).data('gvn-step') !== step.id)", $script);
        $this->assertStringNotContainsString("toggleClass('gvn-step-hidden', function", $script);
    }

    public function test_fixture_options_are_valid_json() {
        $fixtures_dir = GVN_CHECKOUT_PLUGIN_DIR . 'tests/fixtures/options/';
        $files = glob($fixtures_dir . '*.json');
        
        $this->assertNotEmpty($files, 'Deve haver ao menos uma fixture de options.');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $json = json_decode($content, true);
            $this->assertNotNull($json, "JSON malformado no arquivo: {$file}");
            $this->assertArrayHasKey('metadata', $json, "Chave metadata ausente em {$file}");
            $this->assertArrayHasKey('options', $json, "Chave options ausente em {$file}");
        }
    }

    public function test_seed_store_fixture_is_valid() {
        $seed_file = GVN_CHECKOUT_PLUGIN_DIR . 'tests/fixtures/seeds/store-seed.json';
        $this->assertFileExists($seed_file);
        
        $json = json_decode(file_get_contents($seed_file), true);
        $this->assertNotNull($json);
        $this->assertArrayHasKey('products', $json);
        $this->assertArrayHasKey('coupons', $json);
        $this->assertArrayHasKey('sample_customers', $json);
    }
}
