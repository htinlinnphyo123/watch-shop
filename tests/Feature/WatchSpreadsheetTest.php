<?php

namespace Tests\Feature;

use App\Models\AccessoryType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class WatchSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function workbook(array $rows): UploadedFile
    {
        $path = sys_get_temp_dir().'/watch-import-'.Str::uuid().'.xlsx';
        (new FastExcel(collect($rows)))->export($path);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, 'watches.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => null, 'name' => 'Imported watch',
            'model_number' => 'MODEL-1', 'barcode' => 'WATCH-IMPORT-1', 'price' => 250000,
            'currency' => 'MMK', 'brand' => 'Test Brand', 'collection' => null,
            'categories' => 'Dress', 'stock_to_add' => 0, 'is_active' => 1, 'is_public' => 1,
        ], $overrides);
    }

    public function test_watch_export_excludes_accessories_and_includes_safe_stock_columns(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $brand = Brand::create(['name' => 'Test Brand']);
        $watch = Product::create(['kind' => 'watch', 'brand_id' => $brand->id, 'name' => 'Exported watch', 'barcode' => 'WATCH-1', 'price' => 1000, 'currency' => 'MMK']);
        $watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000001']);
        $watch->items()->create(['status' => 'sold', 'system_unique_id' => '000000000002']);
        Product::create(['kind' => 'accessory', 'accessory_type_id' => AccessoryType::first()->id, 'name' => 'Secret strap', 'barcode' => 'ACC-1', 'price' => 500, 'currency' => 'MMK']);

        $response = $this->get(route('products.export'))->assertOk();
        $this->assertStringContainsString('watches-', $response->headers->get('content-disposition'));
        $path = sys_get_temp_dir().'/watch-export-'.Str::uuid().'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $this->temporaryFiles[] = $path;
        $rows = (new FastExcel)->import($path);

        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('record_type', $rows->first());
        $this->assertSame('Exported watch', $rows->first()['name']);
        $this->assertSame(2, $rows->first()['stock_total_read_only']);
        $this->assertSame(1, $rows->first()['stock_available_read_only']);
        $this->assertSame(0, $rows->first()['stock_to_add']);
        $this->assertStringNotContainsString('Secret strap', $response->streamedContent());
    }

    public function test_import_creates_only_a_watch_and_adds_individually_barcoded_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Brand::create(['name' => 'Test Brand']);
        $category = Category::create(['name' => 'Dress']);
        $file = $this->workbook([$this->row([
            'brand' => 'test brand', 'categories' => 'DRESS', 'stock_to_add' => 2,
            'is_active' => 'yes', 'is_public' => 'false', 'unknown_column' => 'ignored safely',
        ])]);

        $this->post(route('products.import'), ['file' => $file])->assertSessionHasNoErrors()->assertSessionHas('success');
        $watch = Product::where('barcode', 'WATCH-IMPORT-1')->firstOrFail();
        $this->assertSame('watch', $watch->kind);
        $this->assertTrue((bool) $watch->is_active);
        $this->assertFalse((bool) $watch->is_public);
        $this->assertTrue($watch->categories->contains($category));
        $this->assertCount(2, $watch->items);
        foreach ($watch->items as $item) {
            $this->assertMatchesRegularExpression('/^\d{12}$/', $item->system_unique_id);
        }
    }

    public function test_import_cannot_update_an_accessory_and_rolls_back_other_rows(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Brand::create(['name' => 'Test Brand']);
        Category::create(['name' => 'Dress']);
        $accessory = Product::create(['kind' => 'accessory', 'accessory_type_id' => AccessoryType::first()->id, 'name' => 'Leather strap', 'barcode' => 'ACC-1', 'price' => 500, 'currency' => 'MMK']);
        $file = $this->workbook([
            $this->row(['name' => 'Would be created', 'barcode' => 'WATCH-ROLLBACK']),
            $this->row(['id' => $accessory->id, 'name' => 'Changed by spreadsheet', 'barcode' => 'ACC-1']),
        ]);

        $this->post(route('products.import'), ['file' => $file])->assertSessionHas('import_errors');
        $this->assertSame('Leather strap', $accessory->fresh()->name);
        $this->assertDatabaseMissing('products', ['barcode' => 'WATCH-ROLLBACK']);
    }

    public function test_watch_import_and_export_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('products.export'))->assertForbidden();
        $this->post(route('products.import'), ['file' => UploadedFile::fake()->create('watches.csv', 1, 'text/csv')])->assertForbidden();
    }
}
