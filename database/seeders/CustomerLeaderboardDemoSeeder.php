<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductItem;
use App\Services\StockCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerLeaderboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $db = DB::connection();
        $isMemoryTest = app()->environment('testing') && $db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:';
        $isLocalTest = $db->getDriverName() === 'pgsql'
            && in_array($db->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            && str_ends_with($db->selectOne('SELECT current_database() AS name')->name, '_test');
        if (! $isMemoryTest && ! $isLocalTest) {
            throw new \RuntimeException('Demo seeding is restricted to a local PostgreSQL database ending in _test or an in-memory test database.');
        }

        DB::transaction(function () {
            $this->synchronizeRestoredSequences();
            $brand = Brand::firstOrCreate(['name' => 'Leaderboard demo brand'], ['slug' => 'leaderboard-demo-brand']);
            $product = Product::firstOrCreate(['barcode' => 'DEMO-LEADERBOARD-WATCH'], [
                'brand_id' => $brand->id, 'name' => 'Demo report watch', 'kind' => 'watch',
                'price' => 75000, 'cost_price' => 50000, 'currency' => 'MMK',
                'is_active' => false, 'is_public' => false,
            ]);
            $sources = ['facebook', 'tiktok', 'referral', 'instagram', 'walk_in', 'other', null];
            for ($i = 1; $i <= 20; $i++) {
                $source = $sources[($i - 1) % count($sources)];
                $customer = Customer::firstOrCreate(['email' => "leaderboard-demo-{$i}@example.test"], [
                    'name' => sprintf('Demo Customer %02d', $i), 'source' => $source,
                    'source_details' => $source === 'referral' ? 'Demo referrer '.(($i % 3) + 1)
                        : (in_array($source, ['facebook', 'tiktok', 'instagram']) ? "https://example.test/profile/{$i}" : null),
                ]);
                for ($j = 1; $j <= ($i % 8) + 1; $j++) {
                    $this->order($product, $customer->id, "DEMO-LB-{$i}-{$j}", ($i * 3 + $j * 7) % 90, ($j % 3) + 1, $j === 8 ? 'cancelled' : 'completed');
                }
            }
            for ($i = 1; $i <= 3; $i++) {
                $this->order($product, null, "DEMO-LB-GUEST-{$i}", $i * 9, $i, 'completed');
            }
        });
        $this->command?->info('Demo customers and orders are ready. Existing demo records are skipped on repeat runs.');
    }

    private function synchronizeRestoredSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // This seeder is restricted to local test databases. Restores may omit sequence state/ownership.
        DB::statement('LOCK TABLE brands, customers, order_items, orders, product_items, products IN SHARE ROW EXCLUSIVE MODE');
        foreach (['brands', 'customers', 'order_items', 'orders', 'product_items', 'products'] as $table) {
            $sequence = DB::selectOne(<<<'SQL'
                SELECT COALESCE(pg_get_serial_sequence(?, 'id'), (
                    SELECT s.oid::regclass::text FROM pg_attrdef d
                    JOIN pg_attribute a ON a.attrelid = d.adrelid AND a.attnum = d.adnum
                    JOIN pg_depend p ON p.classid = 'pg_attrdef'::regclass AND p.objid = d.oid AND p.refclassid = 'pg_class'::regclass
                    JOIN pg_class s ON s.oid = p.refobjid AND s.relkind = 'S'
                    WHERE d.adrelid = ?::regclass AND a.attname = 'id'
                )) AS name
                SQL, [$table, $table])->name;
            if (! $sequence) {
                throw new \RuntimeException("Cannot locate {$table}.id sequence in the restored database.");
            }
            $state = DB::selectOne('SELECT last_value, is_called FROM '.$sequence);
            $maximum = DB::table($table)->max('id');
            if ($maximum !== null && ($maximum > $state->last_value || ($maximum == $state->last_value && ! $state->is_called))) {
                DB::selectOne('SELECT setval(?::regclass, ?, true)', [$sequence, $maximum]);
            }
        }
    }

    private function order(Product $product, ?int $customerId, string $number, int $daysAgo, int $quantity, string $status): void
    {
        if (Order::where('order_number', $number)->exists()) {
            return;
        }
        $date = now()->subDays($daysAgo)->setTime(12, 0);
        $total = 75000 * $quantity;
        $order = Order::create([
            'customer_id' => $customerId, 'order_number' => $number, 'status' => $status,
            'total_amount' => $total, 'amount_paid' => $status === 'completed' ? $total : 0,
            'payment_method' => 'cash', 'payments' => $status === 'completed' ? [['method' => 'cash', 'amount' => $total]] : [],
            'notes' => 'Demo data for customer leaderboard testing.', 'created_at' => $date, 'updated_at' => $date,
        ]);
        $line = $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => 75000]);
        if ($status === 'completed') {
            for ($unit = 1; $unit <= $quantity; $unit++) {
                ProductItem::create([
                    'product_id' => $product->id, 'order_item_id' => $line->id,
                    'system_unique_id' => app(StockCodeService::class)->generate(), 'status' => 'sold',
                ]);
            }
        }
    }
}
