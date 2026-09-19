<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessoryMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
        $this->artisan('migrate')->assertExitCode(0);
    }

    public function test_upgrade_preserves_existing_watch_order_stock_and_image_data(): void
    {
        // Simulate the pre-accessories schema, populated before the upgrade.
        $migration = require database_path('migrations/2026_09_16_000000_add_accessories.php');
        $migration->down();
        \Illuminate\Support\Facades\Schema::table('products', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable(false)->change();
        });
        $brand = Brand::create(['name' => 'Existing brand']);
        $watch = Product::create([
            'brand_id' => $brand->id, 'name' => 'Existing watch', 'model_number' => 'OLD-001',
            'price' => 125000, 'cost_price' => 80000, 'currency' => 'MMK',
            'images' => ['products/gallery/existing-photo.jpg'],
            'image' => 'products/existing-main.jpg', 'is_active' => true, 'is_public' => true,
        ]);
        $order = Order::create(['order_number' => 'EXISTING-001', 'status' => 'completed', 'total_amount' => 125000]);
        $line = $order->items()->create(['product_id' => $watch->id, 'quantity' => 1, 'price' => 125000]);
        $watch->items()->create(['status' => 'sold', 'serial_number' => 'EXISTING-SERIAL', 'order_item_id' => $line->id]);
        $watch->items()->create(['status' => 'available', 'system_unique_id' => '123456789012']);
        $watch->items()->create(['status' => 'reserved']);
        $snapshot = [];
        foreach (['products', 'product_items', 'orders', 'order_items'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        DB::table('migrations')->where('migration', '2026_09_16_000000_add_accessories')->delete();
        $this->artisan('migrate')->assertExitCode(0);

        $upgraded = DB::table('products')->where('id', $watch->id)->first();
        $this->assertSame('watch', $upgraded->kind);
        $this->assertNull($upgraded->accessory_type_id);
        $this->assertNull($upgraded->accessory_attributes);
        foreach ($snapshot as $table => $before) {
            $after = DB::table($table)->orderBy('id')->get()->map(function ($row) use ($table) {
                $data = (array) $row;
                if ($table === 'products') {
                    unset($data['kind'], $data['accessory_type_id'], $data['accessory_attributes']);
                }
                return $data;
            })->all();
            $this->assertEquals($before, $after, "Existing {$table} data changed during the upgrade.");
        }
        $this->getJson('/api/v1/spa/products/'.$watch->id)->assertOk()
            ->assertJsonPath('data.product.id', $watch->id);
        $this->assertDatabaseCount('accessory_types', 2);
    }
}
