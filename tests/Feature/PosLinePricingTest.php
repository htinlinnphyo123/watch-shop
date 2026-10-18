<?php

namespace Tests\Feature;

use App\Models\{Brand, Order, OrderAudit, Product, Setting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosLinePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    private function watch(array $attributes = []): Product
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::create(array_merge(['name' => 'Two prices', 'brand_id' => Brand::create(['name' => 'Test'])->id,
            'currency' => 'MMK', 'price' => 100000, 'web_price' => 120000], $attributes));
        $product->items()->createMany([['status' => 'available', 'system_unique_id' => 'LINE001'], ['status' => 'available', 'system_unique_id' => 'LINE002']]);
        return $product;
    }

    public function test_mixed_prices_and_discounts_are_saved_and_exposed_on_invoice_and_history(): void
    {
        $product = $this->watch();
        $this->post(route('pos.checkout'), [
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price_source' => 'shop', 'discount_percentage' => 10, 'price' => 1],
                ['product_id' => $product->id, 'quantity' => 1, 'price_source' => 'web', 'discount_percentage' => 25],
            ],
            'payments' => [['method' => 'cash', 'amount' => 180000]],
        ])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertEquals(180000, $order->total_amount);
        $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.price', fn ($value) => (float) $value === 100000.0)
            ->where('order.items.0.price_source', 'shop')->where('order.items.0.discount_percentage', '10.00')
            ->where('order.items.1.price', fn ($value) => (float) $value === 120000.0)
            ->where('order.items.1.price_source', 'web')->where('order.items.1.discount_percentage', '25.00'));
        $watches = array_values(OrderAudit::sole()->snapshot['watches']);
        $this->assertSame('shop', $watches[0]['price_source']);
        $this->assertSame('25.00', $watches[1]['discount_percentage']);
        $this->assertSame(2, $product->items()->where('status', 'sold')->count());
    }

    public function test_edit_and_scan_preserve_saved_choices_after_catalog_and_exchange_rate_changes(): void
    {
        $product = $this->watch(['price' => 100, 'web_price' => 120, 'currency' => 'USD']);
        Setting::updateOrCreate(['key' => 'usd_rate'], ['value' => '1000']);
        $this->post(route('pos.checkout'), [
            'status' => 'pending', 'cart' => [['product_id' => $product->id, 'quantity' => 2, 'price_source' => 'web', 'discount_percentage' => 25]],
            'payments' => [['method' => 'cash', 'amount' => 0]],
        ])->assertSessionHasNoErrors();
        $order = Order::sole();
        $line = $order->items()->sole();
        $product->update(['price' => 500, 'web_price' => 600]);
        Setting::where('key', 'usd_rate')->update(['value' => '2000']);
        $this->get(route('pos.index', ['order_id' => $order->id]))->assertInertia(fn (Assert $page) => $page
            ->where('editingOrder.cart.0.product.saved_pricing.shop_price', 100000)
            ->where('editingOrder.cart.0.product.saved_pricing.web_price', '120000.00')
            ->where('editingOrder.cart.0.product.saved_pricing.discount_percentage', 25));
        $this->getJson(route('pos.products.scan', ['code' => 'LINE001', 'order_id' => $order->id]))
            ->assertOk()->assertJsonPath('product.saved_pricing.price_source', 'web');
        $this->getJson(route('pos.products.available-items', ['product' => $product->id, 'order_id' => $order->id]))
            ->assertOk()->assertJsonPath('items.0.saved_pricing.price_source', 'web');
        $cart = $line->soldItems->map(fn ($unit) => ['product_id' => $product->id, 'item_id' => $unit->id, 'original_line_id' => $line->id])->all();
        $this->put(route('pos.orders.update', $order), ['status' => 'pending', 'edit_version' => 0, 'cart' => $cart, 'payments' => [['method' => 'cash', 'amount' => 0]]])->assertSessionHasNoErrors();
        $this->assertEquals(180000, $order->fresh()->total_amount);
        $cart = $order->items()->get()->map(fn ($item) => ['product_id' => $product->id, 'item_id' => $item->soldItems->sole()->id,
            'original_line_id' => $item->id, 'price_source' => 'shop', 'discount_percentage' => 0])->all();
        $this->put(route('pos.orders.update', $order), ['status' => 'pending', 'edit_version' => 1, 'cart' => $cart, 'payments' => [['method' => 'cash', 'amount' => 0]]])->assertSessionHasNoErrors();
        $this->assertEquals(200000, $order->fresh()->total_amount);
    }

    public function test_web_price_automatically_discounts_to_shop_price_and_manual_override_still_wins(): void
    {
        $product = $this->watch();
        $this->post(route('pos.checkout'), [
            'cart' => [['product_id' => $product->id, 'quantity' => 1, 'price_source' => 'web']],
            'payments' => [['method' => 'cash', 'amount' => 100000]],
        ])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('16.67', $order->items()->sole()->discount_percentage);
        $this->assertEquals(100000, $order->total_amount);
        $this->post(route('pos.checkout'), [
            'cart' => [['product_id' => $product->id, 'quantity' => 1, 'price_source' => 'web', 'discount_percentage' => 25]],
            'payments' => [['method' => 'cash', 'amount' => 90000]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(90000, Order::latest('id')->firstOrFail()->total_amount);
    }

    public function test_invalid_line_pricing_does_not_save_orders_or_allocate_stock(): void
    {
        $product = $this->watch(['web_price' => null]);
        foreach ([['price_source' => 'web'], ['price_source' => 'custom'], ['discount_percentage' => -1], ['discount_percentage' => 101], ['discount_percentage' => 1.234]] as $extra) {
            $this->post(route('pos.checkout'), ['cart' => [array_merge(['product_id' => $product->id, 'quantity' => 1], $extra)],
                'payments' => [['method' => 'cash', 'amount' => 100000]]])->assertSessionHasErrors();
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_audits', 0);
            $this->assertSame(2, $product->items()->where('status', 'available')->count());
        }
    }

    public function test_line_discounts_use_exact_amounts_instead_of_rounded_blended_percentage(): void
    {
        $product = $this->watch(['price' => 10000000, 'web_price' => 30000000]);
        $this->post(route('pos.checkout'), ['cart' => [
            ['product_id' => $product->id, 'quantity' => 1, 'price_source' => 'shop', 'discount_percentage' => 0.01],
            ['product_id' => $product->id, 'quantity' => 1, 'price_source' => 'web', 'discount_percentage' => 0],
        ], 'payments' => [['method' => 'cash', 'amount' => 39999000]]])->assertSessionHasNoErrors();
        $this->assertEquals(39999000, Order::sole()->total_amount);
    }
}
