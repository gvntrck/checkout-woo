<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Fields\FieldOrderPersister;
use PHPUnit\Framework\TestCase;

class MockWcOrder {
    public $meta = [];

    public function update_meta_data($key, $value) {
        $this->meta[$key] = $value;
    }

    public function delete_meta_data($key) {
        unset($this->meta[$key]);
    }

    public function get_meta($key) {
        return $this->meta[$key] ?? '';
    }
}

class FieldOrderPersisterTest extends TestCase {

    public function test_checkbox_records_checked_and_unchecked_values(): void {
        $order = new MockWcOrder();
        $fields = [['key' => 'accept_terms', 'type' => 'checkbox', 'enabled' => true]];

        FieldOrderPersister::persist($order, $fields, ['accept_terms' => '1']);
        $this->assertSame('1', $order->get_meta('_accept_terms'));

        FieldOrderPersister::persist($order, $fields, []);
        $this->assertSame('0', $order->get_meta('_accept_terms'));
    }

    public function test_persists_visible_custom_fields_with_underscore_prefix(): void {
        $order = new MockWcOrder();

        $fields = [
            [
                'key'      => 'billing_cpf',
                'type'     => 'text',
                'enabled'  => true,
            ],
            [
                'key'      => 'billing_number',
                'type'     => 'text',
                'enabled'  => true,
            ],
            [
                'key'      => 'billing_neighborhood',
                'type'     => 'text',
                'enabled'  => true,
            ],
        ];

        $posted_data = [
            'billing_cpf'          => '123.456.789-00',
            'billing_number'       => '100',
            'billing_neighborhood' => 'Centro',
        ];

        $persisted = FieldOrderPersister::persist($order, $fields, $posted_data);

        $this->assertEquals('123.456.789-00', $order->get_meta('_billing_cpf'));
        $this->assertEquals('100', $order->get_meta('_billing_number'));
        $this->assertEquals('Centro', $order->get_meta('_billing_neighborhood'));
        $this->assertCount(3, $persisted);
    }

    public function test_does_not_persist_hidden_conditional_fields_and_deletes_stale_meta(): void {
        $order = new MockWcOrder();
        // Simula valor pré-existente ou residual
        $order->update_meta_data('_billing_cnpj', '00.000.000/0001-00');

        $fields = [
            [
                'key'      => 'billing_persontype',
                'type'     => 'select',
                'enabled'  => true,
            ],
            [
                'key'        => 'billing_cnpj',
                'type'       => 'text',
                'enabled'    => true,
                'conditions' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pj'],
                    ],
                ],
            ],
        ];

        // Cliente submeteu como Pessoa Física ('pf') e tentou forçar valor no CNPJ
        $posted_data = [
            'billing_persontype' => 'pf',
            'billing_cnpj'       => '11.222.333/0001-99',
        ];

        FieldOrderPersister::persist($order, $fields, $posted_data);

        // O metadado deve ter sido removido e o valor injetado não deve ter sido salvo
        $this->assertEquals('', $order->get_meta('_billing_cnpj'));
    }

    public function test_blocks_mass_assignment_of_reserved_order_properties(): void {
        $order = new MockWcOrder();
        $order->update_meta_data('_order_total', '50.00');

        $fields = [
            [
                'key'      => 'billing_cpf',
                'type'     => 'text',
                'enabled'  => true,
            ],
        ];

        // Tentativa de ataque enviando _order_total, total, status, etc.
        $posted_data = [
            'billing_cpf'     => '123.456.789-00',
            '_order_total'    => '0.01',
            'total'           => '0.01',
            '_payment_method' => 'fake_free',
            '_status'         => 'completed',
        ];

        FieldOrderPersister::persist($order, $fields, $posted_data);

        // _order_total permaneceu intocado
        $this->assertEquals('50.00', $order->get_meta('_order_total'));
        $this->assertEquals('', $order->get_meta('_total'));
        $this->assertEquals('', $order->get_meta('_status'));
    }

    public function test_duplicate_keys_visible_occurrence_wins_regardless_of_order(): void {
        $order = new MockWcOrder();

        $fields = [
            [
                'key'        => 'gvn_nome',
                'label'      => 'Nome do aluno',
                'type'       => 'text',
                'enabled'    => true,
                'conditions' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'gvn_para_quem', 'operator' => 'equals', 'value' => 'mim'],
                    ],
                ],
            ],
            [
                'key'        => 'gvn_nome',
                'label'      => 'Nome do comprador',
                'type'       => 'text',
                'enabled'    => true,
                'conditions' => [
                    'logic' => 'and',
                    'rules' => [
                        ['field' => 'gvn_para_quem', 'operator' => 'equals', 'value' => 'outra'],
                    ],
                ],
            ],
        ];

        // A ocorrência oculta vem DEPOIS da visível: a exclusão dela não pode
        // apagar o valor persistido pela ocorrência visível.
        $posted_data = [
            'gvn_para_quem' => 'mim',
            'gvn_nome'      => 'Ana Aluna',
        ];

        $persisted = FieldOrderPersister::persist($order, $fields, $posted_data);

        $this->assertEquals('Ana Aluna', $order->get_meta('_gvn_nome'));
        $this->assertArrayHasKey('_gvn_nome', $persisted);
    }

    public function test_does_not_duplicate_native_woo_fields_as_custom_meta(): void {
        $order = new MockWcOrder();

        $fields = [
            [
                'key'      => 'billing_first_name',
                'type'     => 'text',
                'enabled'  => true,
            ],
            [
                'key'      => 'order_comments',
                'type'     => 'textarea',
                'enabled'  => true,
            ],
        ];

        $posted_data = [
            'billing_first_name' => 'Maria',
            'order_comments'     => 'Deixar na portaria',
        ];

        $persisted = FieldOrderPersister::persist($order, $fields, $posted_data);

        // O persister ignora campos nativos pois o próprio WC Core já os persiste
        $this->assertEmpty($persisted);
        $this->assertEquals('', $order->get_meta('_billing_first_name'));
        $this->assertEquals('', $order->get_meta('_order_comments'));
    }
}
