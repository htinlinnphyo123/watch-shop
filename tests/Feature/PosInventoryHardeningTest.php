<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\StockCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosInventoryHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Product $watch;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->watch = Product::create([
            'name' => 'Safety Watch', 'brand_id' => Brand::create(['name' => 'Test'])->id,
            'price' => 10000, 'currency' => 'MMK', 'barcode' => 'WATCH-SAFE',
        ]);
    }

    private function sale(array $extra = []): array
    {
        return array_replace([
            'checkout_request_id' => (string) Str::uuid(),
            'payments' => [['method' => 'cash', 'amount' => 10000]],
            'cart' => [['product_id' => $this->watch->id, 'quantity' => 1]],
        ], $extra);
    }

    public function test_checkout_retry_returns_original_order_without_selling_another_unit(): void
    {
        $this->watch->items()->createMany([['status' => 'available'], ['status' => 'available']]);
        $payload = $this->sale();
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->post(route('pos.checkout'), $payload)->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $this->watch->items()->where('status', 'sold')->count());
        $this->assertSame(1, $this->watch->items()->where('status', 'available')->count());
        $this->post(route('pos.checkout'), array_replace($payload, ['remark' => 'A different sale']))->assertSessionHasErrors('error');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_failed_checkout_can_retry_with_the_same_key_after_fixing_payment(): void
    {
        $this->watch->items()->create(['status' => 'available']);
        $payload = $this->sale(['payments' => [['method' => 'cash', 'amount' => 1]]]);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
        $payload['payments'][0]['amount'] = 10000;
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_pending_retries_and_keys_are_scoped_to_the_cashier(): void
    {
        $this->watch->items()->createMany([['status' => 'available'], ['status' => 'available']]);
        $payload = $this->sale(['status' => 'pending', 'payments' => [['method' => 'cash', 'amount' => 0]]]);
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(0, $this->watch->items()->where('status', 'available')->count());
        $this->assertSame(2, $this->watch->items()->where('status', 'reserved')->count());
    }

    public function test_sold_units_cannot_be_released_or_deleted_from_inventory(): void
    {
        $unit = $this->watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000001']);
        $this->post(route('pos.checkout'), $this->sale())->assertSessionHasNoErrors();
        $this->put(route('items.update', $unit), ['status' => 'available'])->assertSessionHasErrors('status');
        $this->delete(route('items.destroy', $unit))->assertSessionHasErrors('status');
        $this->assertSame('sold', $unit->fresh()->status);
        $this->assertNull($unit->fresh()->deleted_at);
        $this->assertNotNull($unit->fresh()->order_item_id);
        $this->put(route('items.update', $unit), ['status' => 'sold', 'serial_number' => 'CORRECTED-SERIAL'])->assertSessionHasNoErrors();
        $this->post(route('orders.cancel', Order::sole()))->assertSessionHasNoErrors();
        $this->assertSame('available', $unit->fresh()->status);
    }

    public function test_manual_adjustments_cannot_create_sales_or_holds_and_barcodes_are_permanent(): void
    {
        $unit = $this->watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000001']);
        foreach (['sold', 'reserved'] as $status) {
            $this->put(route('items.update', $unit), ['status' => $status])->assertSessionHasErrors('status');
            $this->post(route('products.items.store', $this->watch), ['quantity' => 2, 'status' => $status])->assertSessionHasErrors('status');
        }
        foreach (['000000000002', 'ABCDEFGHIJKL'] as $code) {
            $this->put(route('items.update', $unit), ['status' => 'available', 'system_unique_id' => $code])->assertSessionHasErrors('system_unique_id');
        }
        $this->put(route('items.update', $unit), ['status' => 'damaged'])->assertSessionHasNoErrors();
        $this->delete(route('items.destroy', $unit))->assertSessionHasErrors('status');
        $this->put(route('items.update', $unit), ['status' => 'available'])->assertSessionHasNoErrors();
        $this->assertSame('000000000001', $unit->fresh()->system_unique_id);
        $this->assertSame(1, $this->watch->items()->count());
    }

    public function test_bulk_addition_rolls_back_every_unit_when_generation_fails(): void
    {
        $calls = 0;
        $this->mock(StockCodeService::class, function ($mock) use (&$calls) {
            $mock->shouldReceive('generate')->twice()->andReturnUsing(function () use (&$calls) {
                if (++$calls === 2) throw new \RuntimeException('Simulated stock failure');

                return '000000000001';
            });
        });
        $this->withoutExceptionHandling();
        try {
            $this->post(route('products.items.store', $this->watch), ['quantity' => 2, 'status' => 'available']);
            $this->fail('Expected stock generation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated stock failure', $exception->getMessage());
        }
        $this->assertSame(0, $this->watch->items()->count());
    }

    public function test_watch_edit_link_opens_the_existing_editor_and_unit_codes_are_searchable(): void
    {
        $unit = $this->watch->items()->create(['status' => 'available', 'system_unique_id' => '000000000001', 'serial_number' => 'SERIAL-TEST']);
        $this->get(route('products.edit', $this->watch))->assertRedirect(route('products.index', ['edit' => $this->watch->id]));
        $this->get(route('products.index', ['edit' => $this->watch->id]))->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')->where('editingProduct.id', $this->watch->id));
        foreach ([$unit->system_unique_id, $unit->serial_number] as $code) {
            $this->get(route('products.index', ['search' => $code]))->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)->where('products.data.0.id', $this->watch->id));
        }
    }

    public function test_manual_watch_validation_rejects_bad_prices_warranty_and_duplicate_barcodes(): void
    {
        $payload = [
            'name' => 'New Watch', 'brand_id' => $this->watch->brand_id,
            'category_ids' => [Category::create(['name' => 'Test Category'])->id],
            'price' => 10000, 'warranty_period' => 12, 'barcode' => 'NEW-WATCH',
        ];
        foreach (['price' => -1, 'cost_price' => -1, 'web_price' => -1, 'warranty_period' => -1, 'barcode' => $this->watch->barcode] as $field => $value) {
            $this->post(route('products.store'), array_replace($payload, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->put(route('products.update', $this->watch), array_replace($payload, ['price' => -1]))->assertSessionHasErrors('price');
        $this->put(route('products.update', $this->watch), array_replace($payload, ['barcode' => $this->watch->barcode]))->assertSessionHasNoErrors();
        $this->post(route('products.store'), array_replace($payload, ['customer_group_discounts' => [['group_id' => 999, 'percentage' => 101]]]))
            ->assertSessionHasErrors(['customer_group_discounts.0.group_id', 'customer_group_discounts.0.percentage']);
    }

    public function test_pos_group_overrides_include_zero_and_blend_by_line_value(): void
    {
        $group = CustomerGroup::create(['name' => 'Member', 'percentage' => 20]);
        $customer = Customer::create(['name' => 'Member', 'email' => 'member@test.example', 'customer_group_id' => $group->id]);
        $this->watch->customerGroups()->attach($group->id, ['percentage' => 0]);
        $second = Product::create(['name' => 'Second', 'brand_id' => $this->watch->brand_id, 'price' => 20000, 'currency' => 'MMK']);
        $this->watch->items()->create(['status' => 'available']);
        $second->items()->create(['status' => 'available']);
        $this->getJson(route('pos.products', ['q' => 'Safety']))->assertOk()
            ->assertJsonPath('data.0.customer_groups.0.id', $group->id);
        $this->post(route('pos.checkout'), $this->sale([
            'customer_id' => $customer->id,
            'payments' => [['method' => 'cash', 'amount' => 30000]],
            'cart' => [['product_id' => $this->watch->id, 'quantity' => 1], ['product_id' => $second->id, 'quantity' => 1]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('13.33', Order::sole()->discount_percentage);
        $this->assertSame('26001.00', Order::sole()->total_amount);
    }
}
