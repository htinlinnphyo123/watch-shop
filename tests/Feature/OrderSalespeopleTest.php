<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderAudit;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use App\Services\SalesWorkbookExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderSalespeopleTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    private function payload(array $overrides = []): array
    {
        $brand = Brand::create(['name' => 'Sales test']);
        $product = Product::create(['name' => 'Watch', 'brand_id' => $brand->id, 'price' => 1000, 'currency' => 'MMK']);
        ProductItem::create(['product_id' => $product->id, 'status' => 'available']);

        return array_replace([
            'payments' => [['method' => 'cash', 'amount' => 1000]],
            'cart' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $overrides);
    }

    public function test_checkout_assigns_multiple_people_independently_of_creator_and_retries_once(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $people = User::factory()->count(2)->create(['role' => 'staff']);
        $this->actingAs($creator);
        $payload = $this->payload(['salesperson_ids' => $people->modelKeys(), 'checkout_request_id' => (string) Str::uuid()]);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame($creator->id, $order->user_id);
        $this->assertSame($people->modelKeys(), $order->salespeople->modelKeys());
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_salespeople', 2);
        $this->assertSame($order->id, $people[0]->salesOrders()->sole()->id);
        $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page->has('order.salespeople', 2));
        $this->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page->has('orders.data.0.salespeople', 2));
        $this->get(route('pos.index', ['order_id' => $order->id]))->assertInertia(fn (Assert $page) => $page->where('editingOrder.salesperson_ids', $people->modelKeys())->has('salespeople', 3));
        $row = app(SalesWorkbookExport::class)->saleRow($order);
        $this->assertContains($people->pluck('name')->implode(', '), $row);
    }

    public function test_edit_replaces_assignments_records_audit_and_omitted_field_preserves_them(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $people = User::factory()->count(2)->create(['role' => 'staff']);
        $this->actingAs($creator);
        $payload = $this->payload();
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame([$creator->id], $order->salespeople->modelKeys());
        $this->put(route('pos.orders.update', $order), $payload + ['edit_version' => 0, 'salesperson_ids' => $people->modelKeys()])->assertSessionHasNoErrors();
        $this->assertSame($people->modelKeys(), $order->fresh()->salespeople->modelKeys());
        $this->assertSame($creator->id, $order->fresh()->user_id);
        $audit = OrderAudit::where('event', 'edited')->latest('id')->first();
        $this->assertCount(2, $audit->snapshot['salespeople']);
        $this->assertCount(3, collect($audit->changes)->where('section', 'Salespeople'));
        $this->put(route('pos.orders.update', $order), $payload + ['edit_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame($people->modelKeys(), $order->fresh()->salespeople->modelKeys());
        $this->put(route('pos.orders.update', $order), array_replace($payload, ['edit_version' => 2, 'salesperson_ids' => [$creator->id], 'payments' => [['method' => 'cash', 'amount' => 1]]]))->assertSessionHasErrors('payments');
        $this->assertSame($people->modelKeys(), $order->fresh()->salespeople->modelKeys());
    }

    public function test_invalid_and_duplicate_selections_are_rejected_before_stock_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customerAccount = User::factory()->create(['role' => 'user']);
        $this->actingAs($admin);
        $payload = $this->payload();
        foreach ([[], null, [$admin->id, $admin->id], [999999], [$customerAccount->id]] as $ids) {
            $this->post(route('pos.checkout'), $payload + ['salesperson_ids' => $ids])->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('available', ProductItem::sole()->status);
        $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->has('salespeople', 1)->where('salespeople.0.id', $admin->id));
    }

    public function test_performance_gives_full_credit_without_multiplying_shop_totals(): void
    {
        $people = User::factory()->count(2)->create(['role' => 'staff']);
        foreach ([['completed', 100], ['completed', 50], ['pending', 900], ['cancelled', 900]] as $i => [$status, $amount]) {
            $order = Order::create(['order_number' => 'PERF-'.$i, 'status' => $status, 'total_amount' => $amount, 'created_at' => '2026-10-01']);
            $order->salespeople()->sync($i === 1 ? [$people[0]->id] : $people->modelKeys());
        }
        $outside = Order::create(['order_number' => 'OUTSIDE', 'status' => 'completed', 'total_amount' => 999, 'created_at' => '2026-09-30']);
        $outside->salespeople()->sync($people->modelKeys());
        Order::create(['order_number' => 'UNASSIGNED', 'status' => 'completed', 'total_amount' => 25, 'created_at' => '2026-10-01']);
        $report = app(SalesAnalyticsService::class)->report(['from' => '2026-10-01', 'to' => '2026-10-01']);
        $this->assertEquals(175, $report['summary']['total_sales']);
        $this->assertSame(3, $report['summary']['completed_orders']);
        $performance = $report['salespeople']->keyBy('id');
        $this->assertEquals(150, $performance[$people[0]->id]->sales);
        $this->assertEquals(2, $performance[$people[0]->id]->orders);
        $this->assertEquals(100, $performance[$people[1]->id]->sales);
        $this->assertEquals(1, $performance[$people[1]->id]->orders);
    }

    public function test_staff_can_assign_colleagues_without_granting_them_edit_access(): void
    {
        $creator = User::factory()->create(['role' => 'staff']);
        $colleague = User::factory()->create(['role' => 'staff']);
        $this->actingAs($creator);
        $payload = $this->payload(['salesperson_ids' => [$creator->id, $colleague->id]]);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->actingAs($colleague);
        $this->get(route('pos.index', ['order_id' => $order->id]))->assertForbidden();
        $this->put(route('pos.orders.update', $order), $payload + ['edit_version' => 0])->assertForbidden();
        $this->assertCount(2, $order->fresh()->salespeople);
    }

    public function test_assigned_users_cannot_be_deleted_through_admin_or_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $person = User::factory()->create(['role' => 'staff']);
        $order = Order::create(['order_number' => 'KEEP-HISTORY', 'status' => 'completed', 'total_amount' => 100]);
        $order->salespeople()->attach($person);
        $this->actingAs($admin)->delete(route('users.destroy', $person))->assertSessionHasErrors('error');
        $this->actingAs($person)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertAuthenticatedAs($person);
        $this->assertModelExists($person);
        $this->assertDatabaseCount('order_salespeople', 1);
    }

    public function test_migration_backfills_existing_orders_and_leaves_unowned_orders_unassigned(): void
    {
        $person = User::factory()->create(['role' => 'staff']);
        $order = Order::create(['user_id' => $person->id, 'order_number' => 'LEGACY', 'status' => 'completed', 'total_amount' => 100]);
        Order::create(['order_number' => 'ONLINE', 'status' => 'pending', 'total_amount' => 100]);
        $migration = require database_path('migrations/2026_10_11_000000_create_order_salespeople_table.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('order_salespeople', 1);
        $this->assertDatabaseHas('order_salespeople', ['order_id' => $order->id, 'user_id' => $person->id]);
        $this->assertEquals($order->created_at, DB::table('order_salespeople')->value('created_at'));
    }
}
