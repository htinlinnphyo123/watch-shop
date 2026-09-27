<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    private function watch(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Test Watch', 'model_number' => '00123', 'kind' => 'watch',
            'brand_id' => Brand::firstOrCreate(['name' => 'Test'])->id,
            'price' => 100, 'currency' => 'USD',
        ]);
    }

    public function test_all_available_watch_labels_include_rounded_mmk_prices_across_pages_and_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Setting::create(['key' => 'usd_rate', 'value' => '2145.24']);
        $watch = $this->watch();
        $ids = [];
        for ($i = 0; $i < 31; $i++) {
            $ids[] = $watch->items()->create(['status' => 'available', 'system_unique_id' => str_pad((string) ($i + 1), 12, '0', STR_PAD_LEFT)])->id;
        }
        $second = $this->watch(['name' => 'MMK Watch', 'model_number' => '00234', 'price' => 214232.25, 'currency' => 'MMK']);
        $last = $second->items()->create(['status' => 'available', 'system_unique_id' => '000000000999']);
        // Bulk labels intentionally ignore watch-list filters and pagination.
        $response = $this->postJson(route('products.labels', ['page' => 2, 'search' => 'Not a watch', 'brand_id' => 99999]))
            ->assertOk()->assertJsonCount(32, 'items')
            ->assertJsonPath('items.0.product.name', 'Test Watch')
            ->assertJsonPath('items.0.product.model_number', '00123')
            ->assertJsonPath('items.0.system_unique_id', '000000000001')
            ->assertJsonPath('items.0.price_mmk', 215000)
            ->assertJsonPath('items.31.product.name', 'MMK Watch')
            ->assertJsonPath('items.31.price_mmk', 214232.25);
        $this->assertSame([...$ids, $last->id], array_column($response->json('items'), 'id'));
        $this->assertSame(32, ProductItem::where('status', 'available')->count());
        $this->assertArrayNotHasKey('cost_price', $response->json('items.0.product'));
    }

    public function test_excludes_unavailable_accessory_and_deleted_stock_without_modifying_them(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $watch = $this->watch();
        foreach (['sold', 'reserved', 'returned', 'lost', 'damaged'] as $status) {
            $watch->items()->create(['status' => $status]);
        }
        $deletedItem = $watch->items()->create(['status' => 'available']);
        $deletedItem->delete();
        $deletedWatch = $this->watch(['name' => 'Deleted watch']);
        $deletedWatch->items()->create(['status' => 'available']);
        $deletedWatch->delete();
        $accessory = $this->watch(['kind' => 'accessory', 'name' => 'Strap']);
        $accessory->items()->create(['status' => 'available']);
        $before = ProductItem::withTrashed()->get()->toArray();

        $this->postJson(route('products.labels'))->assertOk()->assertExactJson(['items' => []]);
        $this->assertSame($before, ProductItem::withTrashed()->get()->toArray());
    }

    public function test_generates_only_missing_codes_and_repeated_printing_keeps_codes_and_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        Setting::create(['key' => 'usd_rate', 'value' => '2142.32']);
        $watch = $this->watch();
        $existing = $watch->items()->create(['status' => 'available', 'system_unique_id' => '001234567890']);
        $missing = $watch->items()->create(['status' => 'available']);
        $this->postJson(route('products.labels'))->assertOk()->assertJsonPath('items.0.price_mmk', 214000);
        $code = $missing->fresh()->system_unique_id;
        $this->assertMatchesRegularExpression('/^\d{12}$/', $code);
        $this->assertNotSame($existing->system_unique_id, $code);
        $this->assertSame('001234567890', $existing->fresh()->system_unique_id);
        $this->postJson(route('products.labels'))->assertOk()->assertJsonPath('items.1.system_unique_id', $code);
        $this->assertSame('available', $missing->fresh()->status);
        $this->assertNull($missing->fresh()->order_item_id);
        $this->getJson(route('pos.products.scan', ['code' => $code]))->assertOk()->assertJsonPath('item.id', $missing->id);
    }

    public function test_label_generation_requires_inventory_access(): void
    {
        $this->postJson(route('products.labels'))->assertUnauthorized();
        foreach (['staff', 'user'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->postJson(route('products.labels'))->assertForbidden();
        }
    }
}
