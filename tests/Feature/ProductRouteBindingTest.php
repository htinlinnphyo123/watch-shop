<?php

namespace Tests\Feature;

use App\Models\{Brand, Product};
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    public function test_text_product_ids_are_rejected_before_model_binding_queries(): void
    {
        $productQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$productQueries) {
            if (str_contains($query->sql, '"products"')) $productQueries[] = $query->sql;
        });
        foreach (['default', 'undefined', 'null', '1abc'] as $id) {
            $this->getJson('/api/v1/spa/products/'.$id)->assertNotFound();
            $this->getJson('/api/v1/spa/accessories/'.$id)->assertNotFound();
            $this->getJson('/products/'.$id)->assertNotFound();
            $this->getJson('/pos/products/'.$id.'/available-items')->assertNotFound();
        }
        $this->assertSame([], $productQueries, 'Invalid IDs must not reach numeric product lookups.');
    }

    public function test_numeric_product_detail_and_list_routes_still_work(): void
    {
        $brand = Brand::create(['name' => 'Route test']);
        $product = Product::create(['brand_id' => $brand->id, 'name' => 'Watch', 'price' => 100000,
            'currency' => 'MMK', 'kind' => 'watch', 'is_active' => true, 'is_public' => true]);
        $this->getJson('/api/v1/spa/products')->assertOk()->assertJsonPath('data.data.0.id', $product->id);
        $this->getJson('/api/v1/spa/products/'.$product->id)->assertOk()->assertJsonPath('data.product.id', $product->id);
        $this->getJson('/api/v1/spa/products/999999999')->assertNotFound();
    }
}
