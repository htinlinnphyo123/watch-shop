<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private array $payload;

    private ProductItem $item;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $brand = Brand::create(['name' => 'Reservation Brand']);
        $product = Product::create(['brand_id' => $brand->id, 'name' => 'Watch', 'price' => 10000, 'currency' => 'MMK']);
        $this->item = $product->items()->create(['system_unique_id' => '123456789012', 'status' => 'available']);
        $customer = Customer::create(['name' => 'Buyer', 'email' => 'buyer@example.test']);
        $this->payload = ['type' => 'reservation', 'status' => 'pending', 'customer_id' => $customer->id,
            'brand_id' => $brand->id, 'product_id' => $product->id, 'product_item_id' => $this->item->id,
            'amount_paid' => '1000.00', 'remark' => 'Deposit'];
    }

    private function reserve(): PreOrder
    {
        $this->post(route('pre-orders.store'), $this->payload)->assertSessionHasNoErrors();

        return PreOrder::latest('id')->first();
    }

    public function test_hold_is_not_in_pos_and_cannot_be_reserved_twice(): void
    {
        $this->reserve();
        $this->get('/pos/products/'.$this->item->product_id.'/available-items')->assertJsonCount(0, 'items');
        $this->post(route('pre-orders.store'), $this->payload)->assertSessionHasErrors('product_item_id');
        $this->assertDatabaseCount('pre_orders', 1);
        $this->get(route('pre-orders.index'))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->where('preOrders.data.0.reserved_item.system_unique_id', '123456789012')
            ->where('preOrders.data.0.product.available_stock', 0));
    }

    public function test_cancellation_releases_watch_and_cannot_reopen_old_hold(): void
    {
        $record = $this->reserve();
        $this->put(route('pre-orders.update', $record), array_replace($this->payload, ['status' => 'cancelled']))->assertSessionHasNoErrors();
        $this->assertSame('available', $this->item->fresh()->status);
        $this->put(route('pre-orders.update', $record), $this->payload)->assertSessionHasErrors('status');
        $this->reserve();
        $this->assertSame('reserved', $this->item->fresh()->status);
    }

    public function test_completion_marks_held_watch_sold_and_preserves_creator(): void
    {
        $record = $this->reserve();
        $creator = $record->user_id;
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $data = array_replace($this->payload, ['status' => 'completed', 'amount_paid' => '10000.00']);
        $this->put(route('pre-orders.update', $record), $data)->assertSessionHasNoErrors();
        $this->assertSame('sold', $this->item->fresh()->status);
        $this->assertSame($creator, $record->fresh()->user_id);
        $this->put(route('pre-orders.update', $record), $data)->assertSessionHasNoErrors();
        $this->put(route('pre-orders.update', $record), array_replace($data, ['status' => 'cancelled']))->assertSessionHasErrors('status');
        $this->assertSame('sold', $this->item->fresh()->status);
    }

    public function test_pre_order_can_be_converted_and_changing_watch_releases_old_hold(): void
    {
        $data = array_replace($this->payload, ['type' => 'pre_order', 'product_item_id' => null]);
        $this->post(route('pre-orders.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame('available', $this->item->fresh()->status);
        $record = PreOrder::sole();
        $this->put(route('pre-orders.update', $record), $this->payload)->assertSessionHasNoErrors();
        $other = ProductItem::create(['product_id' => $this->item->product_id, 'system_unique_id' => '123456789013', 'status' => 'available']);
        $this->put(route('pre-orders.update', $record), array_replace($this->payload, ['product_item_id' => $other->id]))->assertSessionHasNoErrors();
        $this->assertSame('available', $this->item->fresh()->status);
        $this->assertSame('reserved', $other->fresh()->status);
        $this->put(route('pre-orders.update', $record), $data)->assertSessionHasNoErrors();
        $this->assertSame('available', $other->fresh()->status);
    }

    public function test_invalid_or_sold_units_and_direct_completion_are_rejected(): void
    {
        $this->post(route('pre-orders.store'), array_replace($this->payload, ['product_item_id' => null]))->assertSessionHasErrors('product_item_id');
        $this->post(route('pre-orders.store'), array_replace($this->payload, ['status' => 'completed']))->assertSessionHasErrors('status');
        $this->item->update(['status' => 'sold']);
        $this->post(route('pre-orders.store'), $this->payload)->assertSessionHasErrors('product_item_id');
        $this->assertDatabaseCount('pre_orders', 0);
    }

    public function test_inventory_cannot_release_or_delete_a_held_watch(): void
    {
        $this->reserve();
        $this->put(route('items.update', $this->item), ['status' => 'available'])->assertSessionHasErrors('status');
        $this->delete(route('items.destroy', $this->item))->assertSessionHasErrors('status');
        $this->assertSame('reserved', $this->item->fresh()->status);
        $this->assertNull($this->item->fresh()->deleted_at);
    }
}
