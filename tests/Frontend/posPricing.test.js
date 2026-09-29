import test from 'node:test';
import assert from 'node:assert/strict';
import { posPriceMmk, posDiscountPercentage } from '../../resources/js/utils/posPricing.js';

test('converted prices round to the nearest thousand with half-thousand rounding up', () => {
    for (const [rate, expected] of [[2145.24, 215000], [2142.32, 214000], [2145, 215000], [2144.99, 214000], [2140, 214000]]) {
        assert.equal(posPriceMmk({ price: '100.00', currency: 'USD' }, { usd_rate: String(rate) }), expected);
    }
    assert.equal(posPriceMmk({ price: 100, currency: 'THB' }, { thb_rate: 2145.24 }), 215000);
});

test('group overrides include zero and fall back to group defaults, while walk-ins use product discounts', () => {
    const product = { discount: 5, customer_groups: [{ id: 1, pivot: { percentage: '0' } }, { id: 2, pivot: { percentage: '15' } }] };
    assert.equal(posDiscountPercentage(product), 5);
    assert.equal(posDiscountPercentage(product, { id: 1, percentage: 20 }), 0);
    assert.equal(posDiscountPercentage(product, { id: '2', percentage: 20 }), 15);
    assert.equal(posDiscountPercentage(product, { id: 3, percentage: 20 }), 20);
});

test('direct MMK prices and saved order prices are never re-rounded', () => {
    assert.equal(posPriceMmk({ price: 214524.25, currency: 'MMK' }), 214524.25);
    assert.equal(posPriceMmk({ price: 214232 }), 214232);
    assert.equal(posPriceMmk({ price: 100, currency: 'USD', pos_price_mmk: '214524.00' }, { usd_rate: 3000 }), 214524);
    assert.equal(posPriceMmk({ price: 100, currency: 'USD', pos_price_mmk: 0 }, { usd_rate: 3000 }), 0);
    assert.equal(posPriceMmk(null), 0);
});
