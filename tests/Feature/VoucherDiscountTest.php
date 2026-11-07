<?php

namespace Tests\Feature;

use App\Models\{Brand, Order, Product, User};
use App\Services\SalesWorkbookExport;
use App\Support\VoucherPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    private function checkout(array $prices, float $percentage = 2): array
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $brand = Brand::create(['name' => 'Voucher watches']);
        $cart = [];
        foreach ($prices as [$price, $discount]) {
            $product = Product::create(['name' => 'Watch', 'price' => $price, 'currency' => 'MMK', 'brand_id' => $brand->id]);
            $unit = $product->items()->create(['status' => 'available']);
            $cart[] = ['product_id' => $product->id, 'item_id' => $unit->id, 'discount_percentage' => $discount];
        }
        return ['cart' => $cart, 'overall_discount_percentage' => $percentage, 'status' => 'pending',
            'payments' => [['method' => 'cash', 'amount' => 0]]];
    }

    public function test_screenshot_example_preserves_prices_and_allocates_net_sales_for_export(): void
    {
        $payload = $this->checkout([[2263000, 8], [178000, 17.3]]);
        $this->post(route('pos.checkout'), $payload + ['delivery_fees' => 5000])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertEquals(44583.32, $order->overall_discount_amount);
        $this->assertEquals(2185000, $order->total_amount);
        $lines = $order->items;
        $this->assertEquals(2263000, $lines[0]->price);
        $this->assertSame('8.00', $lines[0]->discount_percentage);
        $this->assertEquals(22291.66, $lines[0]->overall_discount_amount);
        $this->assertEquals(22291.66, $lines[1]->overall_discount_amount);
        $this->assertEquals(2185000, $lines->sum('net_total'));
        $rows = iterator_to_array(app(SalesWorkbookExport::class)->saleRows($order));
        $this->assertEquals(2185000, array_sum(array_column($rows, 'Net Sales')));
        $this->assertEquals(44583.32, array_sum(array_column($rows, 'Overall Discount')));
        $this->assertEquals(5000, $rows[0]['Delivery Fees']);
        $this->assertNull($rows[1]['Total Payment']);
        $this->assertEquals(0.08, $rows[0]['Discount']);
    }

    public function test_equal_split_caps_cheaper_watches_and_quantity_grouping_does_not_change_totals(): void
    {
        $a = VoucherPricing::calculate([
            ['price' => 1000000, 'quantity' => 2, 'discount_percentage' => 0],
            ['price' => 10000, 'quantity' => 1, 'discount_percentage' => 0],
        ], 50);
        $b = VoucherPricing::calculate([
            ['price' => 1000000, 'quantity' => 1, 'discount_percentage' => 0],
            ['price' => 1000000, 'quantity' => 1, 'discount_percentage' => 0],
            ['price' => 10000, 'quantity' => 1, 'discount_percentage' => 0],
        ], 50);
        $this->assertEquals(1005000, $a['discount']);
        $this->assertEquals(10000, $a['lines'][1]['overall_discount_amount']);
        $this->assertEquals(0, $a['lines'][1]['net_total']);
        $this->assertEquals($a['total'], $b['total']);
        $this->assertEquals(497500, $b['lines'][0]['overall_discount_amount']);
        $this->assertEquals($a['total'], array_sum(array_column($a['lines'], 'net_total')));
        foreach ([0, 100, 33.33] as $percentage) {
            $result = VoucherPricing::calculate([['price' => 1999.99, 'quantity' => 3, 'discount_percentage' => 17.3]], $percentage);
            $line = $result['lines'][0];
            $units = VoucherPricing::units(1999.99, 3, 17.3, $line['overall_discount_amount'], $line['rounding_adjustment']);
            $this->assertEqualsWithDelta($result['total'], array_sum(array_column($units, 'Net Sales')), 0.001);
            $this->assertGreaterThanOrEqual(0, min(array_column($units, 'Net Sales')));
        }
    }

    public function test_edit_preserves_then_removes_voucher_and_updates_audit(): void
    {
        $payload = $this->checkout([[100000, 0], [100000, 0]], 10);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::sole();
        unset($payload['overall_discount_percentage']);
        $this->put(route('pos.orders.update', $order), $payload + ['edit_version' => 0])->assertSessionHasNoErrors();
        $this->assertEquals(180000, $order->fresh()->total_amount);
        $this->assertEquals(10, $order->fresh()->overall_discount_percentage);
        $this->put(route('pos.orders.update', $order), $payload + ['edit_version' => 1, 'overall_discount_percentage' => 0])->assertSessionHasNoErrors();
        $this->assertEquals(200000, $order->fresh()->total_amount);
        $this->assertEquals(0, $order->fresh()->items->sum('overall_discount_amount'));
        $this->assertEquals(0, \App\Models\OrderAudit::latest('id')->first()->snapshot['overall_discount_percentage']);
    }

    public function test_invalid_percentages_and_deposit_exceeding_discounted_total_do_not_hold_stock(): void
    {
        $payload = $this->checkout([[100000, 0]], 10);
        foreach ([-1, 100.01, '2.123', 'invalid'] as $percentage) {
            $this->post(route('pos.checkout'), array_replace($payload, ['overall_discount_percentage' => $percentage]))
                ->assertSessionHasErrors('overall_discount_percentage');
        }
        $this->post(route('pos.checkout'), $payload + ['money_transfer_amount' => 95000])->assertSessionHasErrors('money_transfer_amount');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('product_items', ['id' => $payload['cart'][0]['item_id'], 'status' => 'available']);
    }
}
