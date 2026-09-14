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

class PreOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    private function payload(): array
    {
        return [
            'customer_id' => Customer::create(['name' => 'Pre-order Customer', 'phone' => '091234567', 'email' => 'preorder@example.test'])->id,
            'brand_id' => Brand::create(['name' => 'Requested Brand'])->id,
            'watch_details' => 'Blue dial, reference ABC',
            'amount_paid' => '150000.50',
            'remark' => 'Deposit received. Awaiting stock.',
            'status' => 'pending',
        ];
    }

    public function test_admin_can_create_and_list_a_pre_order_without_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->payload();
        $this->actingAs($admin)->post(route('pre-orders.store'), $payload + ['user_id' => 999])
            ->assertSessionHasNoErrors()->assertRedirect(route('pre-orders.index'));
        $this->assertDatabaseHas('pre_orders', $payload + ['user_id' => $admin->id]);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_items', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('pre-orders.index'))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->has('preOrders.data', 1)
            ->where('preOrders.data.0.amount_paid', '150000.50')
            ->where('preOrders.data.0.brand.name', 'Requested Brand')
            ->where('preOrders.data.0.customer.name', 'Pre-order Customer')
            ->where('preOrders.data.0.remark', $payload['remark']));
    }

    public function test_admin_can_update_total_paid_and_remark_without_changing_creator(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $editor = User::factory()->create(['role' => 'staff']);
        $payload = $this->payload();
        $preOrder = PreOrder::create($payload + ['user_id' => $creator->id]);
        $payload['amount_paid'] = '200000.75';
        $payload['remark'] = 'Additional payment received.';
        $this->actingAs($editor)->put(route('pre-orders.update', $preOrder), $payload + ['user_id' => $editor->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('pre_orders', $payload + ['id' => $preOrder->id, 'user_id' => $creator->id]);
    }

    public function test_staff_can_manage_pre_orders_but_guests_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->payload();
        $preOrder = PreOrder::create($payload + ['user_id' => $admin->id]);
        $this->get(route('pre-orders.index'))->assertRedirect(route('login'));
        $this->post(route('pre-orders.store'), $payload)->assertRedirect(route('login'));
        $this->put(route('pre-orders.update', $preOrder), $payload)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('pre-orders.index'))->assertOk();
        $this->post(route('pre-orders.store'), $payload + ['user_id' => $admin->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(auth()->id(), PreOrder::latest('id')->first()->user_id);
        $this->put(route('pre-orders.update', $preOrder), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('pre_orders', 2);
    }

    public function test_invalid_references_and_payment_amounts_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = $this->payload();
        foreach (['-1', '1.001', 'abc', '10000000000000'] as $amount) {
            $this->post(route('pre-orders.store'), array_replace($payload, ['amount_paid' => $amount]))
                ->assertSessionHasErrors('amount_paid');
        }
        $this->post(route('pre-orders.store'), array_replace($payload, [
            'brand_id' => 999999, 'customer_id' => 999999, 'remark' => str_repeat('a', 5001),
        ]))->assertSessionHasErrors(['brand_id', 'customer_id', 'remark']);
        $this->assertDatabaseCount('pre_orders', 0);
    }

    public function test_archived_brand_and_customer_remain_visible_and_editable_on_existing_pre_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->payload();
        $preOrder = PreOrder::create($payload + ['user_id' => $admin->id]);
        Brand::findOrFail($payload['brand_id'])->delete();
        Customer::findOrFail($payload['customer_id'])->delete();
        $this->actingAs($admin)->get(route('pre-orders.index'))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->has('brands', 0)->has('customers', 0)
            ->where('preOrders.data.0.brand.name', 'Requested Brand')
            ->where('preOrders.data.0.customer.name', 'Pre-order Customer'));
        $this->put(route('pre-orders.update', $preOrder), array_replace($payload, ['amount_paid' => '0']))
            ->assertSessionHasNoErrors();
        $this->post(route('pre-orders.store'), $payload)->assertSessionHasErrors(['brand_id', 'customer_id']);
    }

    public function test_linked_product_stock_updates_and_excludes_unavailable_items(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $payload = $this->payload();
        $product = Product::create(['brand_id' => $payload['brand_id'], 'name' => 'Existing Watch', 'price' => 30000, 'currency' => 'MMK']);
        $payload['product_id'] = $product->id;
        $this->actingAs($user)->post(route('pre-orders.store'), $payload)->assertSessionHasNoErrors();
        $this->get(route('pre-orders.index'))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->where('preOrders.data.0.product.available_stock', 0)
            ->where('preOrders.data.0.product_id', $product->id)->has('products', 1));
        foreach (['available', 'reserved', 'sold', 'available'] as $index => $status) {
            $item = ProductItem::create(['product_id' => $product->id, 'system_unique_id' => 'PRE-STOCK-'.$index, 'status' => $status]);
            if ($index === 3) {
                $item->delete();
            }
        }
        $this->get(route('pre-orders.index'))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->where('preOrders.data.0.product.available_stock', 1));
        $preOrder = PreOrder::sole();
        foreach (['ordered', 'sold_out', 'pending'] as $status) {
            $this->put(route('pre-orders.update', $preOrder), array_replace($payload, ['status' => $status]))->assertSessionHasNoErrors();
            $this->assertSame($status, $preOrder->fresh()->status);
        }
        $this->put(route('pre-orders.update', $preOrder), array_replace($payload, ['product_id' => null]))->assertSessionHasNoErrors();
        $this->assertNull($preOrder->fresh()->product_id);
        $this->assertSame('available', ProductItem::where('system_unique_id', 'PRE-STOCK-0')->first()->status);
    }

    public function test_invalid_status_and_products_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $payload = $this->payload();
        $otherBrand = Brand::create(['name' => 'Other Brand']);
        $product = Product::create(['brand_id' => $otherBrand->id, 'name' => 'Wrong Brand Watch', 'price' => 30000, 'currency' => 'MMK']);
        foreach ([999999, $product->id] as $id) {
            $this->post(route('pre-orders.store'), $payload + ['product_id' => $id])->assertSessionHasErrors('product_id');
        }
        $this->post(route('pre-orders.store'), array_replace($payload, ['status' => 'invalid']))->assertSessionHasErrors('status');
        $product->update(['brand_id' => $payload['brand_id']]);
        $product->delete();
        $this->post(route('pre-orders.store'), $payload + ['product_id' => $product->id])->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('pre_orders', 0);
    }

    public function test_filters_work_individually_together_and_across_pagination(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->actingAs($user);
        $payload = $this->payload();
        $product = Product::create(['brand_id' => $payload['brand_id'], 'name' => 'Filter Watch', 'price' => 100, 'currency' => 'MMK']);
        $matching = $payload + ['user_id' => $user->id, 'product_id' => $product->id];
        $matching['status'] = 'ordered';
        for ($i = 0; $i < 21; $i++) {
            PreOrder::create($matching);
        }
        PreOrder::create([
            'user_id' => $user->id,
            'customer_id' => Customer::create(['name' => 'Other', 'email' => 'other@example.test'])->id,
            'brand_id' => Brand::create(['name' => 'Other'])->id,
            'status' => 'pending', 'amount_paid' => 0,
        ]);
        $filters = array_intersect_key($matching, array_flip(['status', 'brand_id', 'customer_id', 'product_id']));
        foreach ($filters as $field => $value) {
            $this->get(route('pre-orders.index', [$field => $value]))->assertInertia(fn (Assert $page) => $page
                ->component('PreOrders/Index')->where('preOrders.total', 21));
        }
        $this->get(route('pre-orders.index', $filters))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->where('preOrders.total', 21)->has('preOrders.data', 20)
            ->where('preOrders.next_page_url', function ($url) use ($filters) {
                parse_str(parse_url($url, PHP_URL_QUERY), $query);
                foreach ($filters as $key => $value) {
                    if ((string) ($query[$key] ?? '') !== (string) $value) {
                        return false;
                    }
                }

                return $query['page'] === '2';
            }));
        $this->get(route('pre-orders.index', $filters + ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->has('preOrders.data', 1));
        $this->get(route('pre-orders.index', array_replace($filters, ['status' => 'sold_out'])))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->where('preOrders.total', 0));
        $this->get(route('pre-orders.index'))->assertInertia(fn (Assert $page) => $page
            ->component('PreOrders/Index')->where('preOrders.total', 22));
    }
}
