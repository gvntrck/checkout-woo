<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Support\Requirements;
use PHPUnit\Framework\TestCase;

class RequirementsTest extends TestCase {

    public function test_php_compatibility_check() {
        $this->assertTrue(Requirements::is_php_compatible());
    }

    public function test_constants_defined() {
        $this->assertEquals('7.4', Requirements::MIN_PHP_VERSION);
        $this->assertEquals('6.0', Requirements::MIN_WP_VERSION);
        $this->assertEquals('7.0', Requirements::MIN_WC_VERSION);
    }
}
