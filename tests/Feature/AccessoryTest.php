<?php

namespace Tests\Feature;

use App\Models\AccessoryType;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessoryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Leather strap 20 mm', 'accessory_type_id' => AccessoryType::first()->id,
            'price' => 15000, 'currency' => 'MMK', 'is_active' => true, 'is_public' => true,
            'accessory_attributes' => [['name' => 'Size', 'value' => '20 mm']], 'images' => [],
        ], $overrides);
    }

    private function accessory(array $overrides = []): Product
    {
        return Product::create($this->payload($overrides) + ['kind' => 'accessory']);
    }

    public function test_stock_detail_tracks_a_sold_barcode_and_filters_without_changing_units(): void
    {
        $this->admin();
        $accessory = $this->accessory();
        $sold = $accessory->items()->create(['status' => 'available', 'system_unique_id' => '000123456789']);
        $available = $accessory->items()->create(['status' => 'available', 'system_unique_id' => '000123456788']);
        $accessory->items()->create(['status' => 'reserved']);
        $this->accessory()->items()->create(['status' => 'available', 'system_unique_id' => '000123456787']);
        $this->post('/pos/checkout', ['payments' => [['method' => 'cash', 'amount' => 15000]], 'cart' => [['product_id' => $accessory->id, 'item_id' => $sold->id]]])->assertSessionHasNoErrors();
        $url = route('accessories.show', $accessory);
        $this->get($url)->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Accessories/Show')->has('items.data', 3)
            ->where('counts.available', 1)->where('counts.sold', 1)->where('counts.reserved', 1));
        $this->get($url.'?search=000123456789&status=sold')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('items.data', 1)->where('items.data.0.id', $sold->id)
            ->where('items.data.0.system_unique_id', '000123456789')->where('items.data.0.status', 'sold')
            ->where('items.data.0.order_item.order_id', Order::firstOrFail()->id));
        $this->get($url.'?status=available')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('items.data', 1)->where('items.data.0.id', $available->id));
        $this->get($url.'?search=000123456789&status=available')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->has('items.data', 0));
        $this->assertSame('000123456789', $sold->fresh()->system_unique_id);
        $this->get(route('products.show', $accessory))->assertRedirect($url);
    }

    public function test_stock_detail_is_admin_only_and_rejects_watches(): void
    {
        $accessory = $this->accessory();
        $this->get(route('accessories.show', $accessory))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('accessories.show', $accessory))->assertForbidden();
        $this->admin();
        $accessory->update(['kind' => 'watch']);
        $this->get(route('accessories.show', $accessory))->assertNotFound();
    }

    public function test_admin_can_create_edit_custom_types_and_accessories_with_images(): void
    {
        $this->admin();
        Storage::fake('s3');
        Storage::fake('local');
        // Images must use Spaces even if another disk becomes the default.
        config(['filesystems.default' => 'local']);
        $this->post(route('accessory-types.store'), ['name' => 'Travel rolls', 'fields' => ['Capacity', 'Lining']])->assertSessionHasNoErrors();
        $type = AccessoryType::where('name', 'Travel rolls')->firstOrFail();
        $this->put(route('accessory-types.update', $type), ['name' => 'Travel cases', 'fields' => ['Capacity']])->assertSessionHasNoErrors();
        $this->post(route('accessories.store'), $this->payload(['accessory_type_id' => $type->id, 'uploads' => [UploadedFile::fake()->image('strap.jpg')]]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertSame('accessory', $product->kind);
        Storage::disk('s3')->assertExists($product->images[0]);
        Storage::disk('local')->assertMissing($product->images[0]);
        $this->getJson('/api/v1/spa/accessories/'.$product->id)->assertOk()
            ->assertJsonPath('data.images.0', Storage::disk('s3')->url($product->images[0]));
        $this->put(route('accessories.update', $product), $this->payload(['name' => 'Updated strap', 'images' => $product->images]))->assertSessionHasNoErrors();
        $this->assertSame('Updated strap', $product->fresh()->name);
    }

    public function test_multipart_empty_arrays_can_be_omitted(): void
    {
        $this->admin();
        $data = $this->payload();
        unset($data['images'], $data['accessory_attributes']);
        $this->post(route('accessories.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame([], Product::firstOrFail()->images);
    }

    public function test_duplicate_attribute_names_are_rejected(): void
    {
        $this->admin();
        $this->post(route('accessories.store'), $this->payload(['accessory_attributes' => [
            ['name' => 'Size', 'value' => '20 mm'], ['name' => 'size', 'value' => '22 mm'],
        ]]))->assertSessionHasErrors('accessory_attributes.0.name');
    }

    public function test_staff_cannot_manage_accessories(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('accessories.index'))->assertForbidden();
        $this->post(route('accessories.store'), $this->payload())->assertForbidden();
    }

    public function test_public_catalog_excludes_private_inactive_and_watches(): void
    {
        $visible = $this->accessory();
        $hidden = $this->accessory(['is_public' => false]);
        $inactive = $this->accessory(['is_active' => false]);
        $this->getJson('/api/v1/spa/accessories?search=strap')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $visible->id)->assertJsonMissingPath('data.data.0.cost_price');
        $this->getJson('/api/v1/spa/accessories/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/v1/spa/accessories/'.$inactive->id)->assertNotFound();
        $this->get('/accessories')->assertNotFound();
    }

    public function test_mixed_pos_sale_deducts_stock_and_prevents_overselling(): void
    {
        $this->admin();
        $accessory = $this->accessory();
        $this->post(route('accessories.stock', $accessory), ['operation' => 'add', 'quantity' => 3])->assertSessionHasNoErrors();
        $brand = Brand::create(['name' => 'Test']);
        $watch = Product::create(['brand_id' => $brand->id, 'name' => 'Watch', 'price' => 50000, 'currency' => 'MMK']);
        $watch->items()->create(['status' => 'available']);
        $this->getJson('/pos/products?q=strap')->assertOk()->assertJsonPath('data.0.kind', 'accessory');
        $this->getJson('/pos/products?kind=accessory')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $accessory->id);
        $this->getJson('/pos/products?kind=watch')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $watch->id);
        $this->post('/pos/checkout', ['payments' => [['method' => 'cash', 'amount' => 80000]], 'cart' => [
            ['product_id' => $accessory->id, 'quantity' => 2], ['product_id' => $watch->id, 'quantity' => 1],
        ]])->assertSessionHasNoErrors();
        $this->assertEquals(80000, Order::firstOrFail()->total_amount);
        $this->assertSame(2, $accessory->items()->where('status', 'sold')->count());
        $this->post('/pos/checkout', ['payments' => [['method' => 'cash', 'amount' => 30000]], 'cart' => [['product_id' => $accessory->id, 'quantity' => 2]]])->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->post(route('accessories.stock', $accessory), ['operation' => 'remove', 'quantity' => 2])->assertSessionHasErrors('quantity');
        $this->assertSame(1, $accessory->items()->where('status', 'available')->count());
        $order = Order::firstOrFail();
        $this->put(route('pos.orders.update', $order), ['edit_version' => 0, 'payments' => [['method' => 'cash', 'amount' => 50000]], 'cart' => [['product_id' => $watch->id, 'quantity' => 1]]])->assertSessionHasNoErrors();
        $this->assertSame(3, $accessory->items()->where('status', 'available')->count());
    }

    public function test_accessory_barcodes_are_generated_and_preserved_on_edit(): void
    {
        $this->admin();
        $this->post(route('accessories.store'), $this->payload())->assertSessionHasNoErrors();
        $accessory = Product::firstOrFail();
        $this->assertStringStartsWith('A-', $accessory->barcode);
        $barcode = $accessory->barcode;
        $this->put(route('accessories.update', $accessory), $this->payload(['barcode' => '']))->assertSessionHasNoErrors();
        $this->assertSame($barcode, $accessory->fresh()->barcode);
        $this->post(route('accessories.stock', $accessory), ['operation' => 'add', 'quantity' => 2])->assertSessionHasNoErrors();
        $units = $accessory->items()->orderBy('id')->get();
        foreach ($units as $unit) {
            $this->assertMatchesRegularExpression('/^[0-9]{12}$/', $unit->system_unique_id);
        }
        $this->assertCount(2, $units->pluck('system_unique_id')->unique());
        $this->getJson('/pos/products/scan?code='.$barcode)->assertOk()->assertJsonPath('product.id', $accessory->id);
    }

    public function test_printing_labels_fills_missing_available_codes_without_changing_existing_stock(): void
    {
        $this->admin();
        $accessory = $this->accessory();
        $old = $accessory->items()->create(['status' => 'available', 'system_unique_id' => '001234567890']);
        $missing = $accessory->items()->create(['status' => 'available']);
        $sold = $accessory->items()->create(['status' => 'sold']);
        $reserved = $accessory->items()->create(['status' => 'reserved']);
        $this->postJson(route('accessories.labels', $accessory))->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.system_unique_id', '001234567890');
        $this->assertSame('001234567890', $old->fresh()->system_unique_id);
        $generated = $missing->fresh()->system_unique_id;
        $this->assertMatchesRegularExpression('/^[0-9]{12}$/', $generated);
        $this->assertNull($sold->fresh()->system_unique_id);
        $this->assertNull($reserved->fresh()->system_unique_id);
        $this->postJson(route('accessories.labels', $accessory))->assertOk()->assertJsonPath('items.1.system_unique_id', $generated);
        $this->assertSame('available', $missing->fresh()->status);
        $this->assertDatabaseCount('product_items', 4);
    }

    public function test_scanned_accessory_unit_is_sold_once_and_unavailable_codes_are_rejected(): void
    {
        $this->admin();
        $accessory = $this->accessory();
        $unit = $accessory->items()->create(['status' => 'available', 'system_unique_id' => '000123456789']);
        $this->getJson('/pos/products/scan?code=000123456789')->assertOk()
            ->assertJsonPath('product.id', $accessory->id)->assertJsonPath('item.id', $unit->id)
            ->assertJsonPath('item.system_unique_id', '000123456789');
        $this->post('/pos/checkout', ['payments' => [['method' => 'cash', 'amount' => 15000]], 'cart' => [['product_id' => $accessory->id, 'item_id' => $unit->id]]])->assertSessionHasNoErrors();
        $this->assertSame('sold', $unit->fresh()->status);
        $this->getJson('/pos/products/scan?code=000123456789')->assertNotFound();
        $reserved = $accessory->items()->create(['status' => 'reserved', 'system_unique_id' => '000123456788']);
        $this->getJson('/pos/products/scan?code='.$reserved->system_unique_id)->assertNotFound();
        $unit->update(['status' => 'available', 'order_item_id' => null]);
        $accessory->update(['is_active' => false]);
        $this->getJson('/pos/products/scan?code=000123456789')->assertNotFound();
    }

    public function test_staff_can_scan_accessories_but_cannot_generate_labels(): void
    {
        $accessory = $this->accessory();
        $unit = $accessory->items()->create(['status' => 'available', 'system_unique_id' => '001234567891']);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->postJson(route('accessories.labels', $accessory))->assertForbidden();
        $this->getJson('/pos/products/scan?code='.$unit->system_unique_id)->assertOk()->assertJsonPath('item.id', $unit->id);
    }

    public function test_admin_filters_and_summary_describe_accessory_inventory(): void
    {
        $this->admin();
        $strap = $this->accessory(['barcode' => 'STRAP-BLACK']);
        $strap->items()->create(['status' => 'available']);
        $this->accessory(['is_active' => false]);
        $this->get('/admin/accessories?search=STRAP-BLACK&status=active&stock=available')
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Accessories/Index')->has('accessories.data', 1)
                ->where('accessories.data.0.id', $strap->id)
                ->where('summary.total', 2)->where('summary.active', 1)
                ->where('summary.available_units', 1)->where('summary.out_of_stock', 1));
        $this->get('/admin/accessories?stock=out')->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->has('accessories.data', 1));
    }

    public function test_inactive_accessory_cannot_be_sold_by_submitting_its_id(): void
    {
        $this->admin();
        $accessory = $this->accessory(['is_active' => false]);
        $accessory->items()->create(['status' => 'available']);
        $this->getJson('/pos/products?q=strap')->assertOk()->assertJsonCount(0, 'data');
        $this->post('/pos/checkout', ['payments' => [['method' => 'cash', 'amount' => 15000]], 'cart' => [['product_id' => $accessory->id, 'quantity' => 1]]])->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
    }
}
