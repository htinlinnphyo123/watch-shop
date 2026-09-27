<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    public function test_customer_phone_is_unique_on_create_and_update_including_archived_customers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['name' => 'Mg Mg', 'email' => 'mg@example.test', 'phone' => '093223'];
        $this->post(route('customers.store'), $data)->assertSessionHasNoErrors();
        $customer = Customer::sole();
        $this->put(route('customers.update', $customer), $data)->assertSessionHasNoErrors();
        $this->post(route('customers.store'), array_replace($data, ['email' => 'duplicate@example.test']))->assertSessionHasErrors('phone');
        $other = Customer::create(['name' => 'Other', 'email' => 'other@example.test', 'phone' => '094444']);
        $this->put(route('customers.update', $other), ['name' => $other->name, 'email' => $other->email, 'phone' => '093223'])->assertSessionHasErrors('phone');
        $this->assertSame('094444', $other->fresh()->phone);
        $customer->delete();
        $this->post(route('customers.store'), array_replace($data, ['email' => 'archived@example.test']))->assertSessionHasErrors('phone');
        $this->assertSame(2, Customer::withTrashed()->count());
    }

    public function test_optional_phones_and_pos_customer_details(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach (['one', 'two'] as $name) {
            $this->post(route('customers.store'), ['name' => $name, 'email' => "$name@example.test", 'phone' => ''])->assertSessionHasNoErrors();
        }
        $customer = Customer::create(['name' => 'Mg Mg', 'email' => 'mg@example.test', 'phone' => '093223']);
        $this->assertSame(2, Customer::whereNull('phone')->count());
        $this->get(route('pos.index'))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('customers', 3)
            ->where('customers', fn ($customers) => collect($customers)->contains(fn ($item) => $item['id'] === $customer->id
                && $item['name'] === 'Mg Mg' && $item['phone'] === '093223' && $item['email'] === 'mg@example.test')));
    }

    public function test_database_rejects_duplicate_phone_even_without_controller_validation(): void
    {
        Customer::create(['name' => 'First', 'email' => 'first@example.test', 'phone' => '093223']);
        $this->expectException(QueryException::class);
        Customer::create(['name' => 'Second', 'email' => 'second@example.test', 'phone' => '093223']);
    }

    public function test_migration_refuses_existing_duplicates_without_changing_customers(): void
    {
        $migration = require database_path('migrations/2026_09_27_000002_add_unique_customer_phone.php');
        $migration->down();
        Customer::create(['name' => 'First', 'email' => 'first@example.test', 'phone' => '093223']);
        Customer::create(['name' => 'Second', 'email' => 'second@example.test', 'phone' => '093223']);
        try {
            $migration->up();
            $this->fail('Migration must not silently modify duplicate customer records.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate customer phone numbers', $exception->getMessage());
        }
        $this->assertSame(2, Customer::where('phone', '093223')->count());
    }
}
