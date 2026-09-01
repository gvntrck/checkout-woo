<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Fields\FieldSanitizer;
use PHPUnit\Framework\TestCase;

class FieldSanitizerTest extends TestCase {

    public function test_sanitizes_text_field_stripping_scripts_and_tags(): void {
        $input = '<script>alert("xss")</script><b>João Silva</b>';
        $sanitized = FieldSanitizer::sanitize('text', $input);
        $this->assertEquals('João Silva', $sanitized);
    }

    public function test_sanitizes_email(): void {
        $input = '  contato+teste@loja.com.br  ';
        $sanitized = FieldSanitizer::sanitize('email', $input);
        $this->assertEquals('contato+teste@loja.com.br', $sanitized);
    }

    public function test_sanitizes_textarea_preserving_content(): void {
        $input = "Observação linha 1\nLinha 2<script>evil()</script>";
        $sanitized = FieldSanitizer::sanitize('textarea', $input);
        $this->assertStringContainsString("Observação linha 1\nLinha 2", $sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    public function test_sanitizes_select_with_allowed_options(): void {
        $field = [
            'key'     => 'billing_persontype',
            'type'    => 'select',
            'options' => "pf|Pessoa Física\npj|Pessoa Jurídica",
        ];

        // Opção válida
        $this->assertEquals('pf', FieldSanitizer::sanitize('select', 'pf', $field));
        $this->assertEquals('pj', FieldSanitizer::sanitize('select', 'pj', $field));

        // Opção inválida/injetada é rejeitada
        $this->assertEquals('', FieldSanitizer::sanitize('select', 'admin', $field));
        $this->assertEquals('', FieldSanitizer::sanitize('select', 'hack<script>', $field));
    }

    public function test_parses_select_options_with_pipe_and_colon(): void {
        $pipe_options = "1|Opção Um\n2|Opção Dois";
        $parsed_pipe = FieldSanitizer::parse_select_options($pipe_options);
        $this->assertEquals(['1' => 'Opção Um', '2' => 'Opção Dois'], $parsed_pipe);

        $colon_options = "pf : Pessoa Física\npj : Pessoa Jurídica";
        $parsed_colon = FieldSanitizer::parse_select_options($colon_options);
        $this->assertEquals(['pf' => 'Pessoa Física', 'pj' => 'Pessoa Jurídica'], $parsed_colon);
    }

    public function test_sanitizes_number(): void {
        $this->assertEquals('123.45', FieldSanitizer::sanitize('number', 'abc123.45xyz'));
        $this->assertEquals('42', FieldSanitizer::sanitize('number', 42));
    }
}
