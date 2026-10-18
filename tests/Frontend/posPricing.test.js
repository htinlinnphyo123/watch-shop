import test from 'node:test';
import assert from 'node:assert/strict';
import { posPriceMmk, posDiscountPercentage, orderDiscountAmount, posOrderTotal } from '../../resources/js/utils/posPricing.js';

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


test('order totals with or without discount round once to the nearest thousand, with halves up', () => {
    assert.equal(orderDiscountAmount(1238000, 8), 99040);
    assert.equal(posOrderTotal(1238000, 8), 1139000);
    assert.equal(posOrderTotal(1238000, 9), 1127000);
    assert.equal(posOrderTotal(1238000, 25), 929000);
    assert.equal(posOrderTotal(1238000, 100), 0);
    assert.equal(posOrderTotal(214524.25, 0), 215000);
    assert.equal(posOrderTotal(2140200, 0), 2140000);
    assert.equal(posOrderTotal(0, 8), 0);
    assert.equal(posOrderTotal(214000 * 2, 10), 385000);
});

test('each watch chooses shop or web base and keeps an independent discount', async () => {
    const { posLinePrice, posLineDiscountPercentage, lineDiscountAmount } = await import('../../resources/js/utils/posPricing.js');
    const product = { price: 100000, web_price: 120000, currency: 'MMK', discount: 5 };
    const shop = { product, qty: 1, price_source: 'shop', discount_percentage: 10 };
    const web = { product, qty: 1, price_source: 'web', discount_percentage: 25 };
    const net = line => posLinePrice(line) * line.qty - lineDiscountAmount(posLinePrice(line), line.qty, posLineDiscountPercentage(line));
    assert.equal(net(shop), 90000);
    assert.equal(net(web), 90000);
    assert.equal(posLineDiscountPercentage({ product, discount_percentage: 0 }), 0);
    assert.equal(lineDiscountAmount(12345.67, 3, 12.34), lineDiscountAmount(12345.67, 1, 12.34) * 3);
});

test('saved shop and web quotes survive product and exchange rate changes', async () => {
    const { posLinePrice, posLinePrices, posLineDiscountPercentage } = await import('../../resources/js/utils/posPricing.js');
    const line = { product: { price: 500, web_price: 600, currency: 'USD', saved_pricing: {
        shop_price: 100000, web_price: 120000, price_source: 'web', discount_percentage: 25,
    } } };
    assert.equal(posLinePrice(line, { usd_rate: 5000 }), 120000);
    assert.equal(posLineDiscountPercentage(line, { percentage: 50 }), 25);
    assert.equal(posLinePrice({ ...line, price_source: 'shop' }, { usd_rate: 5000 }), 100000);
    assert.equal(posLinePrices({ product: { price: 100000, web_price: null } }).web_price, null);
});

test('switching original price fills and recalculates the discount, including after clearing or overriding it', async () => {
    const { posLineDiscountPercentage, setPosLinePriceSource } = await import('../../resources/js/utils/posPricing.js');
    const line = { product: { price: 100000, web_price: 120000, currency: 'MMK' } };
    assert.equal(posLineDiscountPercentage(line), 0);
    setPosLinePriceSource(line, 'web');
    assert.equal(line.discount_percentage, 16.67);
    line.discount_percentage = '';
    setPosLinePriceSource(line, 'shop');
    assert.equal(line.discount_percentage, 0);
    line.discount_percentage = 25;
    setPosLinePriceSource(line, 'web');
    assert.equal(line.discount_percentage, 16.67);
    line.discount_percentage = 20;
    assert.equal(posLineDiscountPercentage(line), 20);
});

test('automatic discount includes the shop discount and uses converted or saved prices', async () => {
    const { posLineAutoDiscountPercentage, posLineDiscountPercentage, setPosLinePriceSource } = await import('../../resources/js/utils/posPricing.js');
    const line = { product: { price: 100, web_price: 120, currency: 'USD', discount: 10 }, price_source: 'web' };
    assert.equal(posLineAutoDiscountPercentage(line, null, { usd_rate: 1000 }), 25);
    assert.equal(posLineAutoDiscountPercentage({ product: { price: 0, web_price: 0 } }), 0);
    const saved = { product: { price: 500000, web_price: 600000, saved_pricing: {
        shop_price: 100000, web_price: 120000, price_source: 'shop', discount_percentage: 5,
    } } };
    assert.equal(posLineDiscountPercentage(saved), 5);
    setPosLinePriceSource(saved, 'web');
    assert.equal(posLineDiscountPercentage(saved), 16.67);
});
