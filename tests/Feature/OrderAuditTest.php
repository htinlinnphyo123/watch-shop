<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\OrderAudit;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderAuditTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductItem $unit;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Run with DB_CONNECTION=sqlite DB_DATABASE=:memory:');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => 'Original Cashier']));
        $brand = Brand::create(['name' => 'Test Brand']);
        $this->product = Product::create(['brand_id' => $brand->id, 'name' => 'Original Watch', 'model_number' => 'MODEL-1', 'price' => 20000, 'currency' => 'MMK']);
        $this->unit = ProductItem::create(['product_id' => $this->product->id, 'system_unique_id' => '001234567890', 'serial_number' => 'SERIAL-1', 'status' => 'available']);
    }

    private function checkout(): Order
    {
        $this->post('/pos/checkout', [
            'discount_percentage' => 0,
            'payments' => [['method' => 'cash', 'amount' => 10000], ['method' => 'kbz_pay', 'amount' => 10000]],
            'cart' => [['product_id' => $this->product->id, 'item_id' => $this->unit->id]],
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    private function edit(Order $order, array $overrides = [])
    {
        $order->refresh();

        return $this->put(route('pos.orders.update', $order), array_merge([
            'edit_version' => $order->edit_version,
            'discount_percentage' => $order->discount_percentage,
            'payments' => $order->payments,
            'cart' => [['product_id' => $this->product->id, 'item_id' => $this->unit->id, 'original_line_id' => $order->items()->sole()->id]],
        ], $overrides));
    }

    public function test_original_and_each_edit_preserve_watches_payments_and_actor(): void
    {
        $order = $this->checkout();
        $original = OrderAudit::sole();
        $saved = $original->snapshot;
        $this->assertSame('created', $original->event);
        $this->assertSame('001234567890', $saved['watches']['unit:'.$this->unit->id]['system_code']);
        $this->assertSame(['cash' => '10000.00', 'kbz_pay' => '10000.00'], $saved['payments']);
        $editor = User::factory()->create(['name' => 'Second Cashier']);
        $this->actingAs($editor);
        $customer = Customer::create(['name' => 'New Customer', 'email' => 'customer@example.test']);
        $this->edit($order, ['customer_id' => $customer->id, 'discount_percentage' => 10, 'payments' => [['method' => 'cash', 'amount' => 8000], ['method' => 'kbz_pay', 'amount' => 10000]]])->assertSessionHasNoErrors();
        $second = OrderAudit::where('version', 2)->sole();
        $this->assertSame($editor->id, $second->actor_id);
        $this->assertSame('Second Cashier', $second->actor_name);
        $this->assertSame('18000.00', $second->snapshot['total_amount']);
        $this->assertSame('8000.00', $second->snapshot['payments']['cash']);
        $this->assertContains('Customer', array_column($second->changes, 'label'));
        $this->assertNotContains('Watches', array_column($second->changes, 'section'));

        $new = ProductItem::create(['product_id' => $this->product->id, 'system_unique_id' => 'NEW-UNIT', 'status' => 'available']);
        $this->edit($order, ['cart' => [['product_id' => $this->product->id, 'item_id' => $new->id]]])->assertSessionHasNoErrors();
        $third = OrderAudit::where('version', 3)->sole();
        $watchChanges = array_values(array_filter($third->changes, fn ($change) => $change['section'] === 'Watches'));
        $this->assertCount(2, $watchChanges);
        $this->assertNull($watchChanges[0]['after']);
        $this->assertNull($watchChanges[1]['before']);
        $this->assertSame($saved, $original->fresh()->snapshot);
        $this->assertSame('Original Cashier', $original->fresh()->actor_name);
        $this->assertDatabaseCount('order_audits', 3);
    }

    public function test_history_compares_requested_versions_and_authenticates_reads(): void
    {
        $order = $this->checkout();
        $this->edit($order, ['payments' => [['method' => 'cash', 'amount' => 20000]]])->assertSessionHasNoErrors();
        $this->get(route('orders.history', ['order' => $order, 'from' => 1, 'to' => 2]))->assertInertia(fn (Assert $page) => $page
            ->component('Orders/History')->where('hasOriginal', true)->where('from.version', 1)->where('to.version', 2)
            ->has('changes', 2)->where('changes.0.before', '10000.00')->where('changes.0.after', '20000.00')
            ->where('changes.1.after', null)->has('history.data', 2));
        $this->get(route('orders.history', ['order' => $order, 'to' => 999]))->assertNotFound();
        $other = Order::create(['order_number' => 'OTHER', 'total_amount' => 0, 'status' => 'pending']);
        $this->get(route('orders.history', ['order' => $other, 'to' => 1]))->assertNotFound();
        auth()->logout();
        $this->get(route('orders.history', $order))->assertRedirect('/login');
    }

    public function test_failed_edits_do_not_create_versions(): void
    {
        $order = $this->checkout();
        $this->edit($order, ['payments' => [['method' => 'cash', 'amount' => 1]]])->assertSessionHasErrors('payments');
        $this->assertDatabaseCount('order_audits', 1);
        $this->assertSame('20000.00', $order->fresh()->total_amount);
    }

    public function test_older_order_gets_honest_baseline_and_approval_records_allocated_units(): void
    {
        $order = Order::create(['order_number' => 'LEGACY', 'total_amount' => 20000, 'status' => 'pending']);
        $order->items()->create(['product_id' => $this->product->id, 'quantity' => 1, 'price' => 20000]);
        $this->get(route('orders.history', $order))->assertInertia(fn (Assert $page) => $page
            ->where('hasOriginal', false)->where('to', null)->has('currentSnapshot'));
        $this->assertDatabaseCount('order_audits', 0);
        $this->post(route('orders.approve', $order))->assertSessionHasNoErrors();
        $first = OrderAudit::where('version', 1)->sole();
        $second = OrderAudit::where('version', 2)->sole();
        $this->assertSame('baseline', $first->event);
        $this->assertSame('pending', $first->snapshot['status']);
        $this->assertSame('approved', $second->event);
        $this->assertSame('completed', $second->snapshot['status']);
        $this->assertArrayHasKey('unit:'.$this->unit->id, $second->snapshot['watches']);
        $this->assertSame(auth()->id(), $second->actor_id);
    }

    public function test_failed_approval_rolls_back_baseline(): void
    {
        $order = Order::create(['order_number' => 'NO-STOCK', 'total_amount' => 40000, 'status' => 'pending']);
        $order->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'price' => 20000]);
        $this->post(route('orders.approve', $order))->assertSessionHasErrors('error');
        $this->assertDatabaseCount('order_audits', 0);
    }

    public function test_attachment_audit_only_records_successful_completion_once(): void
    {
        $order = $this->checkout();
        Storage::fake('order_attachments');
        $file = OrderAttachment::create([
            'id' => (string) Str::uuid(), 'order_id' => $order->id, 'user_id' => auth()->id(),
            'name' => 'slip.pdf', 'path' => 'test/slip.pdf', 'mime_type' => 'application/pdf', 'size' => 4,
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->postJson(route('orders.files.complete', [$order, $file]))->assertUnprocessable();
        $this->assertDatabaseCount('order_audits', 1);
        Storage::disk('order_attachments')->put($file->path, 'test');
        $this->postJson(route('orders.files.complete', [$order, $file]))->assertOk();
        $this->postJson(route('orders.files.complete', [$order, $file]))->assertOk();
        $entry = OrderAudit::where('version', 2)->sole();
        $this->assertSame('attachment_added', $entry->event);
        $this->assertSame('slip.pdf', $entry->snapshot['attachments'][$file->id]['name']);
        $this->assertArrayNotHasKey('path', $entry->snapshot['attachments'][$file->id]);
        $this->assertCount(1, $entry->changes);
        $this->assertDatabaseCount('order_audits', 2);
    }

    public function test_online_order_records_customer_actor_and_original_cart(): void
    {
        $customer = Customer::create(['name' => 'Online Customer', 'email' => 'online@example.test']);
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/spa/orders', ['cart' => [['id' => $this->product->id, 'count' => 1]]])->assertCreated();
        $entry = OrderAudit::sole();
        $this->assertSame('customer', $entry->actor_type);
        $this->assertSame($customer->id, $entry->actor_id);
        $this->assertSame('Online Customer', $entry->actor_name);
        $this->assertSame('pending', $entry->snapshot['status']);
        $this->assertSame(1, array_values($entry->snapshot['watches'])[0]['quantity']);
    }

    public function test_history_model_rejects_changes(): void
    {
        $this->checkout();
        $this->expectException(\LogicException::class);
        OrderAudit::sole()->update(['actor_name' => 'Rewritten']);
    }

    public function test_history_model_rejects_deletion(): void
    {
        $this->checkout();
        $this->expectException(\LogicException::class);
        OrderAudit::sole()->delete();
    }
}
