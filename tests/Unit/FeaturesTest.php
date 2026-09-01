<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Support\Features;
use PHPUnit\Framework\TestCase;

class FeaturesTest extends TestCase {

    public function test_clean_install_defaults() {
        $defaults = Features::get_clean_install_defaults();
        $this->assertArrayHasKey(Features::FLAG_NEW_SETTINGS_SCHEMA, $defaults);
        $this->assertTrue($defaults[Features::FLAG_NEW_SETTINGS_SCHEMA]);
        $this->assertTrue($defaults[Features::FLAG_CANONICAL_FIELD_MANAGER]);
        $this->assertFalse($defaults[Features::FLAG_DIAGNOSTIC_MODE]);
    }

    public function test_upgrade_defaults_keep_new_flags_off() {
        $defaults = Features::get_upgrade_defaults();
        $this->assertFalse($defaults[Features::FLAG_NEW_SETTINGS_SCHEMA]);
        $this->assertFalse($defaults[Features::FLAG_CANONICAL_FIELD_MANAGER]);
    }
}
