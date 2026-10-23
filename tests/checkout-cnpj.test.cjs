const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');
const { execFileSync } = require('node:child_process');

const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'assets/js/gvn-checkout.js'), 'utf8');
const start = source.indexOf('bindMasks: function () {') + 'bindMasks: '.length;
const end = source.indexOf('\n        /*', start);
const bindMasks = source.slice(start, end).trim().replace(/,$/, '');

test('CNPJ production input handler accepts letters, numeric legacy, partial typing and numeric check digits', () => {
    let handler;
    const document = {};
    const input = { value: '', mask: 'cnpj', dataset: {} };
    const $ = node => node === document ? { on(event, selector, callback) { handler = callback; } } : {
        closest() { return this; },
        data() { return node.mask; },
        val(value) { if (value === undefined) return node.value; node.value = value; return this; }
    };
    vm.runInNewContext(`(${bindMasks})()`, { $, document });
    const cases = [
        ['12abc34501de35', '12.ABC.345/01DE-35'],
        ['12.abc.345/01de-35', '12.ABC.345/01DE-35'],
        ['12345678000195', '12.345.678/0001-95'],
        ['04252011000110', '04.252.011/0001-10'],
        ['12abc34501deAA', '12.ABC.345/01DE'],
        ['12abc34501de3555', '12.ABC.345/01DE-35'],
        ['', ''], ['1', '1'], ['12a', '12.A'], ['12abc3', '12.ABC.3'],
        ['12abc3450', '12.ABC.345/0'], ['12abc34501de3', '12.ABC.345/01DE-3']
    ];
    for (const [value, expected] of cases) {
        input.value = value;
        handler.call(input);
        assert.equal(input.value, expected, value);
    }
    input.mask = 'cpf';
    input.value = '11144477735';
    handler.call(input);
    assert.equal(input.value, '111.444.777-35');
});

test('CNPJ typing and form submission preserve letters in both checkout layouts', {
    skip: !process.env.GVN_PLAYWRIGHT_PATH && 'Defina GVN_PLAYWRIGHT_PATH para usar Playwright já instalado.'
}, async () => {
    const { chromium } = require(process.env.GVN_PLAYWRIGHT_PATH);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.GVN_CHROMIUM_PATH });
    try {
        const page = await browser.newPage();
        for (const layout of ['classic', 'split']) {
            const html = execFileSync(process.platform === 'win32' ? 'wsl' : 'php',
                [...(process.platform === 'win32' ? ['php'] : []), 'tests/fixtures/cnpj.php', layout],
                { cwd: root, encoding: 'utf8' });
            await page.setContent(html);
            await page.addScriptTag({ path: path.join(root, 'vendor/phpunit/php-code-coverage/src/Report/Html/Renderer/Template/js/jquery.min.js') });
            await page.evaluate(code => {
                const $ = window.jQuery;
                eval(`(${code})()`);
            }, bindMasks);
            const input = page.locator('[name="billing_cnpj"]');
            await input.pressSequentially('12abc34501de35');
            assert.equal(await input.inputValue(), '12.ABC.345/01DE-35', layout);
            assert.equal(await input.evaluate(node => new FormData(node.form).get('billing_cnpj')), '12.ABC.345/01DE-35');
            await input.fill('04.252.011/0001-10');
            assert.equal(await input.inputValue(), '04.252.011/0001-10');
        }
    } finally {
        await browser.close();
    }
});
