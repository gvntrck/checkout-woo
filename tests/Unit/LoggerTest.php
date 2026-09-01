<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Support\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase {

    public function test_request_id_is_stable_within_request() {
        $id1 = Logger::get_request_id();
        $id2 = Logger::get_request_id();
        $this->assertEquals($id1, $id2);
        $this->assertNotEmpty($id1);
    }

    public function test_sensitive_context_is_redacted() {
        $context = [
            'user_id'       => 123,
            'cpf'           => '111.222.333-44',
            'password'      => 'secret123',
            'order_total'   => '100.00',
            'customer_card' => '4111111111111111',
        ];

        $sanitized = Logger::sanitize_context($context);

        $this->assertEquals(123, $sanitized['user_id']);
        $this->assertEquals('100.00', $sanitized['order_total']);
        $this->assertEquals('[REDACTED]', $sanitized['cpf']);
        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('[REDACTED]', $sanitized['customer_card']);
    }
}
