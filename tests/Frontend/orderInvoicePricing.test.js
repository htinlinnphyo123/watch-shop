import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { createServer as createHttpServer } from 'node:http';
import vue from '@vitejs/plugin-vue';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { fileURLToPath } from 'node:url';

test('invoice renders chosen original prices, individual discounts and the saved total', async () => {
    const server = await createServer({ configFile: false, plugins: [vue()],
        resolve: { alias: { '@': fileURLToPath(new URL('../../resources/js', import.meta.url)) } },
        server: { middlewareMode: true, hmr: { server: createHttpServer() } }, appType: 'custom',
        optimizeDeps: { noDiscovery: true, include: [] },
    });
    try {
        const { default: Invoice } = await server.ssrLoadModule('/resources/js/Components/OrderInvoice.vue');
        const order = { order_number: 'TEST', status: 'completed', total_amount: 180000, amount_paid: 180000,
            discount_percentage: 18.18, items: [
                { id: 1, price: 100000, quantity: 1, discount_percentage: 10, product: { name: 'Shop quote', model_number: 'SHOP-1' } },
                { id: 2, price: 120000, quantity: 1, discount_percentage: 25, product: { name: 'Web quote', model_number: 'WEB-1' } },
            ] };
        const html = await renderToString(createSSRApp(Invoice, { order }));
        assert.match(html, /Original Price/);
        assert.match(html, /100,000/);
        assert.match(html, /120,000/);
        assert.match(html, /10%/);
        assert.match(html, /25%/);
        assert.equal((html.match(/90,000/g) || []).length, 2);
        assert.match(html, /40,000/);
        assert.match(html, /180,000 MMK/);
        assert.doesNotMatch(html, /18\.18%/);
        const voucher = await renderToString(createSSRApp(Invoice, { order: { ...order, overall_discount_percentage: 2,
            overall_discount_amount: 3600, total_amount: 176000 } }));
        assert.match(voucher, /Overall Discount \(2%\)/);
        assert.match(voucher, /−3,600/);
        assert.match(voucher, /176,000 MMK/);
        assert.equal((voucher.match(/90,000/g) || []).length, 2);
        assert.doesNotMatch(voucher, /Rounding/);
        assert.doesNotMatch(html, /Overall Discount/);
        const rounded = await renderToString(createSSRApp(Invoice, { order: { ...order, total_amount: 2479000, items: [
            { id: 1, price: 2263000, quantity: 1, discount_percentage: 8, product: { name: '1696QC03' } },
            { id: 2, price: 432000, quantity: 1, discount_percentage: 8, product: { name: '9151YM03' } },
        ] } }));
        assert.match(rounded, /2,082,000/);
        assert.match(rounded, /397,000/);
        assert.doesNotMatch(rounded, /2,081,960|397,440/);
        assert.match(rounded, /181,040/);
        assert.match(rounded, /34,560/);
        assert.match(rounded, /2,479,000 MMK/);
        const legacy = await renderToString(createSSRApp(Invoice, { order: { ...order, total_amount: 198000,
            discount_percentage: 10, items: order.items.map(({ discount_percentage, ...item }) => item) } }));
        assert.match(legacy, /198,000 MMK/);
        assert.match(legacy, /22,000/);
        assert.doesNotMatch(legacy, /Original Price/);
    } finally { await server.close(); }
});
