<?php

namespace GVN\Checkout\Tests\Unit;

use GVN_Custom_Fields;
use PHPUnit\Framework\TestCase;

class CustomFieldsConditionsTest extends TestCase {

    public function test_sanitize_conditions_normalizes_legacy_aliases(): void {
        $sanitized = GVN_Custom_Fields::sanitize_conditions([
            'logic' => 'and',
            'rules' => [
                ['field' => 'billing_persontype', 'operator' => '==', 'value' => 'pf'],
                ['field' => 'age', 'operator' => '>', 'value' => '18'],
            ],
        ]);

        $this->assertSame('and', $sanitized['logic']);
        $this->assertCount(2, $sanitized['rules']);
        $this->assertSame('equals', $sanitized['rules'][0]['operator']);
        $this->assertSame('greater', $sanitized['rules'][1]['operator']);
    }

    public function test_sanitize_conditions_discards_invalid_rules_and_self_reference(): void {
        $sanitized = GVN_Custom_Fields::sanitize_conditions(
            [
                'logic' => 'or',
                'rules' => [
                    ['field' => 'billing_cpf', 'operator' => 'equals', 'value' => 'x'], // auto-referência
                    ['field' => 'billing_persontype', 'operator' => 'between', 'value' => 'x'], // operador inválido
                    ['field' => '', 'operator' => 'equals', 'value' => 'x'], // sem trigger
                    ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pf'], // válida
                ],
            ],
            'billing_cpf'
        );

        $this->assertSame('or', $sanitized['logic']);
        $this->assertCount(1, $sanitized['rules']);
        $this->assertSame('billing_persontype', $sanitized['rules'][0]['field']);
    }

    public function test_sanitize_conditions_forces_empty_value_for_presence_operators(): void {
        $sanitized = GVN_Custom_Fields::sanitize_conditions([
            'rules' => [
                ['field' => 'billing_company', 'operator' => 'filled', 'value' => 'deveria-sumir'],
                ['field' => 'billing_company', 'operator' => 'empty', 'value' => 'deveria-sumir'],
            ],
        ]);

        $this->assertSame('', $sanitized['rules'][0]['value']);
        $this->assertSame('', $sanitized['rules'][1]['value']);
    }

    public function test_sanitize_conditions_enforces_max_rules_cap(): void {
        $rules = [];
        for ($i = 0; $i < 15; $i++) {
            $rules[] = ['field' => 'trigger_' . $i, 'operator' => 'filled', 'value' => ''];
        }

        $sanitized = GVN_Custom_Fields::sanitize_conditions(['rules' => $rules]);

        $this->assertCount(GVN_Custom_Fields::MAX_CONDITION_RULES, $sanitized['rules']);
    }

    public function test_sanitize_conditions_accepts_legacy_numeric_list(): void {
        $sanitized = GVN_Custom_Fields::sanitize_conditions([
            ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pj'],
        ]);

        $this->assertSame('and', $sanitized['logic']);
        $this->assertCount(1, $sanitized['rules']);
        $this->assertSame('billing_persontype', $sanitized['rules'][0]['field']);
    }

    public function test_has_conditions(): void {
        $this->assertFalse(GVN_Custom_Fields::has_conditions([]));
        $this->assertFalse(GVN_Custom_Fields::has_conditions(['conditions' => ['logic' => 'and', 'rules' => []]]));
        $this->assertTrue(GVN_Custom_Fields::has_conditions([
            'conditions' => [
                'logic' => 'and',
                'rules' => [['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pf']],
            ],
        ]));
    }

    public function test_find_condition_cycle_detects_direct_cycle(): void {
        $cycle = GVN_Custom_Fields::find_condition_cycle([
            [
                'key'        => 'field_a',
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_b', 'operator' => 'filled', 'value' => '']]],
            ],
            [
                'key'        => 'field_b',
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_a', 'operator' => 'filled', 'value' => '']]],
            ],
        ]);

        $this->assertNotEmpty($cycle);
        $this->assertSame($cycle[0], end($cycle));
    }

    public function test_find_condition_cycle_detects_indirect_cycle(): void {
        $cycle = GVN_Custom_Fields::find_condition_cycle([
            [
                'key'        => 'field_a',
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_b', 'operator' => 'filled', 'value' => '']]],
            ],
            [
                'key'        => 'field_b',
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_c', 'operator' => 'filled', 'value' => '']]],
            ],
            [
                'key'        => 'field_c',
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_a', 'operator' => 'filled', 'value' => '']]],
            ],
        ]);

        $this->assertNotEmpty($cycle);
    }

    public function test_find_condition_cycle_ignores_payment_method_and_acyclic_graphs(): void {
        $cycle = GVN_Custom_Fields::find_condition_cycle([
            [
                'key'        => 'field_a',
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'payment_method', 'operator' => 'equals', 'value' => 'pix']]],
            ],
            [
                'key'        => 'field_b',
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_a', 'operator' => 'filled', 'value' => '']]],
            ],
            ['key' => 'field_c'],
        ]);

        $this->assertSame([], $cycle);
    }

    public function test_validate_conditions_batch_reports_warnings(): void {
        $result = GVN_Custom_Fields::validate_conditions_batch([
            [
                'key'        => 'billing_cnpj',
                'enabled'    => true,
                'conditions' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'ghost_trigger', 'operator' => 'equals', 'value' => 'x'], // inexistente
                        ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'xx'], // valor fora das opções
                    ],
                ],
            ],
            [
                'key'      => 'billing_persontype',
                'type'     => 'select',
                'enabled'  => false, // desativado
                'options'  => "pf|Pessoa Física\npj|Pessoa Jurídica",
            ],
            [
                'key'        => 'billing_ie',
                'enabled'    => true,
                'conditions' => [
                    'logic' => 'and',
                    'rules' => [['field' => 'billing_cnpj', 'operator' => 'filled', 'value' => '']], // cascata
                ],
            ],
        ]);

        $this->assertEmpty($result['errors']);
        $this->assertNotEmpty($result['warnings']);

        $joined = implode(' ', $result['warnings']);
        $this->assertStringContainsString('ghost_trigger', $joined);
        $this->assertStringContainsString('billing_persontype', $joined);
        $this->assertStringContainsString('cascata', $joined);
    }

    public function test_validate_conditions_batch_reports_cycle_as_error(): void {
        $result = GVN_Custom_Fields::validate_conditions_batch([
            [
                'key'        => 'field_a',
                'enabled'    => true,
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_b', 'operator' => 'filled', 'value' => '']]],
            ],
            [
                'key'        => 'field_b',
                'enabled'    => true,
                'conditions' => ['logic' => 'and', 'rules' => [['field' => 'field_a', 'operator' => 'filled', 'value' => '']]],
            ],
        ]);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Ciclo detectado', $result['errors'][0]);
    }
}
