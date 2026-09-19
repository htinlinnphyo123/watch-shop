<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use App\Models\WatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WatchRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-02-28 12:00:00'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function createRepair(array $overrides = [])
    {
        return $this->post(route('watch-services.store'), array_replace([
            'watch_origin' => 'external', 'service_type' => 'repair', 'external_name' => 'Customer-owned watch',
            'external_brand' => 'Example brand', 'external_model' => 'Model 12', 'external_serial' => 'OUTSIDE-123',
            'contact_name' => 'Walk-in Customer', 'contact_phone' => '091234567',
            'issue_date' => '2026-02-27', 'issue_description' => 'Broken movement after a drop.', 'fault_type' => 'human_damage',
            'billing_status' => 'chargeable', 'customer_charge' => '500.50', 'charge_notes' => 'Movement repair and parts.',
        ], $overrides));
    }

    private function updateRepair(WatchService $service, array $overrides = [])
    {
        return $this->put(route('watch-services.update', $service), array_replace([
            'status' => 'inspection', 'coverage_decision' => 'not_applicable', 'notes' => 'Inspection update.',
            'last_event_id' => $service->events()->max('id'),
        ], $overrides));
    }

    public function test_external_repairs_preserve_inventory_and_support_outsourcing_and_charge_history(): void
    {
        $products = Product::count();
        $units = ProductItem::count();
        $this->createRepair()->assertSessionHasNoErrors();
        $service = WatchService::sole();
        $this->assertNull($service->product_item_id);
        $this->assertNull($service->order_id);
        $this->assertSame('not_applicable', $service->coverage_decision);
        $this->assertSame('Customer-owned watch', $service->warranty_snapshot['product_name']);
        $this->assertSame('500.50', $service->customer_charge);
        $this->assertSame($products, Product::count());
        $this->assertSame($units, ProductItem::count());
        $this->createRepair(['external_serial' => 'OUTSIDE-456'])->assertSessionHasNoErrors();
        $this->assertSame(2, WatchService::count());
        $this->updateRepair($service, ['status' => 'sent_to_shop'])->assertSessionHasErrors('repair_provider');
        $this->updateRepair($service, ['repair_provider' => 'external'])->assertSessionHasErrors('external_shop_name');
        foreach (['sent_to_shop', 'external_repair', 'returned_from_shop', 'ready', 'completed'] as $status) {
            $this->updateRepair($service, ['status' => $status, 'repair_provider' => 'external', 'external_shop_name' => 'Partner Repair Shop', 'external_reference' => 'PARTNER-100', 'provider_notes' => 'Replace movement.'])->assertSessionHasNoErrors();
        }
        $this->assertSame('completed', $service->fresh()->status);
        $this->updateRepair($service, ['status' => 'completed', 'billing_status' => 'free', 'charge_notes' => 'Goodwill service.'])->assertSessionHasNoErrors();
        $this->assertSame('0.00', $service->fresh()->customer_charge);
        $this->assertEquals(500.50, $service->events()->oldest('id')->first()->repair_details['customer_charge']);
        $this->get(route('watch-services.index', ['origin' => 'external', 'search' => 'Customer-owned']))->assertInertia(fn (Assert $page) => $page->has('services.data', 2));
    }

    public function test_expenses_are_itemized_idempotent_and_voidable_without_erasing_history(): void
    {
        $this->createRepair()->assertSessionHasNoErrors();
        $service = WatchService::sole();
        $data = ['request_id' => (string) Str::uuid(), 'amount' => '200.25', 'expense_date' => '2026-02-28', 'description' => 'Partner repair fee', 'paid_to' => 'Partner Repair Shop'];
        $this->post(route('watch-services.expenses.store', $service), $data)->assertSessionHasNoErrors();
        $this->post(route('watch-services.expenses.store', $service), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $service->expenses()->count());
        $this->assertSame(2, $service->events()->count());
        $this->get(route('watch-services.show', $service))->assertInertia(fn (Assert $page) => $page->where('expenseTotal', fn ($value) => (float) $value === 200.25)->where('expectedMargin', 300.25));
        $expense = $service->expenses()->sole();
        $this->patch(route('watch-services.expenses.void', [$service, $expense]), ['void_reason' => 'Duplicate invoice entry.'])->assertSessionHasNoErrors();
        $this->patch(route('watch-services.expenses.void', [$service, $expense]), ['void_reason' => 'Duplicate invoice entry.'])->assertSessionHasNoErrors();
        $this->assertSame(1, $service->expenses()->count());
        $this->assertNotNull($expense->fresh()->voided_at);
        $this->assertSame(3, $service->events()->count());
        $this->get(route('watch-services.show', $service))->assertInertia(fn (Assert $page) => $page->where('expenseTotal', fn ($value) => (float) $value === 0.0)->where('expectedMargin', 500.5));
        $this->createRepair()->assertSessionHasNoErrors();
        $other = WatchService::latest('id')->first();
        $this->patch(route('watch-services.expenses.void', [$other, $expense]), ['void_reason' => 'Wrong job'])->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->post(route('watch-services.expenses.store', $service), $data)->assertForbidden();
        $this->patch(route('watch-services.expenses.void', [$service, $expense]), ['void_reason' => 'Denied'])->assertForbidden();
    }

    public function test_invalid_amounts_and_missing_watch_details_are_rejected(): void
    {
        $this->createRepair(['external_name' => ''])->assertSessionHasErrors('external_name');
        $this->createRepair(['customer_charge' => '-1'])->assertSessionHasErrors('customer_charge');
        $this->createRepair(['customer_charge' => '10.001'])->assertSessionHasErrors('customer_charge');
        $this->createRepair(['charge_notes' => ''])->assertSessionHasErrors('charge_notes');
        $this->createRepair(['billing_status' => 'pending', 'customer_charge' => null])->assertSessionHasNoErrors();
        $service = WatchService::sole();
        $this->assertNull($service->customer_charge);
        $data = ['request_id' => (string) Str::uuid(), 'amount' => 0, 'expense_date' => '2026-02-28', 'description' => 'Invalid expense'];
        $this->post(route('watch-services.expenses.store', $service), $data)->assertSessionHasErrors('amount');
        $this->post(route('watch-services.expenses.store', $service), array_replace($data, ['amount' => 10, 'expense_date' => '2026-02-26']))->assertSessionHasErrors('expense_date');
        $this->assertSame(0, $service->expenses()->count());
    }
}
