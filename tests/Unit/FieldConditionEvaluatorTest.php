<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Fields\FieldConditionEvaluator;
use PHPUnit\Framework\TestCase;

class FieldConditionEvaluatorTest extends TestCase {

    public function test_field_without_conditions_is_always_visible(): void {
        $field = ['key' => 'billing_first_name', 'label' => 'Nome'];
        $this->assertTrue(FieldConditionEvaluator::is_field_visible($field, []));
        $this->assertTrue(FieldConditionEvaluator::is_field_visible($field, ['billing_first_name' => 'João']));
    }

    public function test_evaluates_equals_operator(): void {
        $rule = ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => '1'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule, '1'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule, '2'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule, ''));

        // Alias '=='
        $rule_alias = ['field' => 'billing_persontype', 'operator' => '==', 'value' => 'pj'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule_alias, 'pj'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_alias, 'pf'));
    }

    public function test_evaluates_not_equals_operator(): void {
        $rule = ['field' => 'billing_persontype', 'operator' => 'not_equals', 'value' => '1'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule, '2'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule, '1'));

        // Alias '!='
        $rule_alias = ['field' => 'billing_persontype', 'operator' => '!=', 'value' => 'pj'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule_alias, 'pf'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_alias, 'pj'));
    }

    public function test_evaluates_filled_and_empty_operators(): void {
        $rule_filled = ['field' => 'billing_company', 'operator' => 'filled'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule_filled, 'Minha Empresa'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_filled, ''));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_filled, '   '));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_filled, null));

        $rule_empty = ['field' => 'billing_company', 'operator' => 'empty'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule_empty, ''));
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule_empty, '   '));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_empty, 'Empresa LTDA'));
    }

    public function test_evaluates_contains_operator(): void {
        $rule = ['field' => 'billing_email', 'operator' => 'contains', 'value' => '@gmail.com'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule, 'teste@gmail.com'));
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule, 'TESTE@GMAIL.COM')); // Case-insensitive
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule, 'teste@hotmail.com'));
    }

    public function test_evaluates_greater_and_less_operators(): void {
        $rule_greater = ['field' => 'age', 'operator' => 'greater', 'value' => '18'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule_greater, '25'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_greater, '18'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_greater, '15'));

        $rule_less = ['field' => 'age', 'operator' => 'less', 'value' => '18'];
        $this->assertTrue(FieldConditionEvaluator::evaluate_rule($rule_less, '15'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_less, '18'));
        $this->assertFalse(FieldConditionEvaluator::evaluate_rule($rule_less, '25'));
    }

    public function test_evaluates_and_logic_conditions(): void {
        $field = [
            'key'        => 'billing_ie',
            'conditions' => [
                'logic' => 'and',
                'rules' => [
                    ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pj'],
                    ['field' => 'billing_has_ie', 'operator' => 'equals', 'value' => 'yes'],
                ],
            ],
        ];

        // Ambos batem -> visível
        $this->assertTrue(FieldConditionEvaluator::is_field_visible($field, [
            'billing_persontype' => 'pj',
            'billing_has_ie'     => 'yes',
        ]));

        // Um falha -> oculto
        $this->assertFalse(FieldConditionEvaluator::is_field_visible($field, [
            'billing_persontype' => 'pj',
            'billing_has_ie'     => 'no',
        ]));

        $this->assertFalse(FieldConditionEvaluator::is_field_visible($field, [
            'billing_persontype' => 'pf',
            'billing_has_ie'     => 'yes',
        ]));
    }

    public function test_evaluates_or_logic_conditions(): void {
        $field = [
            'key'        => 'billing_doc',
            'conditions' => [
                'logic' => 'or',
                'rules' => [
                    ['field' => 'billing_country', 'operator' => 'equals', 'value' => 'BR'],
                    ['field' => 'billing_custom_flag', 'operator' => 'equals', 'value' => '1'],
                ],
            ],
        ];

        // Primeiro bate -> visível
        $this->assertTrue(FieldConditionEvaluator::is_field_visible($field, [
            'billing_country' => 'BR',
            'billing_custom_flag' => '0',
        ]));

        // Segundo bate -> visível
        $this->assertTrue(FieldConditionEvaluator::is_field_visible($field, [
            'billing_country' => 'US',
            'billing_custom_flag' => '1',
        ]));

        // Nenhum bate -> oculto
        $this->assertFalse(FieldConditionEvaluator::is_field_visible($field, [
            'billing_country' => 'US',
            'billing_custom_flag' => '0',
        ]));
    }
}
