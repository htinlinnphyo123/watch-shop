<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderPaymentClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    public function test_wallet_payments_keep_their_methods_in_orders_summaries_and_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['aya_pay', 'cb_pay'] as $method) {
            foreach (['single', 'legacy', 'split'] as $type) {
                Order::create([
                    'order_number' => "$method-$type",
                    'status' => 'completed',
                    'total_amount' => 10000,
                    'amount_paid' => 10000,
                    'payment_method' => $type === 'split' ? 'split' : $method,
                    'payments' => match ($type) {
                        'legacy' => null,
                        'split' => [['method' => $method, 'amount' => 6000], ['method' => 'cash', 'amount' => 4000]],
                        default => [['method' => $method, 'amount' => 10000]],
                    },
                ]);
            }

            $this->get(route('orders.index', ['payment_method' => $method]))
                ->assertInertia(fn (Assert $page) => $page->has('orders.data', 3)
                    ->where("orders.data.0.payment_breakdown.$method", 6000)
                    ->where('orders.data.0.payment_breakdown.cash', 4000)
                    ->where("orders.data.1.payment_breakdown.$method", 10000)
                    ->where("orders.data.2.payment_breakdown.$method", 10000)
                    ->missing('orders.data.0.payment_breakdown.other'));

            $this->get(route('orders.index', ['payment_method' => $method, 'payment_type' => 'single']))
                ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2));

            $this->get(route('orders.summary', ['payment_method' => $method]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where("summary.by_method.$method", 26000)
                    ->where('summary.by_method.cash', 4000)
                    ->where('summary.by_method.other', 0)
                    ->where('summary.total_received', 30000));
        }
    }
}
