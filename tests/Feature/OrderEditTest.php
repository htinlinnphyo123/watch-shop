<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderEditTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Product $product;

    private ProductItem $unit;

    private int $lineId;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $brand = Brand::create(['name' => 'Test Brand']);
        $this->product = Product::create(['brand_id' => $brand->id, 'name' => 'Original Watch', 'price' => 30000, 'currency' => 'MMK']);
        $this->order = Order::create([
            'user_id' => auth()->id(), 'order_number' => 'EDIT-TEST', 'status' => 'completed',
            'total_amount' => 18000, 'discount_percentage' => 10, 'amount_paid' => 18000,
            'payment_method' => 'split', 'payments' => [['method' => 'cash', 'amount' => '8000.00'], ['method' => 'kbz_pay', 'amount' => '10000.00']],
        ]);
        $line = $this->order->items()->create(['product_id' => $this->product->id, 'quantity' => 1, 'price' => 20000]);
        $this->lineId = $line->id;
        $this->unit = ProductItem::create(['product_id' => $this->product->id, 'system_unique_id' => '001234567890', 'status' => 'sold', 'order_item_id' => $line->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'edit_version' => 0, 'discount_percentage' => 10,
            'payments' => $this->order->payments,
            'cart' => [['product_id' => $this->product->id, 'item_id' => $this->unit->id, 'original_line_id' => $this->lineId, 'quantity' => 1]],
        ], $overrides);
    }

    private function save(array $overrides = [])
    {
        return $this->put(route('pos.orders.update', $this->order), $this->payload($overrides));
    }

    public function test_cancellation_restores_only_owned_units_once_and_keeps_history(): void
    {
        $other = ProductItem::create(['product_id' => $this->product->id, 'status' => 'sold']);
        $this->post(route('orders.cancel', $this->order))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame('available', $this->unit->fresh()->status);
        $this->assertNull($this->unit->fresh()->order_item_id);
        $this->assertSame('sold', $other->fresh()->status);
        $this->assertSame(1, $this->order->items()->count());
        $this->assertSame('18000.00', $this->order->fresh()->amount_paid);
        $this->post(route('orders.cancel', $this->order))->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\OrderAudit::where('order_id', $this->order->id)->where('event', 'cancelled')->count());
        $this->save()->assertSessionHasErrors('error');
    }

    public function test_cod_pending_supports_zero_partial_payments_and_delivery_details(): void
    {
        $this->unit->update(['status' => 'available', 'order_item_id' => null]);
        $payload = [
            'status' => 'pending', 'delivery_code' => 'COD-123', 'remark' => 'Call before delivery', 'money_transfer_amount' => '0.00',
            'payments' => [['method' => 'cash', 'amount' => 0]],
            'cart' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ];
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertSame('pending', $order->status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame('COD-123', $order->delivery_code);
        $this->assertSame('available', $this->unit->fresh()->status);
        $this->put(route('pos.orders.update', $order), array_replace($payload, [
            'edit_version' => 0, 'money_transfer_amount' => '1000.00',
            'payments' => [['method' => 'transfer', 'amount' => 1000]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('1000.00', $order->fresh()->amount_paid);
        $this->assertSame('1000.00', $order->fresh()->money_transfer_amount);
        $this->get('/pos?order_id='.$order->id)->assertInertia(fn (Assert $page) => $page
            ->where('editingOrder.delivery_code', 'COD-123')->where('editingOrder.remark', 'Call before delivery')
            ->where('editingOrder.money_transfer_amount', '1000.00'));
        $this->post(route('orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame('available', $this->unit->fresh()->status);
        $this->assertSame(1, ProductItem::where('status', 'available')->count());
    }

    public function test_invalid_cod_fields_and_incomplete_stock_cancellation_are_rejected(): void
    {
        $this->save(['money_transfer_amount' => -1])->assertSessionHasErrors('money_transfer_amount');
        $this->save(['delivery_code' => str_repeat('x', 256)])->assertSessionHasErrors('delivery_code');
        $this->unit->update(['status' => 'reserved']);
        $this->post(route('orders.cancel', $this->order))->assertSessionHasErrors('error');
        $this->assertSame('completed', $this->order->fresh()->status);
        $this->assertSame('reserved', $this->unit->fresh()->status);
    }

    public function test_pos_loads_original_prices_watches_and_payments_without_modifying_stock(): void
    {
        $this->get('/pos?order_id='.$this->order->id)->assertInertia(fn (Assert $page) => $page
            ->component('POS/Index')->where('editingOrder.id', $this->order->id)
            ->where('editingOrder.cart.0.item_id', $this->unit->id)
            ->where('editingOrder.cart.0.original_line_id', $this->lineId)
            ->where('editingOrder.cart.0.product.pos_price_mmk', 20000)
            ->where('editingOrder.discount_percentage', '10.00')
            ->where('editingOrder.payments', $this->order->payments));
        $this->assertSame('sold', $this->unit->fresh()->status);
    }

    public function test_update_keeps_order_identity_saved_prices_and_attachments(): void
    {
        $file = OrderAttachment::create([
            'id' => (string) Str::uuid(), 'order_id' => $this->order->id, 'user_id' => auth()->id(),
            'name' => 'slip.pdf', 'path' => 'test/slip.pdf', 'mime_type' => 'application/pdf', 'size' => 4,
            'expires_at' => now()->addMinutes(10), 'uploaded_at' => now(),
        ]);
        $customer = Customer::create(['name' => 'New Customer', 'email' => 'new@example.test']);
        $this->save(['customer_id' => $customer->id])->assertSessionHasNoErrors()->assertRedirect(route('orders.show', $this->order));
        $order = $this->order->fresh();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('EDIT-TEST', $order->order_number);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('18000.00', $order->total_amount);
        $this->assertEquals(20000, $order->items()->sole()->price);
        $this->assertSame($this->order->payments, $order->payments);
        $this->assertSame(1, $order->edit_version);
        $this->assertSame($order->id, $file->fresh()->order_id);
        $this->assertSame($order->items()->sole()->id, $this->unit->fresh()->order_item_id);
    }

    public function test_replacing_a_watch_returns_old_unit_to_stock_and_sells_new_unit(): void
    {
        $new = ProductItem::create(['product_id' => $this->product->id, 'status' => 'available']);
        $this->save([
            'discount_percentage' => 0, 'payments' => [['method' => 'cash', 'amount' => 30000]],
            'cart' => [['product_id' => $this->product->id, 'item_id' => $new->id]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('available', $this->unit->fresh()->status);
        $this->assertNull($this->unit->fresh()->order_item_id);
        $this->assertSame('sold', $new->fresh()->status);
        $this->assertSame('30000.00', $this->order->fresh()->total_amount);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_underpayment_rolls_back_order_lines_and_released_stock(): void
    {
        $this->save(['payments' => [['method' => 'cash', 'amount' => 1]]])->assertSessionHasErrors('payments');
        $this->assertSame('sold', $this->unit->fresh()->status);
        $this->assertSame($this->lineId, $this->unit->fresh()->order_item_id);
        $this->assertSame(0, $this->order->fresh()->edit_version);
        $this->assertSame('18000.00', $this->order->fresh()->total_amount);
    }

    public function test_other_orders_sold_units_cannot_be_taken(): void
    {
        $other = ProductItem::create(['product_id' => $this->product->id, 'status' => 'sold']);
        $this->save(['cart' => [['product_id' => $this->product->id, 'item_id' => $other->id]]])->assertSessionHasErrors('error');
        $this->assertSame('sold', $this->unit->fresh()->status);
        $this->assertSame('sold', $other->fresh()->status);
    }

    public function test_stale_and_cancelled_orders_cannot_be_overwritten(): void
    {
        $this->order->update(['edit_version' => 1]);
        $this->save()->assertSessionHasErrors('error');
        $this->order->update(['status' => 'cancelled']);
        $this->save(['edit_version' => 1])->assertSessionHasErrors('error');
        $this->assertSame('sold', $this->unit->fresh()->status);
    }

    public function test_original_price_cannot_be_applied_to_a_different_unit(): void
    {
        $new = ProductItem::create(['product_id' => $this->product->id, 'status' => 'available']);
        $this->save(['cart' => [['product_id' => $this->product->id, 'item_id' => $new->id, 'original_line_id' => $this->lineId]]])
            ->assertSessionHasErrors('cart');
        $this->assertSame('available', $new->fresh()->status);
        $this->assertSame('sold', $this->unit->fresh()->status);
    }

    public function test_scan_and_unit_picker_include_only_this_orders_sold_units(): void
    {
        $this->getJson('/pos/products/scan?code=001234567890&order_id='.$this->order->id)
            ->assertOk()->assertJsonPath('original_line_id', $this->lineId);
        $this->getJson('/pos/products/scan?code=001234567890')->assertNotFound();
        $this->getJson('/pos/products/'.$this->product->id.'/available-items?order_id='.$this->order->id)
            ->assertOk()->assertJsonPath('items.0.original_line_id', $this->lineId);
    }

    public function test_pending_order_stays_pending_without_selling_stock(): void
    {
        $this->order->update(['status' => 'pending']);
        $this->unit->update(['status' => 'available', 'order_item_id' => null]);
        $this->save(['cart' => [['product_id' => $this->product->id, 'quantity' => 1, 'original_line_id' => $this->lineId]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame('available', $this->unit->fresh()->status);
    }

    public function test_new_checkout_allocates_distinct_units_for_generic_and_pinned_lines(): void
    {
        $first = ProductItem::create(['product_id' => $this->product->id, 'status' => 'available']);
        $second = ProductItem::create(['product_id' => $this->product->id, 'status' => 'available']);
        $this->post('/pos/checkout', [
            'payments' => [['method' => 'cash', 'amount' => 60000]],
            'cart' => [['product_id' => $this->product->id, 'quantity' => 1], ['product_id' => $this->product->id, 'item_id' => $first->id]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('sold', $first->fresh()->status);
        $this->assertSame('sold', $second->fresh()->status);
        $this->assertNotSame($first->fresh()->order_item_id, $second->fresh()->order_item_id);
    }

    public function test_approval_invalidates_an_already_open_edit_form(): void
    {
        $this->order->update(['status' => 'pending']);
        $this->unit->update(['status' => 'available', 'order_item_id' => null]);
        $this->post(route('orders.approve', $this->order))->assertSessionHasNoErrors();
        $this->save()->assertSessionHasErrors('error');
        $this->assertSame('completed', $this->order->fresh()->status);
        $this->assertSame(1, $this->order->fresh()->edit_version);
        $this->assertSame('sold', $this->unit->fresh()->status);
    }
}
