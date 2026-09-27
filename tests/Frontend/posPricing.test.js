import test from 'node:test';
import assert from 'node:assert/strict';
import { posPriceMmk } from '../../resources/js/utils/posPricing.js';

test('converted prices round to the nearest thousand with half-thousand rounding up', () => {
    for (const [rate, expected] of [[2145.24, 215000], [2142.32, 214000], [2145, 215000], [2144.99, 214000], [2140, 214000]]) {
        assert.equal(posPriceMmk({ price: '100.00', currency: 'USD' }, { usd_rate: String(rate) }), expected);
    }
    assert.equal(posPriceMmk({ price: 100, currency: 'THB' }, { thb_rate: 2145.24 }), 215000);
});

test('direct MMK prices and saved order prices are never re-rounded', () => {
    assert.equal(posPriceMmk({ price: 214524.25, currency: 'MMK' }), 214524.25);
    assert.equal(posPriceMmk({ price: 214232 }), 214232);
    assert.equal(posPriceMmk({ price: 100, currency: 'USD', pos_price_mmk: '214524.00' }, { usd_rate: 3000 }), 214524);
    assert.equal(posPriceMmk({ price: 100, currency: 'USD', pos_price_mmk: 0 }, { usd_rate: 3000 }), 0);
    assert.equal(posPriceMmk(null), 0);
});
