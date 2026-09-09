# AGENTS.md — GVN Checkout for WooCommerce

## Projeto
Plugin de checkout customizado (shortcode `[gvn-checkout]`), PHP 7.4+, WooCommerce 7+.
Layout clássico canônico em `templates/checkout-template.php` — não mudar visual/comportamento dele sem motivo forte (há testes travando hooks e output).

## Layouts de checkout (seletor)
- Registro central: `src/Layouts/LayoutRegistry.php` (slugs `classic`, `split`).
- Novo layout = copiar `templates/checkout/layout-split.php` para `templates/checkout/layout-{slug}.php` + registrar (nativo ou via filtro `gvn_checkout_layouts`) + CSS/JS em `assets/`. Guia completo em `docs/adding-checkout-layout.md`.
- Contrato inegociável do template: `form[name=checkout]`, nonce, 15 hooks na ordem do clássico, `payment_method` + `payment_fields()`, IDs `#gvn-order-items`, `#gvn-order-totals`, `#payment`, `#place_order`, `#gvn-coupon-*`, `#gvn-bump-checkbox`, `#customer_details`.
- CSS do layout sempre escopado sob `.gvn-layout-{slug}`, sem CDN. Dívida consciente: blocos duplicados entre layouts; extrair `templates/checkout/partials/` ao criar o 3º.

## QA (via WSL — shell padrão é PowerShell sem PHP)
- `wsl bash -c 'cd <wsl-path> && php vendor/bin/phpunit --testsuite unit'`
- `php vendor/bin/phpstan analyse`, `bash scripts/syntax-check.sh`
- Após criar/mover classes PHP: `~/.local/bin/composer dump-autoload` antes do phpunit.
- phpcs está vermelho no repo inteiro (pré-existente); seguir o estilo local (4 espaços), não reformatar.
- Build de release no Windows: `powershell -ExecutionPolicy Bypass -File scripts/build-zip.ps1` (equivale ao `build-zip.sh`).

## Docs do repo
- `docs/adding-checkout-layout.md` — como criar layouts.
- `docs/adr/` — decisões de arquitetura; `docs/plans/` — planos.
