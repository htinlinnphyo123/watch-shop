<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundExistingOrderTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    private function order(array $attributes = []): Order
    {
        return Order::create($attributes + [
            'order_number' => 'ROUND-'.uniqid(),
            'status' => 'completed', 'total_amount' => 1138960,
            'discount_percentage' => 8, 'amount_paid' => 1138960,
            'payment_method' => 'cash',
            'payments' => [['method' => 'cash', 'amount' => '1138960.00']],
            'delivery_fees' => 10000, 'edit_version' => 2,
        ])->fresh();
    }

    public function test_preview_never_writes_orders_or_history(): void
    {
        $order = $this->order();
        $before = $order->getAttributes();
        $this->artisan('orders:round-totals', ['--adjust-paid' => true])
            ->expectsOutputToContain('Would update 1 order(s)')->assertSuccessful();
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('order_audits', 0);
    }

    public function test_apply_with_matching_payments_is_audited_and_repeatable(): void
    {
        $order = $this->order();
        $updatedAt = $order->updated_at->toDateTimeString();
        $this->travel(1)->hours();
        $this->artisan('orders:round-totals', ['--apply' => true, '--adjust-paid' => true])->assertSuccessful();
        $order->refresh();
        $this->assertSame('1139000.00', $order->total_amount);
        $this->assertSame('1139000.00', $order->amount_paid);
        $this->assertSame('1139000.00', $order->payments[0]['amount']);
        $this->assertSame('10000.00', $order->delivery_fees);
        $this->assertSame('8.00', $order->discount_percentage);
        $this->assertSame(3, $order->edit_version);
        $this->assertSame($updatedAt, $order->updated_at->toDateTimeString());
        $history = OrderAudit::where('order_id', $order->id)->orderBy('version')->get();
        $this->assertSame(['baseline', 'total_rounded'], $history->pluck('event')->all());
        $this->assertSame('1138960.00', $history[0]->snapshot['total_amount']);
        $this->assertSame('1139000.00', $history[1]->snapshot['total_amount']);
        $this->assertSame('system', $history[1]->actor_type);
        $this->artisan('orders:round-totals', ['--apply' => true, '--adjust-paid' => true])
            ->expectsOutputToContain('Updated 0 order(s)')->assertSuccessful();
        $this->assertDatabaseCount('order_audits', 2);
        $this->assertSame(3, $order->fresh()->edit_version);
    }

    public function test_rounds_down_and_half_up_without_changing_recorded_payments_by_default(): void
    {
        $down = $this->order(['total_amount' => 1139480]);
        $half = $this->order(['total_amount' => 1139500]);
        $this->artisan('orders:round-totals', ['--apply' => true])->assertSuccessful();
        $this->assertSame('1139000.00', $down->fresh()->total_amount);
        $this->assertSame('1140000.00', $half->fresh()->total_amount);
        $this->assertSame('1138960.00', $down->fresh()->amount_paid);
        $this->assertSame($down->payments, $down->fresh()->payments);
    }

    public function test_adjusts_split_and_legacy_full_payments_but_keeps_deposits_and_cash_overpayments(): void
    {
        $split = $this->order(['payments' => [
            ['method' => 'card', 'amount' => '1000000.00'], ['method' => 'cash', 'amount' => '138960.00'],
        ], 'payment_method' => 'split']);
        $nonCash = $this->order(['payments' => [
            ['method' => 'card', 'amount' => '1000000.00'], ['method' => 'kbz_pay', 'amount' => '138960.00'],
        ], 'payment_method' => 'split']);
        $legacy = $this->order(['payments' => null]);
        $deposit = $this->order(['status' => 'pending', 'amount_paid' => 500000, 'payments' => [['method' => 'cash', 'amount' => '500000.00']]]);
        $overpaid = $this->order(['amount_paid' => 1200000, 'payments' => [['method' => 'cash', 'amount' => '1200000.00']]]);
        $unknown = $this->order(['amount_paid' => null, 'payments' => null]);
        $this->artisan('orders:round-totals', ['--apply' => true, '--adjust-paid' => true])->assertSuccessful();
        $this->assertSame('1000000.00', $split->fresh()->payments[0]['amount']);
        $this->assertSame('139000.00', $split->fresh()->payments[1]['amount']);
        $this->assertSame('1000040.00', $nonCash->fresh()->payments[0]['amount']);
        $this->assertSame('1139000.00', $legacy->fresh()->amount_paid);
        $this->assertNull($legacy->fresh()->payments);
        foreach ([$deposit, $overpaid, $unknown] as $order) {
            $this->assertSame('1139000.00', $order->fresh()->total_amount);
            $this->assertSame($order->amount_paid, $order->fresh()->amount_paid);
            $this->assertSame($order->payments, $order->fresh()->payments);
        }
    }

    public function test_undiscounted_orders_are_rounded_without_changing_payments(): void
    {
        $order = $this->order([
            'discount_percentage' => 0, 'total_amount' => 2140200, 'amount_paid' => 2140200,
            'payments' => [['method' => 'cash', 'amount' => '2140200.00']],
        ]);
        $this->artisan('orders:round-totals')->expectsOutputToContain('Would update 1 order(s)')->assertSuccessful();
        $this->assertSame('2140200.00', $order->fresh()->total_amount);
        $this->artisan('orders:round-totals', ['--apply' => true])->assertSuccessful();
        $this->assertSame('2140000.00', $order->fresh()->total_amount);
        $this->assertSame('2140200.00', $order->fresh()->amount_paid);
        $this->assertSame($order->payments, $order->fresh()->payments);
        $this->artisan('orders:round-discounted-totals')->expectsOutputToContain('Would update 0 order(s)')->assertSuccessful();
    }

    public function test_skips_cancelled_and_already_rounded_orders_and_flags_inconsistent_payments(): void
    {
        $orders = [
            $this->order(['status' => 'cancelled']),
            $this->order(['total_amount' => 1139000]),
            $this->order(['payments' => [['method' => 'cash', 'amount' => '500000.00']]]),
        ];
        $this->artisan('orders:round-totals', ['--apply' => true, '--adjust-paid' => true])
            ->expectsOutputToContain('payment breakdown does not match amount paid')->assertFailed();
        foreach ($orders as $order) {
            $this->assertSame($order->getAttributes(), $order->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('order_audits', 0);
    }
}
