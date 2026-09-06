<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Fields\FieldValidator;
use GVN\Checkout\Payments\GatewayRequirementsResolver;
use GVN_Custom_Fields;
use PHPUnit\Framework\TestCase;

class GatewayRequirementsResolverTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_filters, $wp_mock_options;
        $wp_mock_filters = [];
        $wp_mock_options = [];
        $_POST = [];
    }

    public function test_report_marks_woocommerce_field_that_is_not_required_in_gvn(): void {
        $resolver = new GatewayRequirementsResolver();
        $report   = $resolver->get_report(
            [
                'billing' => [
                    'billing_postcode' => [
                        'label'    => 'CEP',
                        'required' => true,
                    ],
                ],
            ],
            [
                [
                    'key'      => 'billing_postcode',
                    'label'    => 'CEP',
                    'enabled'  => true,
                    'required' => false,
                ],
            ],
            [
                'pix' => new \Mock_WC_Payment_Gateway('pix', 'Pix'),
            ]
        );

        $this->assertCount(1, $report['gateways']);
        $this->assertSame('not_declared', $report['gateways'][0]['declaration']);
        $this->assertSame('not_required', $report['gateways'][0]['requirements'][0]['status']);
        $this->assertSame('billing_postcode', $report['gateways'][0]['requirements'][0]['field_key']);
    }

    public function test_extension_requirement_is_scoped_and_can_be_conditioned(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id !== 'boleto') {
                return $requirements;
            }

            $requirements[] = [
                'field_key'  => 'billing_cpf',
                'variant'    => 'boleto',
                'requirement' => 'required',
                'source'     => 'adapter',
                'confidence' => 'confirmed',
                'when'       => [
                    'logic' => 'and',
                    'rules' => [
                        [
                            'field'    => 'payment_method',
                            'operator' => 'equals',
                            'value'    => 'boleto',
                        ],
                    ],
                ],
            ];

            return $requirements;
        }, 10, 4);

        $resolver = new GatewayRequirementsResolver();
        $active   = $resolver->get_active_requirements_for_gateway('boleto', ['payment_method' => 'boleto', 'payment_method_variant' => 'boleto']);

        $this->assertCount(1, $active);
        $this->assertSame('billing_cpf', $active[0]['field_key']);
        $this->assertTrue($resolver->is_field_required('billing_cpf', [], 'boleto', ['payment_method' => 'boleto', 'payment_method_variant' => 'boleto'], false));
        $this->assertFalse($resolver->is_field_required('billing_cpf', [], 'boleto', ['payment_method' => 'boleto', 'payment_method_variant' => 'pix'], false));
    }

    public function test_unknown_requirement_does_not_block_server_validation(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'pix') {
                $requirements[] = [
                    'field_key'  => 'billing_cpf',
                    'requirement' => 'required',
                    'confidence' => 'unknown',
                    'source'     => 'extension',
                ];
            }

            return $requirements;
        }, 10, 4);

        $errors = new \WP_Error();
        $result = FieldValidator::validate(
            [
                [
                    'key'      => 'billing_cpf',
                    'label'    => 'CPF',
                    'type'     => 'text',
                    'required' => false,
                    'enabled'  => true,
                ],
            ],
            [
                'payment_method' => 'pix',
                'billing_cpf'    => '',
            ],
            $errors
        );

        $this->assertArrayNotHasKey('billing_cpf', $result);
        $this->assertFalse($errors->has_errors());
    }

    public function test_confirmed_gateway_requirement_is_validated_server_side(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'boleto') {
                $requirements[] = [
                    'field_key'  => 'billing_cpf',
                    'requirement' => 'required',
                    'confidence' => 'confirmed',
                    'source'     => 'adapter',
                ];
            }

            return $requirements;
        }, 10, 4);

        $errors = new \WP_Error();
        $result = FieldValidator::validate(
            [
                [
                    'key'      => 'billing_cpf',
                    'label'    => 'CPF',
                    'type'     => 'text',
                    'required' => false,
                    'enabled'  => true,
                ],
            ],
            [
                'payment_method' => 'boleto',
                'billing_cpf'    => '',
            ],
            $errors
        );

        $this->assertArrayHasKey('billing_cpf', $result);
        $this->assertStringContainsString('obrigatório para o método', $result['billing_cpf']);
        $this->assertTrue($errors->has_errors());
    }

    public function test_confirmed_gateway_requirement_prevents_native_field_hiding(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'boleto') {
                $requirements[] = [
                    'field_key'  => 'billing_address_1',
                    'requirement' => 'required',
                    'confidence' => 'confirmed',
                    'source'     => 'adapter',
                ];
            }

            return $requirements;
        }, 10, 4);

        $_POST['payment_method'] = 'boleto';
        $this->assertTrue((new GatewayRequirementsResolver())->is_field_required('billing_address_1', [], 'boleto', ['payment_method' => 'boleto'], false));
        $checkout_fields = GVN_Custom_Fields::get_instance()->register_custom_fields_with_woo([
            'billing' => [
                'billing_address_1' => [
                    'class'    => [],
                    'required' => false,
                ],
            ],
        ]);

        $this->assertNotContains('gvn-hidden-field', $checkout_fields['billing']['billing_address_1']['class']);
    }

    public function test_confirmed_present_requirement_also_prevents_native_field_hiding(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'boleto') {
                $requirements[] = [
                    'field_key'   => 'billing_address_2',
                    'requirement' => 'present',
                    'confidence'  => 'confirmed',
                    'source'      => 'adapter',
                ];
            }

            return $requirements;
        }, 10, 4);

        $resolver = new GatewayRequirementsResolver();

        $this->assertTrue($resolver->is_field_required('billing_address_2', [], 'boleto', [], false));
    }

    public function test_invalid_extension_items_are_ignored_and_valid_items_are_deduplicated_by_precedence(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'boleto') {
                return [
                    [
                        'field_key'   => 'billing_cpf',
                        'requirement' => 'required',
                        'confidence'  => 'unknown',
                        'source'      => 'extension',
                    ],
                    [
                        'field_key'   => 'billing_cpf',
                        'requirement' => 'required',
                        'confidence'  => 'confirmed',
                        'source'      => 'adapter',
                    ],
                    [
                        'field'       => 'billing_phone',
                        'requirement' => 'required',
                        'confidence'  => 'confirmed',
                        'source'      => 'adapter',
                    ],
                    [
                        'field_key'   => 'billing_email',
                        'requirement' => 'invalid',
                        'confidence'  => 'confirmed',
                        'source'      => 'adapter',
                    ],
                ];
            }

            return $requirements;
        }, 10, 4);

        $resolver = new GatewayRequirementsResolver();
        $active = $resolver->get_active_requirements_for_gateway('boleto', []);

        $this->assertCount(1, $active);
        $this->assertSame('billing_cpf', $active[0]['field_key']);
        $this->assertSame('adapter', $active[0]['source']);
        $this->assertSame('confirmed', $active[0]['confidence']);
    }

    public function test_confirmed_requirement_is_validated_even_when_editor_condition_hides_field(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'boleto') {
                $requirements[] = [
                    'field_key'   => 'billing_cpf',
                    'requirement' => 'required',
                    'confidence'  => 'confirmed',
                    'source'      => 'adapter',
                ];
            }

            return $requirements;
        }, 10, 4);

        $result = FieldValidator::validate(
            [
                [
                    'key'        => 'billing_cpf',
                    'label'      => 'CPF',
                    'required'   => false,
                    'enabled'    => true,
                    'conditions' => [
                        'logic' => 'and',
                        'rules' => [
                            [
                                'field'    => 'payment_method',
                                'operator' => 'equals',
                                'value'    => 'pix',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'payment_method' => 'boleto',
                'billing_cpf'    => '',
            ]
        );

        $this->assertArrayHasKey('billing_cpf', $result);
    }

    public function test_variant_requirement_is_scoped_to_the_selected_gateway_variant(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'mercado') {
                $requirements[] = [
                    'field_key'   => 'billing_cpf',
                    'variant'     => 'boleto',
                    'requirement' => 'required',
                    'confidence'  => 'confirmed',
                    'source'      => 'adapter',
                ];
                $requirements[] = [
                    'field_key'   => 'billing_email',
                    'variant'     => 'pix',
                    'requirement' => 'required',
                    'confidence'  => 'confirmed',
                    'source'      => 'adapter',
                ];
            }

            return $requirements;
        }, 10, 4);

        $resolver = new GatewayRequirementsResolver();
        $boleto = $resolver->get_active_requirements_for_gateway(
            'mercado',
            [
                'payment_method'         => 'mercado',
                'payment_method_variant' => 'boleto',
            ],
            [],
            false
        );
        $pix = $resolver->get_active_requirements_for_gateway(
            'mercado',
            [
                'payment_method'         => 'mercado',
                'payment_method_variant' => 'pix',
            ],
            [],
            false
        );
        $without_variant = $resolver->get_active_requirements_for_gateway(
            'mercado',
            ['payment_method' => 'mercado'],
            [],
            false
        );

        $this->assertSame(['billing_cpf'], array_column($boleto, 'field_key'));
        $this->assertSame(['billing_email'], array_column($pix, 'field_key'));
        $this->assertSame([], $without_variant);
    }

    public function test_wrapped_extension_payload_is_not_accepted_by_strict_contract(): void {
        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'pix') {
                return [
                    'requirements' => [
                        [
                            'field_key'   => 'billing_cpf',
                            'requirement' => 'required',
                            'confidence'  => 'confirmed',
                            'source'      => 'extension',
                        ],
                    ],
                ];
            }

            return $requirements;
        }, 10, 4);

        $resolver = new GatewayRequirementsResolver();

        $this->assertSame([], $resolver->get_active_requirements_for_gateway('pix', [], [], false));
    }

    public function test_report_marks_a_hidden_checkout_field_as_conflict(): void {
        $resolver = new GatewayRequirementsResolver();

        add_filter(GatewayRequirementsResolver::FILTER, function ($requirements, $gateway_id) {
            if ($gateway_id === 'pix') {
                $requirements[] = [
                    'field_key'   => 'billing_cpf',
                    'requirement' => 'required',
                    'confidence'  => 'confirmed',
                    'source'      => 'adapter',
                ];
            }

            return $requirements;
        }, 10, 4);

        $report = $resolver->get_report(
            [
                'billing' => [
                    'billing_cpf' => [
                        'class'    => ['gvn-hidden-field'],
                        'required' => false,
                        'label'    => 'CPF',
                    ],
                ],
            ],
            [],
            [
                'pix' => new \Mock_WC_Payment_Gateway('pix', 'Pix'),
            ]
        );

        $this->assertSame('hidden', $report['gateways'][0]['requirements'][0]['status']);
    }

    public function test_report_exposes_plugin_identity_for_grouping_methods_in_admin(): void {
        $resolver = new GatewayRequirementsResolver();
        $report = $resolver->get_report(
            [],
            [],
            [
                'acme_card' => [
                    'title'        => 'Cartão',
                    'method_title' => 'Acme Payments',
                    'plugin_id'    => 'acme_payments',
                ],
                'acme_pix' => [
                    'title'        => 'Pix',
                    'method_title' => 'Acme Payments',
                    'plugin_id'    => 'acme_payments',
                ],
            ]
        );

        $this->assertSame('acme_payments', $report['gateways'][0]['plugin_id']);
        $this->assertSame('Acme Payments', $report['gateways'][0]['plugin_title']);
        $this->assertSame('acme_payments', $report['gateways'][1]['plugin_id']);
    }
}
