import test from 'node:test';
import assert from 'node:assert/strict';
import { stockLabelDetails } from '../../resources/js/utils/stockLabels.js';

test('single-product labels show the same rounded MMK price as POS and retain leading-zero codes', () => {
    const label = stockLabelDetails({ id: 1, system_unique_id: '001234567890' }, { name: 'Watch', model_number: '00123', price: '100.00', currency: 'USD' }, { usd_rate: '2145.24' });
    assert.equal(label.price_label, '215,000 MMK');
    assert.equal(label.system_unique_id, '001234567890');
    assert.equal(label.name, 'Watch');
    assert.equal(label.model_number, '00123');
});

test('mixed-product labels retain the correct product and server price snapshot for every unit', () => {
    const first = stockLabelDetails({ id: 1, product: { name: 'First', model_number: 'A' }, price_mmk: 215000 }, null, { usd_rate: 9999 });
    const second = stockLabelDetails({ id: 2, product: { name: 'Second', model_number: 'B' }, price_mmk: 214232.25 }, null);
    assert.equal(first.name, 'First');
    assert.equal(first.price_label, '215,000 MMK');
    assert.equal(second.name, 'Second');
    assert.equal(second.model_number, 'B');
    assert.equal(second.price_label, '214,232.25 MMK');
});

test('shared accessory labels support MMK prices, missing model and explicit zero price', () => {
    assert.equal(stockLabelDetails({ id: 1 }, { name: 'Strap', price: 12345, currency: 'MMK' }).price_label, '12,345 MMK');
    const free = stockLabelDetails({ id: 2, price_mmk: 0, product: { name: 'Free strap' } }, null);
    assert.equal(free.price_label, '0 MMK');
    assert.equal(free.model_number, '');
});
