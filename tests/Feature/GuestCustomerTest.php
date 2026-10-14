<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuestCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
    }

    public function test_minimal_guest_is_hidden_from_list_but_available_in_pos_and_can_be_promoted(): void
    {
        $response = $this->postJson(route('pos.customers.store'), ['name' => 'One visit', 'phone' => '09 123-456'])->assertCreated();
        $guest = Customer::findOrFail($response->json('customer.id'));
        $this->assertTrue($guest->is_guest);
        $this->assertNull($guest->email);
        $this->assertTrue(Hash::check('password', $guest->password));
        $this->assertSame('09123456', $guest->phone);
        $this->get(route('customers.index'))->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
        $this->get(route('customers.index', ['show_guests' => 1]))->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
        $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page->where('customers.0.id', $guest->id)->where('customers.0.is_guest', true));
        $order = Order::create(['user_id' => auth()->id(), 'customer_id' => $guest->id, 'order_number' => 'GUEST-OLD', 'status' => 'completed', 'total_amount' => 100]);
        $this->patchJson(route('pos.customers.promote', $guest), ['name' => 'Must not change'])->assertOk()->assertJsonPath('customer.is_guest', false);
        $this->assertSame('One visit', $guest->fresh()->name);
        $this->assertSame($guest->id, $order->fresh()->customer_id);
        $this->assertDatabaseCount('customers', 1);
        $this->get(route('customers.index'))->assertInertia(fn (Assert $page) => $page->where('customers.data.0.id', $guest->id));
        $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.customer.name', 'One visit'));
        $this->patchJson(route('pos.customers.promote', $guest))->assertOk();
    }

    public function test_all_optional_details_are_saved_and_password_is_hashed_and_hidden(): void
    {
        $group = CustomerGroup::create(['name' => 'VIP', 'percentage' => 10]);
        $response = $this->postJson(route('pos.customers.store'), [
            'name' => 'Guest', 'phone' => '+95 (9) 987-654', 'email' => 'guest@example.test', 'password' => 'secret123',
            'customer_group_id' => $group->id, 'address' => 'Yangon', 'gender' => 'woman', 'source' => 'referral', 'source_details' => 'Aung',
            'is_guest' => false, 'id' => 999,
        ])->assertCreated()->assertJsonPath('customer.group', null)->assertJsonPath('customer.customer_group_id', null)->assertJsonMissingPath('customer.password');
        $guest = Customer::findOrFail($response->json('customer.id'));
        $this->assertTrue(Hash::check('password', $guest->password));
        $this->assertTrue($guest->is_guest);
        $this->assertSame('09987654', $guest->phone);
        $this->assertSame('Yangon', $guest->address);
        $this->assertSame('woman', $guest->gender);
        $this->assertSame('referral', $guest->source);
        $this->assertSame('Aung', $guest->source_details);
    }

    public function test_guest_checkout_keeps_profile_without_group_discount_and_counts_in_sales_reporting(): void
    {
        $group = CustomerGroup::create(['name' => 'VIP', 'percentage' => 10]);
        $response = $this->postJson(route('pos.customers.store'), ['name' => 'Guest buyer', 'phone' => '095555', 'customer_group_id' => $group->id])->assertCreated();
        $customerId = $response->json('customer.id');
        $brand = \App\Models\Brand::create(['name' => 'Guest watch']);
        $product = \App\Models\Product::create(['name' => 'Watch', 'brand_id' => $brand->id, 'price' => 1000, 'currency' => 'MMK']);
        \App\Models\ProductItem::create(['product_id' => $product->id, 'status' => 'available']);
        $this->post(route('pos.checkout'), [
            'customer_id' => $customerId,
            'cart' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame($customerId, $order->customer_id);
        $this->assertSame('1000.00', $order->total_amount);
        $this->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.customer.name', 'Guest buyer')->where('order.customer.phone', '095555'));
        $report = app(\App\Services\SalesAnalyticsService::class)->report(['from' => now()->toDateString(), 'to' => now()->toDateString()]);
        $this->assertEquals(1000, $report['summary']['total_sales']);
        $this->assertSame(1, $report['summary']['completed_orders']);
    }

    public function test_formatted_phone_or_email_matches_require_explicit_selection_without_overwriting(): void
    {
        $group = CustomerGroup::create(['name' => 'VIP', 'percentage' => 10]);
        $existing = Customer::create(['name' => 'Existing', 'phone' => '09 123-456', 'email' => 'old@example.test', 'customer_group_id' => $group->id]);
        foreach ([['phone' => '+959123456'], ['phone' => '00959123456'], ['phone' => '099999', 'email' => 'old@example.test']] as $fields) {
            $this->postJson(route('pos.customers.store'), ['name' => 'Different'] + $fields)->assertStatus(409)
                ->assertJsonPath('matches.0.id', $existing->id)->assertJsonPath('matches.0.group.id', $group->id);
        }
        $this->assertDatabaseCount('customers', 1);
        $this->assertSame('Existing', $existing->fresh()->name);
        $existing->delete();
        $this->postJson(route('pos.customers.store'), ['name' => 'Archived match', 'phone' => '09123456'])->assertStatus(409);
        $this->patchJson(route('pos.customers.promote', $existing))->assertNotFound();
    }

    public function test_required_fields_and_optional_validation_and_guest_only_creation(): void
    {
        $this->postJson(route('pos.customers.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
        $this->postJson(route('pos.customers.store'), ['name' => 'Guest', 'phone' => ['invalid']])->assertUnprocessable();
        $this->postJson(route('pos.customers.store'), ['name' => 'Guest', 'phone' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson(route('pos.customers.store'), ['name' => 'Guest', 'phone' => '091', 'email' => 'invalid', 'password' => 'x', 'gender' => 'bad', 'source' => 'bad', 'customer_group_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'gender', 'source']);
        $this->post(route('customers.store'), ['name' => 'Regular', 'email' => 'regular@example.test', 'is_guest' => true])->assertSessionHasNoErrors();
        $this->assertFalse(Customer::sole()->is_guest);
        $this->postJson(route('pos.customers.store'), ['name' => 'Guest A', 'phone' => '091'])->assertCreated();
        $this->postJson(route('pos.customers.store'), ['name' => 'Guest B', 'phone' => '092'])->assertCreated();
        $this->assertSame(2, Customer::whereNull('email')->count());
    }

    public function test_promoted_customer_without_email_can_be_edited_and_guests_cannot_access_endpoints(): void
    {
        $guest = Customer::create(['name' => 'Guest', 'phone' => '09333', 'is_guest' => true]);
        $this->patchJson(route('pos.customers.promote', $guest))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('customers.update', $guest), ['name' => 'Returning customer', 'phone' => '09333'])->assertSessionHasNoErrors();
        $this->assertSame('Returning customer', $guest->fresh()->name);
        auth()->logout();
        $this->postJson(route('pos.customers.store'), ['name' => 'No login', 'phone' => '09444'])->assertUnauthorized();
        $this->patchJson(route('pos.customers.promote', $guest))->assertUnauthorized();
    }
}
