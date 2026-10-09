// Run: node --test tests/checkout-steps.test.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/gvn-checkout.js'), 'utf8');
const start = source.indexOf('bindSteps: function () {') + 'bindSteps: '.length;
const end = source.indexOf('\n        /*', start);
const bindSteps = source.slice(start, end).trim().replace(/,$/, '');

// Minimal DOM/jQuery double; executes the production bindSteps function.
function checkout(stepCount = 3) {
    const node = (data = {}) => ({ data, classes: new Set(), children: [], events: {}, text: '' });
    const form = node();
    form.addEventListener = (event, handler) => { form.events[event] = handler; };
    const container = node();
    const body = node();
    const payment = node();
    payment.scrollIntoView = () => {};
    const steps = ['a', 'b', 'c'].slice(0, stepCount).map(id => ({ id, title: id }));
    const nav = node({ 'gvn-steps': steps, back: 'Voltar', next: 'Avançar', finish: 'Ir para pagamento' });
    const fields = steps.map(step => {
        const field = node({ 'gvn-step': step.id });
        const input = node();
        Object.assign(input, {
            parent: field, valid: true, disabled: false,
            validationMessage: 'Preencha este campo.',
            checkValidity() { return this.disabled || this.valid; },
            focus() { this.focused = true; },
            scrollIntoView() { this.scrolled = true; },
            reportValidity() { this.reported = true; }
        });
        field.children.push(input);
        return field;
    });
    const buttons = { back: node(), next: node() };
    const controls = node();
    const title = node();
    const count = node();

    class Query extends Array {
        each(fn) { this.forEach((item, i) => fn.call(item, i, item)); return this; }
        data(key) { return this[0].data[key]; }
        siblings() { return $(fields); }
        closest(selector) {
            if (selector === 'form') return $(form);
            if (selector === '.gvn-fields-dynamic') return $(container);
            return $(this[0].parent);
        }
        find(selector) {
            if (selector.endsWith('__back')) return $(buttons.back);
            if (selector.endsWith('__next')) return $(buttons.next);
            if (selector.endsWith('__title')) return $(title);
            if (selector.endsWith('__count')) return $(count);
            if (selector.includes('payment_method')) return $([]);
            const children = Array.from(this).flatMap(item => item.children);
            return $(children.filter(item => selector === '.gvn-step-error' ? item.error : !item.error));
        }
        filter(selector) {
            return $(Array.from(this).filter((item, i) => {
                if (typeof selector === 'function') return selector.call(item, i, item);
                if (selector === ':not(.gvn-step-hidden)') return !item.classes.has('gvn-step-hidden');
                if (selector === ':visible') return !item.parent.classes.has('gvn-step-hidden');
                return item.data['gvn-step'] === selector.match(/"(.*?)"/)[1];
            }));
        }
        hasClass(name) { return this[0].classes.has(name); }
        toggleClass(name, enabled) { return this.each(function () { enabled ? this.classes.add(name) : this.classes.delete(name); }); }
        addClass(name) { return this.toggleClass(name, true); }
        removeClass(name) { return this.toggleClass(name, false); }
        prop(key, value) { return this.each(function () { this[key] = value; }); }
        text(value) { return this.each(function () { this.text = value; }); }
        html() { return this; }
        after() { return this; }
        on(event, selector, handler) {
            if (typeof selector === 'function') handler = selector;
            return this.each(function () { this.events[event + (typeof selector === 'string' ? '|' + selector : '')] = handler; });
        }
        first() { return $(this.length ? this[0] : []); }
        focus() { return this.each(function () { this.focus(); }); }
        appendTo(target) { this[0].parent = target[0]; target[0].children.push(this[0]); return this; }
        remove() { return this.each(function () { this.parent.children = this.parent.children.filter(item => item !== this); }); }
    }
    function $(value) {
        if (value === '.gvn-checkout-steps') value = nav;
        else if (value === '[data-gvn-payment-panel]') value = payment;
        else if (typeof value === 'string') value = value.includes('role="alert"') ? Object.assign(node(), { error: true }) : controls;
        const result = new Query();
        result.push(...(Array.isArray(value) ? value : [value]));
        return result;
    }
    $.grep = (items, fn) => items.filter(fn);
    $.map = (items, fn) => items.map(fn).filter(value => value != null);
    vm.runInNewContext('(' + bindSteps + ')()', { $, document: { body } });
    return {
        fields, payment, buttons, container, body, form,
        next: () => controls.events['click|.gvn-checkout-steps__next'](),
        back: () => controls.events['click|.gvn-checkout-steps__back'](),
        hidden: () => payment.classes.has('gvn-payment-pending')
    };
}

test('valid steps release payment only after the last click; back hides it again', () => {
    const page = checkout();
    page.next(); page.next();
    assert.equal(page.hidden(), true);
    page.next();
    assert.equal(page.hidden(), false);
    page.back();
    assert.equal(page.hidden(), true);
});

test('single-step checkout keeps payment visible and has no step handlers', () => {
    const page = checkout(1);
    assert.equal(page.hidden(), false);
    assert.deepEqual(page.container.events, {});
    assert.deepEqual(page.form.events, {});
});

test('invalid input shows inline error even without reportValidity', () => {
    const page = checkout();
    const input = page.fields[0].children[0];
    input.valid = false;
    input.reportValidity = undefined;
    page.next();
    assert.equal(page.hidden(), true);
    assert.equal(input.focused, true);
    assert.equal(page.fields[0].children[1].text, input.validationMessage);
    input.valid = true;
    page.container.events['input change|.gvn-field__input']();
    assert.equal(page.fields[0].children.length, 1);
});

test('removing a preceding conditional step preserves last step and releases payment on first click', () => {
    const page = checkout();
    page.next(); page.next();
    page.fields[1].classes.add('gvn-field--conditional-hidden');
    page.fields[1].children[0].disabled = true;
    page.fields[1].children[0].valid = false;
    page.next();
    assert.equal(page.hidden(), false);
    assert.equal(page.fields[2].classes.has('gvn-step-hidden'), false);
});

test('checkout refresh preserves active step when a preceding step disappears', () => {
    const page = checkout();
    page.next();
    page.fields[0].classes.add('gvn-field--conditional-hidden');
    page.fields[0].children[0].disabled = true;
    page.body.events.updated_checkout();
    assert.equal(page.fields[1].classes.has('gvn-step-hidden'), false);
    assert.equal(page.buttons.next.text, 'Avançar');
});

test('last step reveals an invalid previous field and never releases payment', () => {
    const page = checkout();
    page.next(); page.next();
    const input = page.fields[0].children[0];
    input.valid = false;
    page.next();
    assert.equal(page.hidden(), true);
    assert.equal(page.fields[0].classes.has('gvn-step-hidden'), false);
    assert.equal(input.reported, true);
    assert.equal(page.fields[0].children[1].text, input.validationMessage);
});

test('submit handler blocks invalid fields and reveals their step', () => {
    const page = checkout();
    page.next(); page.next(); page.next();
    page.fields[0].children[0].valid = false;
    let prevented = false;
    let stopped = false;
    page.form.events.submit({
        preventDefault() { prevented = true; },
        stopImmediatePropagation() { stopped = true; }
    });
    assert.equal(prevented, true);
    assert.equal(stopped, true);
    assert.equal(page.fields[0].classes.has('gvn-step-hidden'), false);
    assert.equal(page.hidden(), true);
});
