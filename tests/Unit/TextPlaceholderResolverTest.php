<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Checkout\TextPlaceholderResolver;
use Mock_WC_Order_Complete;
use Mock_WC_Product;
use PHPUnit\Framework\TestCase;

class TextPlaceholderResolverTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $mock_woocommerce_instance;
        $mock_woocommerce_instance = null;
    }

    public function test_resolves_cart_placeholders_with_native_cart_values(): void {
        $cart = WC()->cart;
        $cart->items = [
            'course' => [
                'data'     => new Mock_WC_Product(10, 'Curso Conversão', '120.00'),
                'quantity' => 2,
            ],
            'ebook' => [
                'data'     => new Mock_WC_Product(11, 'E-book Bônus', '30.00'),
                'quantity' => 1,
            ],
        ];

        $resolved = TextPlaceholderResolver::resolve(
            '{produto} | {qtd-produto} itens | {subtotal} | {total} | {nome-loja}',
            $cart
        );

        $this->assertSame(
            'Curso Conversão, E-book Bônus | 3 itens | R$ 270,00 | R$ 270,00 | 6.7',
            $resolved
        );
    }

    public function test_resolves_order_placeholders_for_thankyou_header(): void {
        $order = new Mock_WC_Order_Complete(123, 'key_123');

        $resolved = TextPlaceholderResolver::resolve(
            'Olá {primeiro-nome}, pedido #{numero-pedido}: {qtd-produto}x {produto} por {total} via {metodo-pagamento}',
            $order
        );

        $this->assertSame('Olá João, pedido #123: 1x Curso Principal por R$ 150,00 via Pix', $resolved);
    }

    public function test_preserves_unknown_placeholders_and_sanitizes_product_names(): void {
        $cart = WC()->cart;
        $cart->items = [
            'unsafe' => [
                'data'     => new Mock_WC_Product(12, '<b>Produto Seguro</b>', '10.00'),
                'quantity' => 1,
            ],
        ];

        $resolved = TextPlaceholderResolver::resolve('{produto} {atalho-futuro}', $cart);

        $this->assertSame('Produto Seguro {atalho-futuro}', $resolved);
    }

    public function test_exposes_the_documented_shortcuts_to_the_admin(): void {
        $placeholders = TextPlaceholderResolver::get_available_placeholders();

        $this->assertSame(
            ['{produto}', '{qtd-produto}', '{subtotal}', '{total}', '{nome-loja}'],
            array_keys($placeholders)
        );

        $this->assertSame(
            [
                '{produto}',
                '{qtd-produto}',
                '{subtotal}',
                '{total}',
                '{nome-loja}',
                '{primeiro-nome}',
                '{numero-pedido}',
                '{metodo-pagamento}',
            ],
            array_keys(TextPlaceholderResolver::get_thankyou_placeholders())
        );
    }
}
