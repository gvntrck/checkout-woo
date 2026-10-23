const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const test = require('node:test');

const root = path.join(__dirname, '..');
const fixture = (layout, count = 2) => execFileSync(process.platform === 'win32' ? 'wsl' : 'php',
    [...(process.platform === 'win32' ? ['php'] : []), 'tests/fixtures/participants.php', layout, String(count)],
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
            await page.setContent(`<style>${styles}</style>${fixture(layout)}`);
            await page.addScriptTag({ path: path.join(root, 'vendor/phpunit/php-code-coverage/src/Report/Html/Renderer/Template/js/jquery.min.js') });
            await page.addScriptTag({ path: path.join(root, 'assets/js/gvn-participants.js') });
            await page.waitForFunction(() => window.jQuery && jQuery.isReady);
            assert.equal(await page.locator('.gvn-participants__person').count(), 2);
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
            await secondName.fill('Maria Silva');
            await page.locator('.gvn-participants__buyer').check();
            assert.equal(await first.locator('.gvn-participants__fields').isVisible(), false);
            assert.equal(await first.locator('input').first().isDisabled(), true);
            // Simulate WooCommerce replacing an AJAX fragment with an empty server draft.
            const refreshed = fixture(layout, 3);
            await page.evaluate(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                document.querySelector('#gvn-participants').replaceWith(doc.querySelector('#gvn-participants'));
                jQuery(document.body).trigger('updated_checkout');
            }, refreshed);
            assert.equal(await page.locator('.gvn-participants__person').count(), 3);
            assert.equal(await page.locator('.gvn-participants__buyer').isChecked(), true);
            assert.equal(await secondName.inputValue(), 'Maria Silva');
            await page.locator('.gvn-participants__buyer').uncheck();
            assert.equal(await first.locator('input').first().isDisabled(), false);
            assert.equal(await first.locator('[data-participant-field="name"]').inputValue(), 'Rascunho do aluno');
            assert.equal(await first.locator('input').first().getAttribute('required'), '');
        }
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
