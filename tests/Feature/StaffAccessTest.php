<?php

namespace Tests\Feature;

use App\Models\{Brand, LowStockNotification, Order, Product, User};
use App\Services\LowStockNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    public function test_staff_can_access_operations_but_not_inventory_or_analytics(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/dashboard')->assertRedirect(route('pos.index'));
        foreach (['wallet.index', 'customers.index', 'pos.index', 'orders.index', 'low-stock-notifications.index', 'watch-services.index', 'pre-orders.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['products.index', 'categories.index', 'brands.index', 'collections.index', 'banners.index', 'articles.index', 'accessories.index', 'users.index', 'attendance.index', 'orders.summary', 'sales.analytics', 'customers.leaderboard'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('products.store'), [])->assertForbidden();
        $this->post(route('categories.store'), [])->assertForbidden();
        $this->post(route('customers.store'), ['name' => 'Customer', 'email' => 'customer@example.test'])->assertSessionHasNoErrors();
    }

    public function test_staff_only_access_their_orders_including_pos_and_file_endpoints(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $own = Order::create(['user_id' => $staff->id, 'order_number' => 'OWN', 'status' => 'pending', 'total_amount' => 100]);
        $other = Order::create(['user_id' => $admin->id, 'order_number' => 'OTHER', 'status' => 'pending', 'total_amount' => 100]);
        $this->actingAs($staff);
        $this->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $own->id));
        $this->get(route('orders.show', $own))->assertOk();
        foreach (['orders.show', 'orders.history'] as $route) {
            $this->get(route($route, $other))->assertForbidden();
        }
        foreach (['orders.cancel', 'orders.approve', 'orders.files.presign'] as $route) {
            $this->post(route($route, $other), [])->assertForbidden();
        }
        $this->put(route('pos.orders.update', $other), [])->assertForbidden();
        foreach (['pos.index', 'pos.products', 'pos.products.scan'] as $route) {
            $this->get(route($route, ['order_id' => $other->id]))->assertForbidden();
        }
        $this->actingAs($admin)->get(route('orders.show', $own))->assertOk();
    }

    public function test_staff_wallet_is_scoped_and_cannot_edit_another_wallet(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create(['role' => 'staff']);
        $transaction = $other->wallet->transactions()->create(['created_by' => $other->id, 'type' => 'debit', 'amount' => 10, 'balance_after' => -10]);
        $this->actingAs($staff)->get(route('wallet.index', ['user_id' => $other->id]))
            ->assertInertia(fn (Assert $page) => $page->where('currentWallet.user_id', $staff->id)->has('transactions.data', 0));
        $this->put(route('wallet.transactions.update', $transaction), ['type' => 'debit', 'amount' => 20])->assertForbidden();
        $this->delete(route('wallet.transactions.destroy', $transaction))->assertForbidden();
        $this->post(route('wallet.transactions.store'), ['user_id' => $other->id, 'type' => 'debit', 'amount' => 25])->assertSessionHasNoErrors();
        $this->assertSame(1, $staff->wallet->transactions()->count());
        $this->assertSame(1, $other->wallet->transactions()->count());
    }

    public function test_admin_updates_ordered_count_independently_and_stock_sync_preserves_it(): void
    {
        $brand = Brand::create(['name' => 'Test']);
        $product = Product::create(['name' => 'Watch', 'brand_id' => $brand->id, 'price' => 100, 'priority_level' => 3, 'low_stock_alert_count' => 5]);
        $service = app(LowStockNotificationService::class);
        $notification = $service->sync($product)->fresh();
        $this->assertSame(0, $notification->ordered_count);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('low-stock-notifications.update', $notification), ['ordered_count' => 12])->assertSessionHasNoErrors();
        $this->assertSame('pending', $notification->fresh()->status);
        $this->assertSame(12, $service->sync($product)->fresh()->ordered_count);
        foreach ([-1, 1.5, null, 2147483648] as $invalid) {
            $this->patch(route('low-stock-notifications.update', $notification), ['ordered_count' => $invalid])->assertSessionHasErrors('ordered_count');
        }
        $this->patch(route('low-stock-notifications.update', $notification), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->assertSame(12, $notification->fresh()->ordered_count);
        $this->assertSame(0, $product->items()->count());
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->patch(route('low-stock-notifications.update', $notification), ['ordered_count' => 20])->assertForbidden();
    }

    public function test_admin_user_forms_accept_staff_manager_admin_and_reject_customer_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['staff', 'manager', 'admin'] as $role) {
            $data = ['name' => $role, 'email' => "$role@example.test", 'role' => $role, 'password' => 'password', 'password_confirmation' => 'password'];
            $this->post(route('users.store'), $data)->assertSessionHasNoErrors();
            $user = User::where('email', $data['email'])->firstOrFail();
            $this->put(route('users.update', $user), array_replace($data, ['role' => 'staff']))->assertSessionHasNoErrors();
            $this->put(route('users.update', $user), array_replace($data, ['role' => 'user']))->assertSessionHasErrors('role');
        }
        $this->post(route('users.store'), ['name' => 'Normal', 'email' => 'normal@example.test', 'role' => 'user', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('role');
    }
}
