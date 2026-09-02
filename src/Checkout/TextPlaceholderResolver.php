<?php

namespace GVN\Checkout\Checkout;

/**
 * Resolve atalhos de texto configuráveis usando dados nativos do carrinho ou pedido.
 */
final class TextPlaceholderResolver {

    /**
     * Retorna os atalhos disponíveis para apresentação no painel.
     *
     * @return array<string, string>
     */
    public static function get_available_placeholders(): array {
        return [
            '{produto}'     => __('Nome do produto ou produtos do carrinho', 'gvn-checkout'),
            '{qtd-produto}' => __('Quantidade total de produtos', 'gvn-checkout'),
            '{subtotal}'    => __('Subtotal atual do carrinho', 'gvn-checkout'),
            '{total}'       => __('Total atual do carrinho', 'gvn-checkout'),
            '{nome-loja}'   => __('Nome da loja', 'gvn-checkout'),
        ];
    }

    /**
     * Substitui somente os atalhos conhecidos e preserva qualquer texto desconhecido.
     *
     * @param string      $text    Texto configurado pelo administrador.
     * @param object|null $context WC_Cart, WC_Order ou null para usar o carrinho atual.
     */
    public static function resolve(string $text, $context = null): string {
        if ($text === '' || strpos($text, '{') === false) {
            return $text;
        }

        if ($context === null && function_exists('WC') && WC()) {
            $context = WC()->cart;
        }

        $values = self::get_context_values($context);
        $values['{nome-loja}'] = function_exists('get_bloginfo')
            ? self::plain_text((string) get_bloginfo('name'))
            : '';

        return strtr($text, $values);
    }

    /**
     * @param object|null $context
     * @return array<string, string>
     */
    private static function get_context_values($context): array {
        $values = [
            '{produto}'     => '',
            '{qtd-produto}' => '0',
            '{subtotal}'    => '',
            '{total}'       => '',
        ];

        if (!is_object($context) || (!method_exists($context, 'get_items') && !method_exists($context, 'get_cart'))) {
            return $values;
        }

        if (method_exists($context, 'get_items')) {
            return self::get_order_values($context, $values);
        }

        return self::get_cart_values($context, $values);
    }

    /**
     * @param object                $cart
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private static function get_cart_values($cart, array $values): array {
        $names    = [];
        $quantity = 0;

        foreach ((array) $cart->get_cart() as $item) {
            $product = isset($item['data']) && is_object($item['data']) ? $item['data'] : null;
            if ($product && method_exists($product, 'get_name')) {
                $name = self::plain_text((string) $product->get_name());
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $quantity += max(0, (int) ($item['quantity'] ?? 0));
        }

        $values['{produto}']     = implode(', ', array_values(array_unique($names)));
        $values['{qtd-produto}'] = (string) $quantity;

        if (method_exists($cart, 'get_cart_subtotal')) {
            $values['{subtotal}'] = self::plain_text((string) $cart->get_cart_subtotal());
        } elseif (method_exists($cart, 'get_subtotal')) {
            $values['{subtotal}'] = self::format_price($cart->get_subtotal());
        }

        if (method_exists($cart, 'get_total')) {
            $values['{total}'] = self::plain_text((string) $cart->get_total());
        }

        return $values;
    }

    /**
     * @param object                $order
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private static function get_order_values($order, array $values): array {
        $names    = [];
        $quantity = 0;

        foreach ((array) $order->get_items() as $item) {
            if (is_object($item) && method_exists($item, 'get_name')) {
                $name = self::plain_text((string) $item->get_name());
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            if (is_object($item) && method_exists($item, 'get_quantity')) {
                $quantity += max(0, (int) $item->get_quantity());
            }
        }

        $values['{produto}']     = implode(', ', array_values(array_unique($names)));
        $values['{qtd-produto}'] = (string) $quantity;

        if (method_exists($order, 'get_subtotal')) {
            $values['{subtotal}'] = self::format_price($order->get_subtotal());
        }
        if (method_exists($order, 'get_formatted_order_total')) {
            $values['{total}'] = self::plain_text((string) $order->get_formatted_order_total());
        }

        return $values;
    }

    /**
     * @param mixed $price
     */
    private static function format_price($price): string {
        $formatted = function_exists('wc_price') ? wc_price($price) : (string) $price;
        return self::plain_text((string) $formatted);
    }

    private static function plain_text(string $value): string {
        $value = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($value) : strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        return function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim($value);
    }
}
