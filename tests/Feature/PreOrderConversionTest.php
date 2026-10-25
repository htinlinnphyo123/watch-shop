<?php

namespace Tests\Feature;

use App\Models\{Brand, Customer, Order, PreOrder, Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PreOrderConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->actingAs($user);
        $brand = Brand::create(['name' => 'Test']);
        $product = Product::create(['name' => 'Arrived watch', 'brand_id' => $brand->id, 'price' => 10000, 'currency' => 'MMK']);
        $unit = $product->items()->create(['status' => 'available', 'system_unique_id' => '123456789012']);
        $customer = Customer::create(['name' => 'Buyer']);
        $source = PreOrder::create(['user_id' => $user->id, 'customer_id' => $customer->id, 'brand_id' => $brand->id,
            'product_id' => $product->id, 'type' => 'pre_order', 'status' => 'ordered', 'amount_paid' => 2000,
            'deposit_amount' => 2000, 'money_transfer_amount' => 2000]);
        $source->refresh();
        $payload = ['pre_order_id' => $source->id, 'pre_order_version' => $source->updated_at->toISOString(),
            'customer_id' => $customer->id, 'order_type' => 'reserved', 'status' => 'pending',
            'money_transfer_amount' => 2000, 'payments' => [['method' => 'cash', 'amount' => 0]],
            'checkout_request_id' => (string) Str::uuid(),
            'cart' => [['product_id' => $product->id, 'item_id' => $unit->id, 'quantity' => 1]]];
        return [$source, $unit, $payload];
    }

    public function test_conversion_carries_deposit_once_links_records_and_is_idempotent(): void
    {
        [$source, $unit, $payload] = $this->fixture();
        $this->get(route('pos.index', ['pre_order_id' => $source->id]))->assertInertia(fn (Assert $page) => $page
            ->component('POS/Index')->where('sourcePreOrder.id', $source->id)->where('sourcePreOrder.amount_paid', '2000.00'));
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame($order->id, $source->fresh()->converted_order_id);
        $this->assertSame('converted', $source->fresh()->status);
        $this->assertEquals(2000, $order->money_transfer_amount);
        $this->assertEquals(0, $order->amount_paid);
        $this->assertSame('reserved', $unit->fresh()->status);
        $this->post(route('pos.checkout'), $payload)->assertRedirect(route('orders.show', $order));
        $this->post(route('pos.checkout'), array_replace($payload, ['checkout_request_id' => (string) Str::uuid()]))
            ->assertSessionHasErrors('pre_order_id');
        $this->assertDatabaseCount('orders', 1);
        $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('order.source_pre_order.id', $source->id));
        $this->put(route('pre-orders.update', $source), $source->only(['customer_id', 'brand_id', 'product_id', 'type', 'amount_paid']) + ['status' => 'pending'])
            ->assertSessionHasErrors('status');
        $this->assertEquals(2000, $source->fresh()->amount_paid);
    }

    public function test_failed_conversion_preserves_pre_order_deposit_and_stock(): void
    {
        [$source, $unit, $payload] = $this->fixture();
        foreach ([['money_transfer_amount' => 4000], ['customer_id' => null], ['pre_order_version' => 'stale'],
            ['cart' => [['product_id' => $unit->product_id, 'quantity' => 1]]]] as $change) {
            $this->post(route('pos.checkout'), array_replace($payload, $change))->assertSessionHasErrors();
            $this->assertNull($source->fresh()->converted_order_id);
            $this->assertSame('ordered', $source->fresh()->status);
            $this->assertSame('available', $unit->fresh()->status);
            $this->assertDatabaseCount('orders', 0);
        }
        $unit->update(['status' => 'sold']);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors('cart.0');
        $this->assertNull($source->fresh()->converted_order_id);
        $this->assertDatabaseCount('orders', 0);
        $unit->update(['status' => 'available']);
        $source->update(['amount_paid' => 20000]);
        $payload['money_transfer_amount'] = 20000;
        $payload['pre_order_version'] = $source->fresh()->updated_at->toISOString();
        $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors('money_transfer_amount');
        $this->assertNull($source->fresh()->converted_order_id);
        $this->assertSame('available', $unit->fresh()->status);
    }

    public function test_completion_accepts_only_remaining_payment_and_edit_cannot_remove_credit(): void
    {
        [$source, $unit, $payload] = $this->fixture();
        $this->post(route('pos.checkout'), array_replace($payload, ['order_type' => 'instock', 'status' => 'completed',
            'payments' => [['method' => 'cash', 'amount' => 8000]]]))->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertEquals(10000, (float) $order->amount_paid + (float) $order->money_transfer_amount);
        $this->assertSame('sold', $unit->fresh()->status);
        $edit = array_replace($payload, ['pre_order_id' => null, 'status' => 'completed', 'order_type' => 'instock',
            'edit_version' => $order->edit_version, 'money_transfer_amount' => 0]);
        $this->put(route('pos.orders.update', $order), $edit)->assertSessionHasErrors('pre_order_id');
        $this->assertEquals(2000, $order->fresh()->money_transfer_amount);
        $this->assertSame('sold', $unit->fresh()->status);
        $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'Customer cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('available', $unit->fresh()->status);
        $this->assertSame($order->id, $source->fresh()->converted_order_id);
    }

    public function test_unlinked_model_and_zero_deposit_can_be_converted(): void
    {
        [$source, $unit, $payload] = $this->fixture();
        $source->update(['product_id' => null, 'amount_paid' => 0]);
        $payload['pre_order_version'] = $source->fresh()->updated_at->toISOString();
        $payload['money_transfer_amount'] = 0;
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(0, Order::sole()->money_transfer_amount);
        $this->assertSame('reserved', $unit->fresh()->status);
    }

    public function test_cancelled_pre_order_and_wrong_model_cannot_be_converted(): void
    {
        [$source, $unit, $payload] = $this->fixture();
        $other = Product::create(['name' => 'Other', 'brand_id' => $source->brand_id, 'price' => 10000, 'currency' => 'MMK']);
        $otherUnit = $other->items()->create(['status' => 'available']);
        $this->post(route('pos.checkout'), array_replace($payload, ['cart' => [['product_id' => $other->id, 'item_id' => $otherUnit->id]]]))
            ->assertSessionHasErrors('cart');
        $source->update(['status' => 'cancelled']);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors('pre_order_id');
        $this->assertDatabaseCount('orders', 0);
    }
}
