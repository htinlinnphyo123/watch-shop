<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\SalesWorkbookExport;
use App\Support\PosPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosPriceRoundingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    public function test_conversion_rounds_nearest_thousand_with_half_up_and_preserves_mmk(): void
    {
        foreach ([[2145.24, 215000], [2142.32, 214000], [2145, 215000], [2144.99, 214000], [2140, 214000]] as [$rate, $expected]) {
            $this->assertSame((float) $expected, PosPrice::inMmk(100, 'USD', ['usd_rate' => $rate]));
        }
        $this->assertSame(215000.0, PosPrice::inMmk(100, 'THB', ['thb_rate' => 2145.24]));
        $this->assertSame(214524.25, PosPrice::inMmk(214524.25, 'MMK', ['mmk_rate' => 2]));
        $this->assertSame(214232.0, PosPrice::inMmk(214232, null, []));
    }

    public function test_checkout_saves_rounded_unit_prices_before_quantity_discount_and_payments(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $brand = Brand::create(['name' => 'Test']);
        foreach ([[2145.24, 215000], [2142.32, 214000], [2145, 215000]] as [$rate, $unitPrice]) {
            Setting::updateOrCreate(['key' => 'usd_rate'], ['value' => (string) $rate]);
            $product = Product::create(['name' => 'USD Watch', 'brand_id' => $brand->id, 'price' => 100, 'currency' => 'USD']);
            $product->items()->createMany([['status' => 'available'], ['status' => 'available']]);
            $total = $unitPrice * 2 * 0.9;
            $this->post(route('pos.checkout'), [
                'cart' => [['product_id' => $product->id, 'quantity' => 2]],
                'discount_percentage' => 10,
                'payments' => [['method' => 'cash', 'amount' => $total]],
            ])->assertSessionHasNoErrors();
            $order = Order::latest('id')->firstOrFail();
            $this->assertEquals($unitPrice, $order->items()->sole()->price);
            $this->assertEquals($total, $order->total_amount);
            $this->assertEquals($total, $order->amount_paid);
            $this->assertSame(2, $product->items()->where('status', 'sold')->count());
            $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page
                ->where('order.total_amount', number_format($total, 2, '.', ''))
                ->where('order.items.0.price', fn ($value) => (float) $value === (float) $unitPrice));
            $row = app(SalesWorkbookExport::class)->saleRow($order->load(['items.product.brand', 'user']));
            $this->assertEquals($unitPrice * 2, $row['Price']);
            $this->assertEquals($total, $row['Total Payment']);
        }
    }

    public function test_existing_order_price_is_preserved_when_editing_after_rate_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $brand = Brand::create(['name' => 'Test']);
        $product = Product::create(['name' => 'Old USD Watch', 'brand_id' => $brand->id, 'price' => 100, 'currency' => 'USD']);
        $product->items()->create(['status' => 'available']);
        $order = Order::create(['user_id' => $admin->id, 'order_number' => 'OLD-PRICE', 'status' => 'pending', 'total_amount' => 214524, 'amount_paid' => 0, 'payments' => [['method' => 'cash', 'amount' => 0]]]);
        $line = $order->items()->create(['product_id' => $product->id, 'price' => 214524, 'quantity' => 1]);
        Setting::updateOrCreate(['key' => 'usd_rate'], ['value' => '3000']);
        $this->get(route('pos.index', ['order_id' => $order->id]))->assertInertia(fn (Assert $page) => $page->where('editingOrder.cart.0.product.pos_price_mmk', 214524));
        $this->put(route('pos.orders.update', $order), [
            'status' => 'pending', 'edit_version' => 0, 'discount_percentage' => 0,
            'cart' => [['product_id' => $product->id, 'original_line_id' => $line->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 0]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('214524.00', $order->fresh()->total_amount);
        $this->assertEquals(214524, $order->items()->sole()->price);
        $this->post(route('orders.approve', $order))->assertSessionHasNoErrors();
        $this->assertSame('214524.00', $order->fresh()->total_amount);
        $this->assertSame('completed', $order->fresh()->status);
    }
}
