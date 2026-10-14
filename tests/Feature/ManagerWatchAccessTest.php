<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagerWatchAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    private function watch(): Product
    {
        return Product::create([
            'name' => 'Manager Watch', 'model_number' => 'MW-001', 'kind' => 'watch',
            'brand_id' => Brand::firstOrCreate(['name' => 'Test'])->id,
            'price' => 200000, 'cost_price' => 123456, 'currency' => 'MMK',
        ]);
    }

    public function test_manager_can_browse_details_and_print_labels_without_cost_prices(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $watch = $this->watch();
        $unit = $watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000001']);

        $this->get(route('products.index', ['search' => 'MW-001', 'edit' => $watch->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $watch->id)
                ->missing('products.data.0.cost_price')
                ->where('editingProduct', null)
                ->where('latestImport', null));
        $this->get(route('products.show', $watch))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->where('product.id', $watch->id)
                ->missing('product.cost_price')
                ->where('items.data.0.id', $unit->id));
        $this->postJson(route('products.labels'))->assertOk()->assertExactJson([
            'items' => [[
                'id' => $unit->id, 'system_unique_id' => $unit->system_unique_id,
                'product' => ['name' => $watch->name, 'model_number' => $watch->model_number],
                'price_mmk' => 200000,
            ]],
        ]);
    }

    public function test_manager_and_staff_cannot_mutate_inventory_or_access_spreadsheets(): void
    {
        $watch = $this->watch();
        $unit = $watch->items()->create(['status' => 'available']);
        $import = WatchImport::create(['original_name' => 'watches.xlsx', 'file_path' => 'imports/watches.xlsx']);

        foreach (['manager', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['products.create' => [], 'products.edit' => [$watch], 'products.export' => [], 'products.imports.status' => [$import]] as $route => $parameters) {
                $this->get(route($route, $parameters))->assertForbidden();
            }
            $this->post(route('products.store'), [])->assertForbidden();
            $this->put(route('products.update', $watch), ['cost_price' => 1])->assertForbidden();
            $this->delete(route('products.destroy', $watch))->assertForbidden();
            $this->post(route('products.items.store', $watch), ['quantity' => 1])->assertForbidden();
            $this->put(route('items.update', $unit), ['status' => 'lost'])->assertForbidden();
            $this->delete(route('items.destroy', $unit))->assertForbidden();
            $this->post(route('products.presigned-url'), [])->assertForbidden();
            $this->post(route('products.import'), [])->assertForbidden();
            $this->patch(route('products.imports.dismiss', $import))->assertForbidden();
        }

        $this->get(route('products.index'))->assertForbidden();
        $this->get(route('products.show', $watch))->assertForbidden();
        $this->assertSame(123456.0, (float) $watch->fresh()->cost_price);
        $this->assertNull($watch->fresh()->deleted_at);
        $this->assertSame('available', $unit->fresh()->status);
        $this->assertNull($unit->fresh()->deleted_at);
        $this->assertDatabaseCount('product_items', 1);
    }

    public function test_manager_and_staff_pos_lookups_do_not_expose_cost_prices(): void
    {
        $watch = $this->watch();
        $unit = $watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000001']);

        foreach (['manager', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route('pos.products'))->assertOk()
                ->assertJsonPath('data.0.id', $watch->id)->assertJsonMissingPath('data.0.cost_price');
            $this->getJson(route('pos.products.scan', ['code' => $unit->system_unique_id]))->assertOk()
                ->assertJsonPath('product.id', $watch->id)->assertJsonMissingPath('product.cost_price');
        }
    }

    public function test_admin_retains_watch_cost_prices_and_editor_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $watch = $this->watch();
        $this->get(route('products.index', ['edit' => $watch->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data.0.cost_price')
                ->where('editingProduct.id', $watch->id)
                ->has('editingProduct.cost_price'));
        $this->get(route('products.show', $watch))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('product.cost_price'));
        $this->get(route('products.edit', $watch))
            ->assertRedirect(route('products.index', ['edit' => $watch->id]));
    }
}
