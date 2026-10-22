const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/gvn-checkout.js'), 'utf8');
const start = source.indexOf('syncFieldGroups: function () {') + 'syncFieldGroups: '.length;
const end = source.indexOf('\n        bindSteps:', start);
const sync = source.slice(start, end).trim().replace(/,$/, '');

test('group boxes disappear only when all fields are hidden by conditions or steps', () => {
    let visible = true;
    const classes = new Set();
    const group = {
        querySelector(selector) {
            assert.equal(selector, '.gvn-field:not(.gvn-field--conditional-hidden):not(.gvn-step-hidden)');
            return visible ? {} : null;
        },
        classList: { toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); } }
    };
    const context = { document: { querySelectorAll() { return [group]; } } };
    const run = () => vm.runInNewContext('(' + sync + ')()', context);
    run();
    assert.equal(classes.has('gvn-field-group--empty'), false);
    visible = false;
    run();
    assert.equal(classes.has('gvn-field-group--empty'), true);
    visible = true;
    run();
    assert.equal(classes.has('gvn-field-group--empty'), false);
});

test('group borders, widths and empty states work on mobile and desktop in both layouts', {
    skip: !process.env.GVN_PLAYWRIGHT_PATH && 'Defina GVN_PLAYWRIGHT_PATH para usar Playwright já instalado.'
}, async () => {
    const { chromium } = require(process.env.GVN_PLAYWRIGHT_PATH);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.GVN_CHROMIUM_PATH });
    try {
        const page = await browser.newPage();
        const css = fs.readFileSync(path.join(__dirname, '../assets/css/gvn-checkout.css'), 'utf8');
        const splitCss = fs.readFileSync(path.join(__dirname, '../assets/css/gvn-checkout-layout-split.css'), 'utf8');
        for (const layout of ['classic', 'split']) {
            for (const width of [320, 390, 768, 1280]) {
                await page.setViewportSize({ width, height: 1000 });
                await page.setContent(`<style>${css}${splitCss}</style><body class="gvn-checkout-isolated"><div class="gvn-checkout gvn-layout-${layout}"><div class="gvn-fields-dynamic">
                    <div class="gvn-field-group" style="border:2px dashed #aabbcc;border-radius:6px;background:#f8f9fa;padding:20px">
                        <h3 class="gvn-field-group__title">Dados do aluno</h3>
                        <div class="gvn-field gvn-field--w50"><label>Nome<input class="gvn-field__input"></label></div>
                        <div class="gvn-field gvn-field--w50"><label>Sobrenome<input class="gvn-field__input"></label></div>
                    </div><div class="gvn-field gvn-field--w100">Fora do grupo</div></div></div></body>`);
                const geometry = await page.locator('.gvn-field-group').evaluate(group => {
                    const style = getComputedStyle(group), rect = group.getBoundingClientRect();
                    return {
                        border: style.borderTopWidth, borderStyle: style.borderTopStyle, color: style.borderTopColor,
                        overflow: document.documentElement.scrollWidth > innerWidth,
                        fits: [...group.querySelectorAll('.gvn-field')].every(field => {
                            const box = field.getBoundingClientRect();
                            return box.left >= rect.left && box.right <= rect.right;
                        })
                    };
                });
                assert.deepEqual(geometry, { border: '2px', borderStyle: 'dashed', color: 'rgb(170, 187, 204)', overflow: false, fits: true });
                await page.evaluate(script => {
                    const group = document.querySelector('.gvn-field-group');
                    group.querySelectorAll('.gvn-field').forEach(field => field.classList.add('gvn-step-hidden'));
                    (0, eval)('(' + script + ')()');
                }, sync);
                assert.equal(await page.locator('.gvn-field-group').isVisible(), false);
                await page.evaluate(script => {
                    document.querySelector('.gvn-field-group .gvn-field').classList.remove('gvn-step-hidden');
                    (0, eval)('(' + script + ')()');
                }, sync);
                assert.equal(await page.locator('.gvn-field-group').isVisible(), true);
            }
        }
    } finally {
        await browser.close();
    }
});
