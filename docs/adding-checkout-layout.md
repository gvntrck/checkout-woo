# Adicionando um novo layout de checkout

Este plugin suporta múltiplos modelos visuais de checkout selecionáveis
(Configurações > GVN Checkout > Layout do Checkout, ou por página com
`[gvn-checkout layout="{slug}"]`). Nativos hoje: `classic` e `split`.

## Passo a passo (3 passos)

### 1. Criar o template

Copie `templates/checkout/layout-split.php` para
`templates/checkout/layout-{slug}.php` e ajuste o visual. O raiz deve ser:

```php
<div class="gvn-checkout gvn-layout-{slug}" id="gvn-checkout" data-layout="{slug}">
```

> Dívida consciente: cada layout hoje duplica os blocos de campos, gateways,
> resumo e order bump (para não arriscar o clássico). A partir do 3º layout,
> extraia partials em `templates/checkout/partials/` (`fields.php`,
> `payment.php`, `summary.php`, `coupon.php`, `order-bump.php`).

### 2. Registrar o layout

Opção A — nativo, em `src/Layouts/LayoutRegistry.php::get_builtin_layouts()`:

```php
'{slug}' => array(
    'label'    => __( 'Meu Modelo', 'gvn-checkout' ),
    'template' => $base . 'templates/checkout/layout-{slug}.php',
    'css'      => 'assets/css/gvn-checkout-layout-{slug}.css',
    'js'       => null, // ou 'assets/js/gvn-checkout-layout-{slug}.js'
),
```

Opção B — sem mexer no core (tema/outro plugin), via filtro:

```php
add_filter( 'gvn_checkout_layouts', function ( $layouts ) {
    $layouts['{slug}'] = array(
        'label'    => 'Meu Modelo',
        'template' => get_stylesheet_directory() . '/gvn-checkout/layout-{slug}.php',
        'css'      => null,
        'js'       => null,
    );
    return $layouts;
} );
```

Nada mais é preciso no admin: o select lista automaticamente, a sanitização
só aceita slugs registrados e slug inválido cai para `classic`.

### 3. Validar

- `composer dump-autoload` (WSL) e `php vendor/bin/phpunit --testsuite unit`
- Estenda `tests/Unit/LayoutRegistryTest.php` se houver lógica nova de resolução.
- Checklist manual: cupom on/off, order bump on/off, Pix/cartão/boleto,
  CEP + máscaras, campos condicionais, mobile, carrinho vazio, guest checkout.

## Contrato obrigatório do template (não quebrar)

- `form[name=checkout].woocommerce-checkout` com `action` de `wc_get_checkout_url()`
  e nonce `woocommerce-process-checkout-nonce`.
- Os 15 hooks canônicos **na mesma ordem** do clássico (ver sequência em
  `templates/checkout-template.php`; há teste que trava a ordem do clássico).
- `input[type=radio][name=payment_method]` por gateway + `$gateway->payment_fields()`
  dentro de `#gvn-gateway-content` (blocos `#gvn-gateway-fields-{id}`).
- IDs usados pelo JS e pelos fragments: `#gvn-order-items`, `#gvn-order-totals`,
  `#gvn-subtotal`, `#gvn-total` (`#gvn-discount`, `#gvn-discount-row` quando houver
  desconto), `#payment`, `#place_order`, `#gvn-coupon-toggle`,
  `#gvn-coupon-form`, `#gvn-coupon-code`, `#gvn-apply-coupon`,
  `#gvn-coupon-message`, `#gvn-bump-checkbox`, `#customer_details`.
- Botão via filtros `woocommerce_order_button_text` / `woocommerce_order_button_html`.
- Textos via `SettingsRepository::get()` + `TextPlaceholderResolver::resolve()`.

## Como a resolução funciona

Prioridade: atributo `layout` do shortcode > atributo no conteúdo da página >
configuração `checkout_layout` > `classic`. Filtros:

- `gvn_checkout_layouts` — registra/remove layouts (`slug => [label, template, css, js]`).
- `gvn_checkout_layout` — override final do slug (`$layout`, `$context`: render/enqueue/body).
- `gvn_checkout_template_path` — override do caminho (`$template`, `$slug`).

Override pelo tema: `gvn-checkout/layout-{slug}.php` tem precedência sobre o plugin.
Assets: CSS/JS do layout enfileiram como `gvn-checkout-layout-{slug}` (dependendo
da base); body recebe `gvn-layout-{slug}`. CSS do layout deve ser 100% escopado
sob `.gvn-layout-{slug}` e autocontido (sem CDN).
