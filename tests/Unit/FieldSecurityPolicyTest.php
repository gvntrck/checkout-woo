<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Fields\FieldSecurityPolicy;
use PHPUnit\Framework\TestCase;

class FieldSecurityPolicyTest extends TestCase {

    public function test_allows_valid_types_and_rejects_invalid_types(): void {
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('text'));
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('email'));
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('tel'));
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('number'));
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('textarea'));
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('select'));
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('date'));
        $this->assertTrue(FieldSecurityPolicy::is_valid_type('password'));

        $this->assertFalse(FieldSecurityPolicy::is_valid_type('file'));
        $this->assertFalse(FieldSecurityPolicy::is_valid_type('hidden_admin'));
        $this->assertFalse(FieldSecurityPolicy::is_valid_type('exec'));
    }

    public function test_blocks_reserved_order_and_payment_keys(): void {
        $reserved_keys = [
            '_order_total',
            'total',
            '_order_subtotal',
            '_order_tax',
            '_payment_method',
            'payment_method',
            '_transaction_id',
            'transaction_id',
            '_status',
            'status',
            '_customer_user',
            'customer_user',
            '_order_key',
            'order_key',
            'id',
            'order_id',
        ];

        foreach ($reserved_keys as $key) {
            $this->assertTrue(FieldSecurityPolicy::is_reserved_key($key), "A chave reservada '{$key}' deveria ser bloqueada.");
        }

        $this->assertFalse(FieldSecurityPolicy::is_reserved_key('billing_cpf'));
        $this->assertFalse(FieldSecurityPolicy::is_reserved_key('billing_number'));
        $this->assertFalse(FieldSecurityPolicy::is_reserved_key('custom_delivery_date'));
    }

    public function test_identifies_native_woo_keys(): void {
        $this->assertTrue(FieldSecurityPolicy::is_native_woo_key('billing_first_name'));
        $this->assertTrue(FieldSecurityPolicy::is_native_woo_key('billing_last_name'));
        $this->assertTrue(FieldSecurityPolicy::is_native_woo_key('billing_email'));
        $this->assertTrue(FieldSecurityPolicy::is_native_woo_key('billing_address_1'));
        $this->assertTrue(FieldSecurityPolicy::is_native_woo_key('shipping_city'));
        $this->assertTrue(FieldSecurityPolicy::is_native_woo_key('order_comments'));

        $this->assertFalse(FieldSecurityPolicy::is_native_woo_key('billing_cpf'));
        $this->assertFalse(FieldSecurityPolicy::is_native_woo_key('billing_number'));
        $this->assertFalse(FieldSecurityPolicy::is_native_woo_key('billing_neighborhood'));
    }

    public function test_validates_persistable_custom_field(): void {
        // Campo custom válido e ativo
        $valid_field = [
            'key'     => 'billing_cpf',
            'type'    => 'text',
            'enabled' => true,
        ];
        $this->assertTrue(FieldSecurityPolicy::is_persistable_custom_field($valid_field));

        // Campo desativado não é persistível
        $disabled_field = [
            'key'     => 'billing_cpf',
            'type'    => 'text',
            'enabled' => false,
        ];
        $this->assertFalse(FieldSecurityPolicy::is_persistable_custom_field($disabled_field));

        // Campo tentando injetar chave reservada
        $attack_field = [
            'key'     => '_order_total',
            'type'    => 'number',
            'enabled' => true,
        ];
        $this->assertFalse(FieldSecurityPolicy::is_persistable_custom_field($attack_field));

        // Campo nativo do WooCommerce (não deve ser duplicado pelo persister customizado)
        $native_field = [
            'key'     => 'billing_first_name',
            'type'    => 'text',
            'enabled' => true,
        ];
        $this->assertFalse(FieldSecurityPolicy::is_persistable_custom_field($native_field));
    }
}
