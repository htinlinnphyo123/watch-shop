<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Only run with DB_CONNECTION=sqlite DB_DATABASE=:memory:.');
        }
    }

    private function order(string $date, float $amount, string $status = 'completed'): Order
    {
        return Order::create(['order_number' => 'ANALYTICS-'.uniqid(), 'status' => $status, 'total_amount' => $amount, 'created_at' => $date]);
    }

    public function test_discounted_sales_are_not_multiplied_by_lines_and_all_breakdowns_reconcile(): void
    {
        $brand = Brand::create(['name' => 'Analytics brand']);
        $watch = Product::create(['name' => 'Watch', 'brand_id' => $brand->id, 'kind' => 'watch', 'price' => 5000]);
        $accessory = Product::create(['name' => 'Strap', 'kind' => 'accessory', 'price' => 1000]);
        $a = $this->order('2026-09-01 00:00:00', 270.50);
        $a->items()->create(['product_id' => $watch->id, 'quantity' => 2, 'price' => 100]);
        $a->items()->create(['product_id' => $accessory->id, 'quantity' => 1, 'price' => 100]);
        $b = $this->order('2026-09-02 23:59:59', 50);
        $b->items()->create(['product_id' => $watch->id, 'quantity' => 1, 'price' => 50]);
        $watch->delete();
        $this->order('2026-08-30 00:00:00', 100);
        $this->order('2026-08-29 23:59:59', 9999);
        $this->order('2026-09-03 00:00:00', 9999);
        foreach (['pending', 'processing', 'cancelled'] as $status) {
            $excluded = $this->order('2026-09-01 12:00:00', 9999, $status);
            $excluded->items()->create(['product_id' => $watch->id, 'quantity' => 100, 'price' => 100]);
        }
        $report = app(SalesAnalyticsService::class)->report(['from' => '2026-09-01', 'to' => '2026-09-02']);
        $this->assertEquals(320.50, $report['summary']['total_sales']);
        $this->assertSame(2, $report['summary']['completed_orders']);
        $this->assertSame(4, $report['summary']['items_sold']);
        $this->assertEquals(160.25, $report['summary']['average_sale']);
        $this->assertEquals(100, $report['summary']['previous_sales']);
        $this->assertSame('2026-08-30', $report['summary']['previous_from']);
        $this->assertSame('2026-08-31', $report['summary']['previous_to']);
        $this->assertEquals(220.5, $report['summary']['change_percent']);
        $this->assertEquals(320.50, array_sum(array_column($report['trend'], 'sales')));
        $this->assertEquals(2, array_sum(array_column($report['trend'], 'orders')));
        $this->assertEquals(4, $report['productTypes']->sum('units'));
        $this->assertSame($watch->id, $report['topProducts'][0]->product_id);
        $this->assertEquals(3, $report['topProducts'][0]->units);
        $this->assertEquals(2, $report['topProducts'][0]->orders);
        $this->assertEquals(5, $report['statuses']->sum('count'));
        $this->assertCount(2, $report['recent']);
        $this->assertSame($b->id, $report['recent'][0]->id);
    }

    public function test_empty_days_zero_sales_and_missing_products_are_handled(): void
    {
        $service = app(SalesAnalyticsService::class);
        $filters = ['from' => '2026-09-01', 'to' => '2026-09-03'];
        $empty = $service->report($filters);
        $this->assertSame(0, $empty['summary']['completed_orders']);
        $this->assertSame(0, $empty['summary']['average_sale']);
        $this->assertNull($empty['summary']['change_percent']);
        $this->assertCount(3, $empty['trend']);
        $this->assertEquals(0, array_sum(array_column($empty['trend'], 'sales')));
        $order = $this->order('2026-09-02 12:00:00', 0);
        $order->items()->create(['product_id' => null, 'quantity' => 1, 'price' => 100]);
        $report = $service->report($filters);
        $this->assertSame(1, $report['summary']['completed_orders']);
        $this->assertSame(1, $report['summary']['items_sold']);
        $this->assertSame('unknown', $report['productTypes'][0]->type);
        $this->assertEquals(0, $report['summary']['average_sale']);
        $this->assertNull($report['summary']['change_percent']);
        $this->assertSame([0, 1, 0], array_column($report['trend'], 'orders'));
    }

    public function test_authenticated_staff_can_read_reports_and_dates_are_validated(): void
    {
        $this->travelTo(Carbon::parse('2026-09-19 12:00:00'));
        $this->get(route('sales.analytics'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']));
        $this->get(route('sales.analytics'))->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Analytics')->where('filters.from', '2026-08-21')
            ->where('filters.to', '2026-09-19')->has('report.trend', 30)->has('presets', 4));
        $this->from(route('sales.analytics'))->get(route('sales.analytics', ['from' => '2026-09-19', 'to' => '2026-09-01']))->assertSessionHasErrors('to');
        $this->get(route('sales.analytics', ['from' => '2025-01-01', 'to' => '2026-09-19']))->assertSessionHasErrors('to');
        $this->get(route('sales.analytics', ['from' => '2026-09-19', 'to' => '2026-09-20']))->assertSessionHasErrors('to');
        $this->get(route('sales.analytics', ['from' => 'invalid', 'to' => '2026-09-19']))->assertSessionHasErrors('from');
        $this->get(route('sales.analytics', ['from' => '2026-09-19', 'to' => '2026-09-19']))->assertInertia(fn (Assert $page) => $page->has('report.trend', 1));
    }
}
