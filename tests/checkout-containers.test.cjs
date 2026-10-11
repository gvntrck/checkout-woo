const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const css = fs.readFileSync(path.join(__dirname, '../assets/css/gvn-checkout.css'), 'utf8');
const legacyCss = css.replaceAll('body:where(:not(.gvn-checkout-isolated))', 'body')
    .replaceAll(':where(body:not(.gvn-checkout-isolated)) ', '');
const checkout = '<div id="gvn-checkout" class="gvn-checkout"><form name="checkout"><div class="gvn-grid"><div class="gvn-col-main-details">Dados</div><div class="gvn-col-sidebar">Resumo</div></div></form></div>';
const header = '<header data-protected class="site-header"><div class="ast-container col-full">Menu</div></header>';
const footer = '<footer data-protected><div class="ast-container col-full">Rodapé</div></footer>';
const banner = '<section data-protected class="banner">Título da página</section>';
const other = '<article data-protected class="other hentry page"><div class="entry-content">Outro conteúdo</div></article>';
const themeCss = `
    * { box-sizing: border-box; }
    body { margin: 0; }
    article { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
    .entry-content { max-width: 720px; margin: 0 auto; }
    .ast-container, .col-full { max-width: 960px; margin: 0 auto; padding: 0 24px; }
    header, footer, .banner { background: navy; color: white; }
    .e-con { padding: 0 10px; }
    .e-con-inner { max-width: min(100%, 1140px); margin: 0 auto; }
    .is-layout-constrained > :where(:not(.alignfull)) { max-width: 680px; margin-left: auto; margin-right: auto; }
`;
const fixtures = [
    { name: 'shortcode', container: 'article', html: `${header}<main id="content">${banner}<article data-container>${checkout}</article></main>${other}${footer}` },
    { name: 'Elementor', container: 'article', html: `${header}<main id="content">${banner}<article data-container><div class="elementor"><div class="e-con"><div class="e-con-inner"><div class="elementor-widget-container"><div class="elementor-shortcode">${checkout}</div></div></div></div></div></article></main>${other}${footer}` },
    { name: 'Astra', container: 'content', html: `${header}<div id="content" class="site-content"><div class="ast-container"><main class="site-main"><article class="page hentry"><div data-container class="entry-content">${checkout}</div></article></main></div></div>${other}${footer}` },
    { name: 'Storefront', container: 'content', html: `${header}<div id="content" class="site-content"><div class="col-full"><main class="site-main"><article class="page hentry"><div data-container class="entry-content">${checkout}</div></article></main></div></div>${other}${footer}` },
    { name: 'FSE', container: 'content', html: `<div class="wp-site-blocks is-layout-constrained">${header}${banner}<main><div data-container class="wp-block-post-content is-layout-constrained">${checkout}<p data-protected>Conteúdo vizinho</p></div></main>${footer}</div>${other}` },
];

for (const fixture of fixtures) {
    test(`largura e isolamento: ${fixture.name}`, { skip: !process.env.GVN_PLAYWRIGHT_PATH && 'Defina GVN_PLAYWRIGHT_PATH para usar Playwright já instalado.' }, async () => {
        const { chromium } = require(process.env.GVN_PLAYWRIGHT_PATH);
        const browser = await chromium.launch({ headless: true, executablePath: process.env.GVN_CHROMIUM_PATH });
        try {
            const page = await browser.newPage();
            async function render(styles, classes, html = fixture.html) {
                await page.setContent(`<style>${themeCss}</style><style>${styles}</style><body class="${classes}">${html}</body>`);
            }
            async function snapshot(selector) {
                return page.locator(selector).evaluateAll(nodes => nodes.map(node => {
                    const rect = node.getBoundingClientRect();
                    const style = getComputedStyle(node);
                    return { width: rect.width, left: rect.left, maxWidth: style.maxWidth, padding: style.padding, marginLeft: style.marginLeft, marginRight: style.marginRight };
                }));
            }
            for (const width of [320, 390, 768, 1600]) {
                await page.setViewportSize({ width, height: 1000 });
                const classes = 'gvn-checkout-active woocommerce-checkout';
                await render(legacyCss, classes);
                const legacy = await snapshot('header, footer, main, article, .entry-content, .ast-container, .col-full, .wp-site-blocks, .is-layout-constrained > *, #gvn-checkout');
                await render(css, classes);
                assert.deepEqual(await snapshot('header, footer, main, article, .entry-content, .ast-container, .col-full, .wp-site-blocks, .is-layout-constrained > *, #gvn-checkout'), legacy, `legado ${width}`);

                await render('', '');
                const protectedContent = await snapshot('[data-protected], [data-protected] .ast-container, [data-protected] .col-full, .other .entry-content');
                const nativeContainer = await snapshot('[data-container]');
                for (const expand of ['', ` gvn-checkout-expand-${fixture.container}`]) {
                    await render(css, `${classes} gvn-checkout-isolated${expand}`);
                    assert.deepEqual(await snapshot('[data-protected], [data-protected] .ast-container, [data-protected] .col-full, .other .entry-content'), protectedContent, `conteúdo externo ${width}${expand}`);
                    if (!expand) assert.deepEqual(await snapshot('[data-container]'), nativeContainer, `contêiner nativo ${width}`);
                    else assert.equal((await snapshot('[data-container]'))[0].maxWidth, 'none');
                    const result = await page.locator('#gvn-checkout').evaluate(node => {
                        const rect = node.getBoundingClientRect();
                        const parent = node.parentElement.getBoundingClientRect();
                        const style = getComputedStyle(node);
                        return { width: rect.width, center: rect.x + rect.width / 2, parentCenter: parent.x + parent.width / 2, maxWidth: style.maxWidth, boxSizing: style.boxSizing, scrollWidth: document.documentElement.scrollWidth, viewport: innerWidth };
                    });
                    assert.equal(result.maxWidth, '1240px');
                    assert.equal(result.boxSizing, 'border-box');
                    assert.ok(result.width <= 1240, `limite ${width}`);
                    assert.ok(Math.abs(result.center - result.parentCenter) < 1, `centralização ${width}`);
                    assert.ok(result.scrollWidth <= result.viewport, `overflow ${width}`);
                    assert.equal(await page.locator('form[name=checkout]').count(), 1);
                }
                const noCheckout = fixture.html.replace(checkout, '<p>Sem checkout</p>');
                await render('', '', noCheckout);
                const native = await snapshot('[data-protected], [data-container]');
                await render(css, `gvn-checkout-isolated gvn-checkout-expand-${fixture.container}`, noCheckout);
                assert.deepEqual(await snapshot('[data-protected], [data-container]'), native, `sem checkout ${width}`);
            }
        } finally {
            await browser.close();
        }
    });
}
