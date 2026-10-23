const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

test('participant CPF inputs reuse the delegated checkout mask', () => {
    const source = fs.readFileSync(path.join(__dirname, '../assets/js/gvn-checkout.js'), 'utf8');
    const start = source.indexOf('bindMasks: function () {') + 'bindMasks: '.length;
    const end = source.indexOf('\n        /*', start);
    let handler;
    const document = {};
    const $ = target => target === document ? {
        on(event, selector, callback) {
            assert.equal(event, 'input');
            assert.ok(selector.includes('#gvn-participants input[data-participant-field="cpf"]'));
            handler = callback;
        }
    } : {
        val(value) {
            if (value === undefined) return target.value;
            target.value = value;
        }
    };
    vm.runInNewContext(`(${source.slice(start, end).trim().replace(/,$/, '')})()`, { $, document });
    for (const [raw, expected] of [
        ['', ''], ['1234', '123.4'], ['1234567', '123.456.7'],
        ['12345678901', '123.456.789-01'], ['123.456.789-01', '123.456.789-01'],
        ['abc12345678901234', '123.456.789-01']
    ]) {
        const input = { dataset: { participantField: 'cpf' }, value: raw };
        handler.call(input);
        assert.equal(input.value, expected);
    }
});
