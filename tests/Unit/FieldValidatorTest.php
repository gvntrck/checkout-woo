<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Fields\FieldValidator;
use PHPUnit\Framework\TestCase;
use WP_Error;

class FieldValidatorTest extends TestCase {

    public function test_validates_required_visible_field_missing_value(): void {
        $fields = [
            [
                'key'      => 'billing_cpf',
                'label'    => 'CPF',
                'type'     => 'text',
                'required' => true,
                'enabled'  => true,
                'mask'     => 'cpf',
            ],
        ];

        $errors = new WP_Error();
        $validation_errors = FieldValidator::validate($fields, ['billing_cpf' => ''], $errors);

        $this->assertArrayHasKey('billing_cpf', $validation_errors);
        $this->assertTrue($errors->has_errors());
        $this->assertStringContainsString('O campo "CPF" é obrigatório', $validation_errors['billing_cpf']);
    }

    public function test_passes_when_required_visible_field_is_provided(): void {
        $fields = [
            [
                'key'      => 'billing_cpf',
                'label'    => 'CPF',
                'type'     => 'text',
                'required' => true,
                'enabled'  => true,
                'mask'     => 'cpf',
            ],
        ];

        $errors = new WP_Error();
        $validation_errors = FieldValidator::validate($fields, ['billing_cpf' => '111.444.777-35'], $errors);

        $this->assertEmpty($validation_errors);
        $this->assertFalse($errors->has_errors());
    }

    public function test_rejects_invalid_cpf(): void {
        $fields = [
            [
                'key'      => 'billing_cpf',
                'label'    => 'CPF',
                'type'     => 'text',
                'required' => true,
                'enabled'  => true,
                'mask'     => 'cpf',
            ],
        ];

        foreach (['2', '111.111.111-11', '111.444.777-34'] as $cpf) {
            $errors = new WP_Error();
            $validation_errors = FieldValidator::validate($fields, ['billing_cpf' => $cpf], $errors);

            $this->assertArrayHasKey('billing_cpf', $validation_errors);
            $this->assertTrue($errors->has_errors());
            $this->assertStringContainsString('CPF válido', $validation_errors['billing_cpf']);
        }
    }

    public function test_does_not_require_field_when_hidden_by_condition(): void {
        $fields = [
            [
                'key'      => 'billing_persontype',
                'label'    => 'Tipo de Pessoa',
                'type'     => 'select',
                'required' => true,
                'enabled'  => true,
            ],
            [
                'key'        => 'billing_cnpj',
                'label'      => 'CNPJ',
                'type'       => 'text',
                'required'   => true, // Marcado como obrigatório
                'enabled'    => true,
                'conditions' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pj'],
                    ],
                ],
            ],
        ];

        // Submissão como Pessoa Física ('pf') -> CNPJ está oculto pela condição
        $posted_data = [
            'billing_persontype' => 'pf',
            'billing_cnpj'       => '', // Vazio
        ];

        $errors = new WP_Error();
        $validation_errors = FieldValidator::validate($fields, $posted_data, $errors);

        // CNPJ não deve ser cobrado nem gerar erro!
        $this->assertArrayNotHasKey('billing_cnpj', $validation_errors);
        $this->assertFalse($errors->has_errors());
    }

    public function test_requires_conditional_field_when_condition_is_met(): void {
        $fields = [
            [
                'key'      => 'billing_persontype',
                'label'    => 'Tipo de Pessoa',
                'type'     => 'select',
                'required' => true,
                'enabled'  => true,
            ],
            [
                'key'        => 'billing_cnpj',
                'label'      => 'CNPJ',
                'type'       => 'text',
                'required'   => true,
                'enabled'    => true,
                'conditions' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pj'],
                    ],
                ],
            ],
        ];

        // Submissão como Pessoa Jurídica ('pj') com CNPJ vazio -> deve falhar
        $posted_data = [
            'billing_persontype' => 'pj',
            'billing_cnpj'       => '',
        ];

        $errors = new WP_Error();
        $validation_errors = FieldValidator::validate($fields, $posted_data, $errors);

        $this->assertArrayHasKey('billing_cnpj', $validation_errors);
        $this->assertTrue($errors->has_errors());
    }

    public function test_duplicate_keys_emit_single_error_for_same_destination(): void {
        $fields = [
            [
                'key'      => 'billing_first_name',
                'label'    => 'Nome do aluno',
                'type'     => 'text',
                'required' => true,
                'enabled'  => true,
            ],
            [
                'key'      => 'billing_first_name',
                'label'    => 'Nome do comprador',
                'type'     => 'text',
                'required' => true,
                'enabled'  => true,
            ],
        ];

        $errors = new WP_Error();
        $validation_errors = FieldValidator::validate($fields, ['billing_first_name' => ''], $errors);

        $this->assertCount(1, $validation_errors);
        $this->assertArrayHasKey('billing_first_name', $validation_errors);
        $this->assertCount(1, $errors->get_error_messages('gvn_billing_first_name_required'));
    }

    public function test_validates_email_format_when_provided(): void {
        $fields = [
            [
                'key'      => 'billing_custom_email',
                'label'    => 'E-mail Secundário',
                'type'     => 'email',
                'required' => false,
                'enabled'  => true,
            ],
        ];

        $errors = new WP_Error();
        $validation_errors = FieldValidator::validate($fields, ['billing_custom_email' => 'invalido-sem-arroba'], $errors);

        $this->assertArrayHasKey('billing_custom_email', $validation_errors);
        $this->assertTrue($errors->has_errors());
        $this->assertStringContainsString('deve conter um e-mail válido', $validation_errors['billing_custom_email']);
    }
}
