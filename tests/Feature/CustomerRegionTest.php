<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerRegionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    public function test_all_regions_can_be_saved_for_regular_and_pos_guest_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (Customer::REGIONS as $index => $region) {
            $email = "customer{$index}@example.test";
            $this->post(route('customers.store'), [
                'name' => 'Regular', 'email' => $email, 'region' => $region, 'address' => 'Main Street',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('customers', [
                'email' => $email, 'region' => $region, 'address' => 'Main Street', 'is_guest' => false,
            ]);
            $this->postJson(route('pos.customers.store'), [
                'name' => 'Guest', 'phone' => '091234'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'region' => $region, 'address' => 'Guest Street',
            ])->assertCreated()->assertJsonPath('customer.region', $region)
                ->assertJsonPath('customer.address', 'Guest Street')->assertJsonPath('customer.is_guest', true);
        }
    }

    public function test_region_can_be_updated_cleared_and_omitted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('customers.store'), ['name' => 'Regular', 'email' => 'regular@example.test'])
            ->assertSessionHasNoErrors();
        $customer = Customer::sole();
        $this->assertNull($customer->region);
        $this->put(route('customers.update', $customer), ['name' => 'Regular', 'region' => 'Yangon Region'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Yangon Region', $customer->fresh()->region);
        $this->put(route('customers.update', $customer), ['name' => 'Regular'])->assertSessionHasNoErrors();
        $this->assertSame('Yangon Region', $customer->fresh()->region);
        $this->put(route('customers.update', $customer), ['name' => 'Regular', 'region' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->region);
        $this->postJson(route('pos.customers.store'), ['name' => 'Guest', 'phone' => '09111', 'region' => ''])
            ->assertCreated();
        $this->assertNull(Customer::where('phone', '09111')->firstOrFail()->region);
    }

    public function test_invalid_regions_are_rejected_on_all_customer_write_endpoints(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $customer = Customer::create(['name' => 'Existing', 'region' => 'Mon State']);
        foreach (['Unknown Region', ['Yangon Region']] as $invalid) {
            $this->post(route('customers.store'), [
                'name' => 'Regular', 'email' => 'invalid@example.test', 'region' => $invalid,
            ])->assertSessionHasErrors('region');
            $this->put(route('customers.update', $customer), ['name' => 'Existing', 'region' => $invalid])
                ->assertSessionHasErrors('region');
            $this->postJson(route('pos.customers.store'), [
                'name' => 'Guest', 'phone' => '09222', 'region' => $invalid,
            ])->assertUnprocessable()->assertJsonValidationErrors('region');
        }
        $this->assertSame('Mon State', $customer->fresh()->region);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_forms_share_regions_and_guest_promotion_preserves_region(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $response = $this->postJson(route('pos.customers.store'), [
            'name' => 'Returning guest', 'phone' => '09333', 'region' => 'NayPyiTaw',
        ])->assertCreated();
        $customer = Customer::findOrFail($response->json('customer.id'));
        $this->get(route('pos.index'))->assertInertia(fn (Assert $page) => $page
            ->where('customerRegions', Customer::REGIONS)
            ->where('customers.0.region', 'NayPyiTaw'));
        $this->patchJson(route('pos.customers.promote', $customer))->assertOk()
            ->assertJsonPath('customer.is_guest', false)->assertJsonPath('customer.region', 'NayPyiTaw');
        $this->get(route('customers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('regionOptions', Customer::REGIONS)
            ->where('customers.data.0.region', 'NayPyiTaw'));
    }
}
