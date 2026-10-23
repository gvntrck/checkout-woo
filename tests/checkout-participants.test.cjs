const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const test = require('node:test');

const root = path.join(__dirname, '..');
const fixture = (layout, count = 2, sectionSize) => execFileSync(process.platform === 'win32' ? 'wsl' : 'php',
    [...(process.platform === 'win32' ? ['php'] : []), 'tests/fixtures/participants.php', layout, String(count), ...(sectionSize ? [String(sectionSize)] : [])],
    { cwd: root, encoding: 'utf8' });

test('participants: buyer toggle, AJAX drafts and responsive fields in both layouts', {
    skip: !process.env.GVN_PLAYWRIGHT_PATH && 'Defina GVN_PLAYWRIGHT_PATH para usar Playwright já instalado.'
}, async () => {
    const { chromium } = require(process.env.GVN_PLAYWRIGHT_PATH);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.GVN_CHROMIUM_PATH });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const styles = ['gvn-checkout.css', 'gvn-checkout-layout-split.css', 'gvn-participants.css']
            .map(file => fs.readFileSync(path.join(root, 'assets/css', file), 'utf8')).join('\n');
        for (const layout of ['classic', 'split']) {
          for (const quantity of [1, 2]) {
            await page.setContent(`<style>${styles}</style>${fixture(layout, quantity)}`);
            await page.addScriptTag({ path: path.join(root, 'vendor/phpunit/php-code-coverage/src/Report/Html/Renderer/Template/js/jquery.min.js') });
            await page.addScriptTag({ path: path.join(root, 'assets/js/gvn-participants.js') });
            await page.waitForFunction(() => window.jQuery && jQuery.isReady);
            assert.equal(await page.locator('.gvn-participants__person').count(), quantity);
            assert.equal(await page.locator('label[for="billing_first_name"]').innerText(), 'Nome do comprador *');
            assert.equal(await page.locator('label[for="billing_email"]').innerText(), 'E-mail do comprador *');
            assert.equal(await page.locator('#gvn-participants input[type="checkbox"]').count(), 0);
            const buyer = page.locator('#gvn-participants-intent input[value="1"]');
            const others = page.locator('#gvn-participants-intent input[value="0"]');
            assert.equal(await buyer.locator('..').innerText(), quantity === 1 ? ' Eu mesmo' : ' Eu e outras pessoas');
            assert.equal(await page.locator('#gvn-participants-intent').evaluate(intent =>
                !!(intent.compareDocumentPosition(document.querySelector('[name="billing_first_name"]')) & Node.DOCUMENT_POSITION_FOLLOWING)), true);
            for (const width of [320, 390, 768, 1280]) {
                await page.setViewportSize({ width, height: 1000 });
                assert.equal(await page.locator('#gvn-participants').evaluate(section => {
                    const box = section.getBoundingClientRect();
                    return box.left >= 0 && box.right <= innerWidth + 1 && [...section.querySelectorAll('.input-text')].every(input => {
                        const field = input.getBoundingClientRect();
                        return field.left >= box.left - 1 && field.right <= box.right + 1;
                    });
                }), true, `${layout} at ${width}px`);
            }
            const first = page.locator('.gvn-participants__person').first();
            const secondName = page.locator('.gvn-participants__person').nth(1).locator('[data-participant-field="name"]');
            await first.locator('[data-participant-field="name"]').fill('Rascunho do aluno');
            if (quantity > 1) await secondName.fill('Maria Silva');
            await buyer.check();
            assert.equal(await first.locator('.gvn-participants__fields').isVisible(), false);
            assert.equal(await first.locator('input').first().isDisabled(), true);
            // Simulate WooCommerce replacing an AJAX fragment with an empty server draft.
            const refreshed = fixture(layout, 3);
            await page.evaluate(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                document.querySelector('#gvn-participants').replaceWith(doc.querySelector('#gvn-participants'));
                document.querySelector('#gvn-participants-intent').replaceWith(doc.querySelector('#gvn-participants-intent'));
                jQuery(document.body).trigger('updated_checkout');
            }, refreshed);
            assert.equal(await page.locator('.gvn-participants__person').count(), 3);
            assert.equal(await buyer.isChecked(), true);
            if (quantity > 1) assert.equal(await secondName.inputValue(), 'Maria Silva');
            await others.check();
            assert.equal(await first.locator('input').first().isDisabled(), false);
            assert.equal(await first.locator('[data-participant-field="name"]').inputValue(), 'Rascunho do aluno');
            assert.equal(await first.locator('input').first().getAttribute('required'), '');
          }
        }
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

test('participant headings follow section title size, not page title size, in both layouts', {
    skip: !process.env.GVN_PLAYWRIGHT_PATH && 'Defina GVN_PLAYWRIGHT_PATH para usar Playwright já instalado.'
}, async () => {
    const { chromium } = require(process.env.GVN_PLAYWRIGHT_PATH);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.GVN_CHROMIUM_PATH });
    try {
        const page = await browser.newPage();
        const styles = ['gvn-checkout.css', 'gvn-checkout-layout-split.css', 'gvn-participants.css']
            .map(file => fs.readFileSync(path.join(root, 'assets/css', file), 'utf8')).join('\n');
        for (const layout of ['classic', 'split']) {
            for (const size of [19, 27]) {
                await page.setContent(`<style>h3 { font-size: 48px; } ${styles}</style>${fixture(layout, 2, size)}`);
                const headings = await page.locator('.gvn-participants h3').evaluateAll(nodes => nodes.map(node => getComputedStyle(node).fontSize));
                assert.deepEqual(headings, Array(3).fill(`${size}px`), layout);
            }
        }
    } finally {
        await browser.close();
    }
});
