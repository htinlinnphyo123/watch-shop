<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderAudit;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PendingOrderStockTest extends TestCase
{
    use RefreshDatabase;

    private Product $watch;
    private ProductItem $older;
    private ProductItem $selected;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->watch = Product::create(['name' => 'Reserved Watch', 'brand_id' => Brand::create(['name' => 'Test'])->id, 'price' => 10000, 'currency' => 'MMK']);
        $this->older = $this->watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000001', 'created_at' => now()->subDay()]);
        $this->selected = $this->watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000002']);
    }

    private function payload(array $extra = []): array
    {
        return array_replace([
            'status' => 'pending', 'payments' => [['method' => 'cash', 'amount' => 0]],
            'cart' => [['product_id' => $this->watch->id, 'item_id' => $this->selected->id]],
        ], $extra);
    }

    private function reserve(): Order
    {
        $this->post(route('pos.checkout'), $this->payload(['checkout_request_id' => (string) Str::uuid()]))->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_pending_checkout_reserves_exact_code_and_blocks_sales_manual_release_and_deletion(): void
    {
        $order = $this->reserve();
        $this->assertNotNull($order->stock_reserved_at);
        $this->assertSame('reserved', $this->selected->fresh()->status);
        $this->assertSame($order->items()->sole()->id, $this->selected->fresh()->order_item_id);
        $this->assertSame('available', $this->older->fresh()->status);
        $this->getJson(route('pos.products.scan', ['code' => $this->selected->system_unique_id]))->assertNotFound();
        $this->getJson(route('pos.products.available-items', $this->watch))->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $this->older->id);
        $this->getJson(route('pos.products', ['q' => 'Reserved Watch']))->assertOk()->assertJsonPath('data.0.available_items_count', 1);
        $this->post(route('pos.checkout'), $this->payload())->assertSessionHasErrors('cart.0');
        $this->post(route('pos.checkout'), $this->payload(['status' => 'completed', 'payments' => [['method' => 'cash', 'amount' => 10000]]]))
            ->assertSessionHasErrors('cart.0');
        $this->delete(route('items.destroy', $this->selected))->assertSessionHasErrors('status');
        $this->put(route('items.update', $this->selected), ['status' => 'available'])->assertSessionHasErrors('status');
        $this->delete(route('products.destroy', $this->watch))->assertSessionHasErrors('error');
        $this->assertNull($this->watch->fresh()->deleted_at);
        $this->assertNull($this->selected->fresh()->deleted_at);
        $this->assertSame('reserved', $this->selected->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertArrayHasKey('unit:'.$this->selected->id, OrderAudit::sole()->snapshot['watches']);
    }

    public function test_approval_sells_the_reserved_code_instead_of_an_older_available_watch(): void
    {
        $order = $this->reserve();
        $this->post(route('orders.approve', $order))->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('sold', $this->selected->fresh()->status);
        $this->assertSame('available', $this->older->fresh()->status);
        $this->assertSame($order->items()->sole()->id, $this->selected->fresh()->order_item_id);
        $this->post(route('orders.approve', $order))->assertSessionHasErrors('error');
        $this->assertSame(1, $this->watch->items()->where('status', 'sold')->count());
    }

    public function test_cancellation_releases_only_owned_reserved_stock_and_is_repeatable(): void
    {
        $order = $this->reserve();
        $this->older->update(['status' => 'reserved']);
        $this->post(route('orders.cancel', $order))->assertSessionHasNoErrors();
        $this->post(route('orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('available', $this->selected->fresh()->status);
        $this->assertNull($this->selected->fresh()->order_item_id);
        $this->assertSame('reserved', $this->older->fresh()->status);
        $this->assertSame(2, OrderAudit::where('order_id', $order->id)->count());
    }

    public function test_editing_exposes_only_owned_reserved_units_and_keeps_saved_price(): void
    {
        $order = $this->reserve();
        $line = $order->items()->sole();
        $this->watch->update(['price' => 20000]);
        $this->get(route('pos.index', ['order_id' => $order->id]))->assertInertia(fn (Assert $page) => $page
            ->where('editingOrder.cart.0.item_id', $this->selected->id)
            ->where('editingOrder.cart.0.system_unique_id', '000000000002')
            ->where('editingOrder.cart.0.product.pos_price_mmk', 10000));
        $this->getJson(route('pos.products.scan', ['code' => $this->selected->system_unique_id, 'order_id' => $order->id]))
            ->assertOk()->assertJsonPath('original_line_id', $line->id);
        $this->put(route('pos.orders.update', $order), $this->payload([
            'edit_version' => 0,
            'cart' => [['product_id' => $this->watch->id, 'item_id' => $this->selected->id, 'original_line_id' => $line->id]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('10000.00', $order->fresh()->total_amount);
        $this->assertSame('reserved', $this->selected->fresh()->status);
        $other = Order::create(['order_number' => 'OTHER', 'status' => 'pending', 'total_amount' => 0]);
        $this->getJson(route('pos.products.scan', ['code' => $this->selected->system_unique_id, 'order_id' => $other->id]))->assertNotFound();
    }

    public function test_replacing_a_unit_releases_old_hold_and_reserves_new_unit(): void
    {
        $order = $this->reserve();
        $this->put(route('pos.orders.update', $order), $this->payload([
            'edit_version' => 0, 'cart' => [['product_id' => $this->watch->id, 'item_id' => $this->older->id]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('available', $this->selected->fresh()->status);
        $this->assertNull($this->selected->fresh()->order_item_id);
        $this->assertSame('reserved', $this->older->fresh()->status);
        $this->assertSame($order->items()->sole()->id, $this->older->fresh()->order_item_id);
        $this->put(route('pos.orders.update', $order), $this->payload(['edit_version' => 0]))->assertSessionHasErrors('error');
    }

    public function test_failed_edits_restore_the_original_reservation_and_history(): void
    {
        $order = $this->reserve();
        $lineId = $this->selected->fresh()->order_item_id;
        $this->older->update(['status' => 'sold']);
        $this->put(route('pos.orders.update', $order), $this->payload([
            'edit_version' => 0, 'cart' => [['product_id' => $this->watch->id, 'item_id' => $this->older->id]],
        ]))->assertSessionHasErrors('cart.0');
        $this->assertSame('reserved', $this->selected->fresh()->status);
        $this->assertSame($lineId, $this->selected->fresh()->order_item_id);
        $this->assertSame(0, $order->fresh()->edit_version);
        $this->assertSame(1, OrderAudit::where('order_id', $order->id)->count());
    }

    public function test_missing_reserved_unit_blocks_approval_edit_and_cancellation_without_substitution(): void
    {
        $order = $this->reserve();
        // Simulate corrupt/externally modified stock; normal inventory routes block this.
        $this->selected->update(['order_item_id' => null]);
        $this->post(route('orders.approve', $order))->assertSessionHasErrors('error');
        $this->post(route('orders.cancel', $order))->assertSessionHasErrors('error');
        $this->put(route('pos.orders.update', $order), $this->payload(['edit_version' => 0]))->assertSessionHasErrors('error');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('available', $this->older->fresh()->status);
        $this->assertSame(1, OrderAudit::where('order_id', $order->id)->count());
    }

    public function test_generic_and_pinned_pending_lines_reserve_distinct_units(): void
    {
        $this->post(route('pos.checkout'), $this->payload([
            'cart' => [['product_id' => $this->watch->id, 'quantity' => 1], ['product_id' => $this->watch->id, 'item_id' => $this->selected->id]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->watch->items()->where('status', 'reserved')->count());
        $this->assertNotSame($this->older->fresh()->order_item_id, $this->selected->fresh()->order_item_id);
    }
}
