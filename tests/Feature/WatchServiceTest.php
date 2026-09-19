<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use App\Models\WatchService;
use App\Services\WatchWarrantyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductItem $unit;
    private Product $watch;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run only with DB_CONNECTION=sqlite DB_DATABASE=:memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-02-28 12:00:00'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $brand = Brand::create(['name' => 'Service brand']);
        $this->watch = Product::create(['brand_id' => $brand->id, 'name' => 'Warranty Watch', 'kind' => 'watch', 'price' => 100, 'warranty_period' => 1]);
        $customer = Customer::create(['name' => 'Warranty Customer', 'email' => 'warranty@example.test']);
        $order = Order::create(['customer_id' => $customer->id, 'order_number' => 'WARRANTY-TEST', 'status' => 'completed', 'total_amount' => 100, 'created_at' => '2026-01-31 12:00:00']);
        $line = $order->items()->create(['product_id' => $this->watch->id, 'quantity' => 1, 'price' => 100]);
        $this->unit = ProductItem::create(['product_id' => $this->watch->id, 'order_item_id' => $line->id, 'system_unique_id' => '123456789012', 'serial_number' => 'SERIAL-SERVICE-1', 'status' => 'sold', 'purchase_date' => '2020-01-01']);
    }

    private function intake(array $overrides = [])
    {
        return $this->post(route('watch-services.store'), array_replace([
            'service_type' => 'warranty', 'product_item_id' => $this->unit->id, 'contact_name' => 'Warranty Customer',
            'issue_date' => '2026-02-28', 'issue_description' => 'Watch stops intermittently.', 'notes' => 'Receipt presented.',
        ], $overrides));
    }

    public function test_warranty_uses_customer_sale_date_handles_month_end_and_missing_proof(): void
    {
        $checker = app(WatchWarrantyService::class);
        $check = $checker->check($this->unit);
        $this->assertSame('2026-01-31', $check['purchase_date']);
        $this->assertSame('2026-02-28', $check['expires_on']);
        $this->assertSame('within_period', $check['result']);
        $this->assertSame('expired', $checker->check($this->unit, '2026-03-01')['result']);
        $this->assertSame('unverified', $checker->check($this->unit, '2026-01-30')['result']);
        $this->watch->update(['warranty_period' => 0]);
        $this->assertSame('no_warranty', $checker->check($this->unit)['result']);
        $this->unit->update(['order_item_id' => null]);
        $this->assertSame('unverified', $checker->check($this->unit)['result']);
        $this->assertNull($checker->check($this->unit)['purchase_date']);
    }

    public function test_intake_status_history_snapshots_and_duplicate_open_cases(): void
    {
        $this->intake()->assertSessionHasNoErrors();
        $service = WatchService::sole();
        $this->assertSame('within_period', $service->warranty_snapshot['result']);
        $this->assertSame('received', $service->status);
        $this->assertSame('pending', $service->coverage_decision);
        $this->intake()->assertSessionHasErrors('product_item_id');
        $this->assertSame(1, WatchService::count());
        $firstEvent = $service->events()->sole()->id;
        foreach (['inspection', 'sent_to_company', 'repairing', 'ready', 'completed'] as $status) {
            $this->put(route('watch-services.update', $service), [
                'repair_provider' => 'external', 'external_shop_name' => 'Test company', 'status' => $status, 'coverage_decision' => 'approved', 'decision_notes' => 'Manufacturing defect; receipt verified.',
                'notes' => 'Updated to '.$status, 'last_event_id' => $service->events()->max('id'),
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(6, $service->events()->count());
        $this->assertSame('completed', $service->fresh()->status);
        $this->assertSame('sold', $this->unit->fresh()->status);
        $this->put(route('watch-services.update', $service), ['status' => 'inspection', 'coverage_decision' => 'pending', 'notes' => 'Stale update', 'last_event_id' => $firstEvent])->assertSessionHasErrors('notes');
        $this->watch->update(['warranty_period' => 0]);
        $this->assertSame('within_period', $service->fresh()->warranty_snapshot['result']);
        $this->get(route('watch-services.show', $service))->assertInertia(fn (Assert $page) => $page->component('Warranty/Show')->has('service.events', 6)->where('currentCheck.result', 'no_warranty'));
        $this->intake()->assertSessionHasNoErrors();
        $this->assertSame(2, WatchService::count());
        $this->put(route('watch-services.update', $service), ['status' => 'received', 'coverage_decision' => 'pending', 'notes' => 'Reopen', 'last_event_id' => $service->events()->max('id')])->assertSessionHasErrors('status');
    }

    public function test_validation_search_and_admin_access(): void
    {
        $this->get(route('watch-services.index', ['q' => '123456789012', 'unit_id' => $this->unit->id]))->assertInertia(fn (Assert $page) => $page->component('Warranty/Index')->has('matches', 1)->where('check.customer_name', 'Warranty Customer'));
        $this->intake(['issue_date' => '2026-01-30'])->assertSessionHasErrors('issue_date');
        $this->intake(['issue_date' => '2026-03-01'])->assertSessionHasErrors('issue_date');
        $this->intake()->assertSessionHasNoErrors();
        $service = WatchService::sole();
        $this->put(route('watch-services.update', $service), ['status' => 'inspection', 'coverage_decision' => 'rejected', 'notes' => 'Review', 'last_event_id' => $service->events()->max('id')])->assertSessionHasErrors('decision_notes');
        $this->watch->update(['kind' => 'accessory']);
        $this->intake()->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->get(route('watch-services.index'))->assertForbidden();
        $this->get(route('watch-services.show', $service))->assertForbidden();
        $this->intake()->assertForbidden();
        $this->put(route('watch-services.update', $service), [])->assertForbidden();
    }
}
