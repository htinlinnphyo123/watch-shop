<?php

namespace Tests\Feature;

use App\Models\{Brand, Customer, Order, Product, PreOrder, User};
use App\Services\SalesWorkbookExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class SalesWorkbookExportTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') throw new \RuntimeException('Use an isolated in-memory database.');
    }

    private function readExport(string $route, array $params = []): array
    {
        $response = $this->get(route($route, $params))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $path = tempnam(sys_get_temp_dir(), 'sales-export-').'.xlsx';
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new \ZipArchive();
            $zip->open($path);
            $this->assertStringNotContainsString('<f', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
            return (new FastExcel)->import($path)->all();
        } finally {
            @unlink($path);
            @unlink(substr($path, 0, -5));
        }
    }

    public function test_sales_columns_match_template_and_staff_export_only_own_completed_sales(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($staff);
        $customer = Customer::create(['name' => 'Customer', 'source' => 'facebook', 'source_details' => 'Profile', 'gender' => 'woman', 'email' => 'sample@example.test', 'phone' => '09123456', 'address' => 'Yangon']);
        $brand = Brand::create(['name' => 'Test brand']);
        $product = Product::create(['name' => 'Watch', 'brand_id' => $brand->id, 'model_number' => '00123', 'gender' => 'Women', 'price' => 999]);
        $order = Order::create(['user_id' => $staff->id, 'customer_id' => $customer->id, 'order_number' => 'SAMPLE', 'status' => 'completed', 'total_amount' => 225, 'discount_percentage' => 10,
            'order_date' => '2026-09-20', 'order_type' => 'preorder', 'buying_type' => 'online', 'payment_method' => 'split', 'payments' => [['method' => 'cash', 'amount' => 50], ['method' => 'kbz_pay', 'amount' => 100]], 'amount_paid' => 150,
            'delivery_type' => 'Courier', 'delivery_code' => '001234', 'delivery_status' => 'Delivered', 'delivery_fees' => 5, 'money_transfer_amount' => 50, 'remark' => 'Call first']);
        $order->items()->create(['product_id' => $product->id, 'price' => 100, 'quantity' => 2]);
        $order->items()->create(['product_id' => $product->id, 'price' => 50, 'quantity' => 1]);
        foreach ([['user_id' => $admin->id, 'status' => 'completed'], ['user_id' => $staff->id, 'status' => 'cancelled'], ['user_id' => $staff->id, 'status' => 'pending']] as $index => $data) {
            Order::create($data + ['order_number' => 'EXCLUDED-'.$index, 'total_amount' => 100]);
        }
        $rows = $this->readExport('orders.export');
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(SalesWorkbookExport::SALES_HEADERS, array_keys($row));
        $this->assertCount(28, $row);
        $this->assertSame('Customer', $row['Customer Name']);
        $this->assertSame('Profile', $row['Fb Acc']);
        $this->assertSame('', $row['Marketing Channel']);
        $order->update(['marketing_channel' => 'Walk-in']);
        $this->assertSame('Walk-in', $this->readExport('orders.export')[0]['Marketing Channel']);
        $this->assertSame('Cash, Kpay', $row['Paid by']);
        $this->assertSame('Split', $row['Payment Type']);
        $this->assertSame('', $row['Pre-Order Payment']);
        $this->assertEquals(50, $row['Deposit']);
        $this->assertEquals(150, $row['Received Money']);
        $this->assertSame('09123456', $row['Phone']);
        $this->assertSame('001234', $row['Delivery ID']);
        $this->assertSame('Courier', $row['Delivery Type']);
        $this->assertSame('00123 (x2), 00123', $row['Model No.']);
        $this->assertEquals(250, $row['Price']);
        $this->assertEquals(0.1, $row['Discount']);
        $this->assertEquals(75, $row['Opening']);
        $this->assertEquals(225, $row['Total Payment']);
        $this->assertSame('Woman', $row['Customer Gender']);
        $this->assertSame('2026-09-20', $row['Order Date']->format('Y-m-d'));
        $this->assertCount(0, $this->readExport('orders.export', ['date_from' => '2099-01-01']));
        $this->assertCount(1, $this->readExport('orders.export', ['date_from' => '2026-09-20', 'date_to' => '2026-09-20']));
        $this->actingAs($admin);
        $this->assertCount(2, $this->readExport('orders.export'));
    }

    public function test_preorders_save_export_update_and_validate_template_fields_without_creating_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $brand = Brand::create(['name' => 'Strap brand']);
        $customer = Customer::create(['name' => 'Customer', 'email' => 'strap@example.test', 'source' => 'facebook', 'source_details' => 'FB name', 'address' => 'Address', 'phone' => '09123']);
        $payload = ['customer_id' => $customer->id, 'brand_id' => $brand->id, 'type' => 'pre_order', 'status' => 'pending', 'amount_paid' => 30000,
            'model_number' => 'AR11119', 'price' => 83000, 'discount_amount' => 0, 'deposit_amount' => 30000, 'order_date' => '2026-09-01', 'buying_type' => 'online',
            'paid_by' => 'Kpay', 'payment_type' => 'mbanking', 'delivery_type' => 'Courier', 'delivery_code' => '0009', 'delivery_status' => 'Pending', 'delivery_fees' => 0, 'money_transfer_amount' => 0];
        $this->post(route('pre-orders.store'), $payload)->assertSessionHasNoErrors();
        $record = PreOrder::sole();
        $this->assertNull($record->marketing_channel);
        $payload['marketing_channel'] = 'Walk-in';
        $this->put(route('pre-orders.update', $record), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Walk-in', $record->fresh()->marketing_channel);
        $this->get(route('pre-orders.index'))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('preOrders.data.0.marketing_channel', 'Walk-in'));
        $this->assertSame('83000.00', $record->price);
        $rows = $this->readExport('pre-orders.export', ['status' => 'pending']);
        $this->assertSame(SalesWorkbookExport::PRE_ORDER_HEADERS, array_keys($rows[0]));
        $this->assertCount(22, $rows[0]);
        $this->assertSame('FB name', $rows[0]['Fb Acc']);
        $this->assertEquals(53000, $rows[0]['Opening']);
        $this->assertEquals(83000, $rows[0]['Total Payment']);
        $this->assertEquals(0, $rows[0]['Received Money']);
        $this->assertSame('0009', $rows[0]['Delivery ID']);
        $this->assertSame('Pending', $rows[0]['Delivery Status']);
        $this->assertSame('Courier', $rows[0]['Delivery Type']);
        $this->assertCount(0, $this->readExport('pre-orders.export', ['status' => 'ordered']));
        $this->put(route('pre-orders.update', $record), array_replace($payload, ['discount_amount' => 8000, 'price' => 58000, 'deposit_amount' => 50000, 'delivery_type' => 'Shop Pickup']))->assertSessionHasNoErrors();
        $row = $this->readExport('pre-orders.export')[0];
        $this->assertEquals(50000, $row['Total Payment']);
        $this->assertSame('Shop Pickup', $row['Delivery Type']);
        $this->assertEquals(0, $row['Opening']);
        $this->put(route('pre-orders.update', $record), array_replace($payload, ['discount_amount' => 90000]))->assertSessionHasErrors('discount_amount');
        $this->put(route('pre-orders.update', $record), array_replace($payload, ['delivery_fees' => -1]))->assertSessionHasErrors('delivery_fees');
        $this->post(route('pre-orders.store'), array_replace($payload, ['delivery_status' => 'Anything']))->assertSessionHasErrors('delivery_status');
        $this->put(route('pre-orders.update', $record), array_replace($payload, ['delivery_status' => 'Anything']))->assertSessionHasErrors('delivery_status');
        $this->assertSame('Pending', $record->fresh()->delivery_status);
        $this->post(route('pre-orders.store'), array_replace($payload, ['delivery_type' => 'Anything']))->assertSessionHasErrors('delivery_type');
        $this->put(route('pre-orders.update', $record), array_replace($payload, ['delivery_type' => 'Anything']))->assertSessionHasErrors('delivery_type');
        $this->assertSame('Shop Pickup', $record->fresh()->delivery_type);
        $this->assertDatabaseCount('product_items', 0);
        $this->put(route('pre-orders.update', $record), array_replace($payload, ['marketing_channel' => str_repeat('x', 256)]))->assertSessionHasErrors('marketing_channel');
        $this->put(route('pre-orders.update', $record), array_replace($payload, ['marketing_channel' => '']))->assertSessionHasNoErrors();
        $this->assertNull($record->fresh()->marketing_channel);
        $this->post(route('pre-orders.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Walk-in', PreOrder::latest('id')->first()->marketing_channel);
    }

    public function test_customer_fields_and_pos_details_round_trip_and_are_audited(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->post(route('customers.store'), ['name' => 'Customer', 'email' => 'customer@example.test', 'source' => 'facebook', 'source_details' => 'Facebook profile', 'gender' => 'man'])->assertSessionHasNoErrors();
        $this->assertSame('Facebook profile', Customer::sole()->source_details);
        $brand = Brand::create(['name' => 'Watch brand']);
        $product = Product::create(['name' => 'Watch', 'brand_id' => $brand->id, 'price' => 100, 'currency' => 'MMK']);
        $product->items()->create(['status' => 'available']);
        $payload = ['customer_id' => Customer::sole()->id, 'status' => 'pending', 'cart' => [['product_id' => $product->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 0]],
            'order_date' => '2026-09-20', 'order_type' => 'instock', 'buying_type' => 'online', 'delivery_type' => 'Courier', 'delivery_status' => 'Pending', 'delivery_fees' => 10,
            // Old/stale clients must not persist the removed duplicate inputs.
            'paid_by' => 'Ignored', 'payment_type' => 'cod', 'pre_order_payment' => 80, 'deposit_amount' => 80, 'marketing_channel' => 'Walk-in'];
        $this->post(route('pos.checkout'), $payload)->assertSessionHasNoErrors();
        $order = Order::sole();
        foreach (['paid_by', 'payment_type', 'pre_order_payment', 'deposit_amount'] as $removed) {
            $this->assertArrayNotHasKey($removed, $order->getAttributes());
        }
        $this->assertSame('100.00', $order->total_amount);
        $this->assertSame('Walk-in', $order->marketing_channel);
        $this->get(route('pos.index', ['order_id' => $order->id]))->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('editingOrder.marketing_channel', 'Walk-in')->missing('editingOrder.paid_by')->missing('editingOrder.deposit_amount')->where('editingOrder.delivery_fees', '10.00'));
        $this->put(route('pos.orders.update', $order), array_replace($payload, ['edit_version' => 0, 'delivery_status' => 'Dispatched', 'delivery_type' => 'Shop Pickup', 'marketing_channel' => 'Referral']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_audits', ['order_id' => $order->id, 'event' => 'edited']);
        $audit = \App\Models\OrderAudit::where('order_id', $order->id)->latest('version')->first();
        $this->assertSame('Dispatched', $audit->snapshot['delivery_status']);
        $this->assertSame('Shop Pickup', $audit->snapshot['delivery_type']);
        $this->assertSame('Referral', $audit->snapshot['marketing_channel']);
        $this->assertSame('Referral', $order->fresh()->marketing_channel);
        $this->post(route('pos.checkout'), array_replace($payload, ['marketing_channel' => ['invalid']]))->assertSessionHasErrors('marketing_channel');
        $this->put(route('pos.orders.update', $order), array_replace($payload, ['edit_version' => 1, 'marketing_channel' => str_repeat('x', 256)]))->assertSessionHasErrors('marketing_channel');
        $this->put(route('pos.orders.update', $order), array_replace($payload, ['edit_version' => 1, 'buying_type' => 'bad']))->assertSessionHasErrors('buying_type');
        $this->post(route('pos.checkout'), array_replace($payload, ['delivery_status' => 'Anything']))->assertSessionHasErrors('delivery_status');
        $this->put(route('pos.orders.update', $order), array_replace($payload, ['edit_version' => 1, 'delivery_status' => 'Anything']))->assertSessionHasErrors('delivery_status');
        $this->assertSame('Dispatched', $order->fresh()->delivery_status);
        $this->post(route('pos.checkout'), array_replace($payload, ['delivery_type' => 'Anything']))->assertSessionHasErrors('delivery_type');
        $this->put(route('pos.orders.update', $order), array_replace($payload, ['edit_version' => 1, 'delivery_type' => 'Anything']))->assertSessionHasErrors('delivery_type');
        $this->assertSame('Shop Pickup', $order->fresh()->delivery_type);
        $this->put(route('pos.orders.update', $order), array_replace($payload, ['edit_version' => 1, 'marketing_channel' => '']))->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->marketing_channel);
    }

    public function test_marketing_channel_options_and_validation_share_one_enum(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $options = \App\Enums\MarketingChannel::options();
        foreach (['pos.index', 'pre-orders.index'] as $route) {
            $this->get(route($route))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('marketingChannelOptions', $options));
        }
        foreach ([false, true] as $preOrder) {
            $rules = ['marketing_channel' => \App\Support\SalesRecordFields::rules($preOrder)['marketing_channel']];
            foreach ([...array_keys($options), null, ''] as $channel) {
                $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['marketing_channel' => $channel], $rules)->passes());
            }
            foreach (['Anything', 'facebook', ['Facebook']] as $channel) {
                $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['marketing_channel' => $channel], $rules)->fails());
            }
        }
        $order = Order::create(['order_number' => 'LEGACY-MARKETING', 'total_amount' => 0, 'marketing_channel' => 'Old campaign']);
        $this->assertSame('Old campaign', $order->fresh()->marketing_channel);
        $this->assertSame('Old campaign', app(SalesWorkbookExport::class)->saleRow($order->fresh())['Marketing Channel']);
    }

    public function test_delivery_type_options_and_validation_share_one_enum(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $options = \App\Enums\DeliveryType::options();
        foreach (['pos.index', 'pre-orders.index'] as $route) {
            $this->get(route($route))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('deliveryTypeOptions', $options));
        }
        foreach ([false, true] as $preOrder) {
            $rules = ['delivery_type' => \App\Support\SalesRecordFields::rules($preOrder)['delivery_type']];
            foreach ([...array_keys($options), null, ''] as $type) {
                $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['delivery_type' => $type], $rules)->passes());
            }
            foreach (['Anything', 'courier', ['Courier']] as $type) {
                $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['delivery_type' => $type], $rules)->fails());
            }
        }
        $order = Order::create(['order_number' => 'LEGACY-DELIVERY-TYPE', 'total_amount' => 0, 'delivery_type' => 'Old courier service']);
        $this->assertSame('Old courier service', $order->fresh()->delivery_type);
        $this->assertSame('Old courier service', app(SalesWorkbookExport::class)->saleRow($order->fresh())['Delivery Type']);
    }

    public function test_delivery_status_options_and_validation_share_one_enum(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $options = \App\Enums\DeliveryStatus::options();
        foreach (['pos.index', 'pre-orders.index'] as $route) {
            $this->get(route($route))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('deliveryStatusOptions', $options));
        }
        foreach ([false, true] as $preOrder) {
            $rules = ['delivery_status' => \App\Support\SalesRecordFields::rules($preOrder)['delivery_status']];
            foreach ([...array_keys($options), null, ''] as $status) {
                $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['delivery_status' => $status], $rules)->passes());
            }
            foreach (['Anything', 'delivered', ['Pending']] as $status) {
                $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['delivery_status' => $status], $rules)->fails());
            }
        }
        // Do not cast legacy free text into an enum: old records must remain readable.
        $order = Order::create(['order_number' => 'LEGACY-DELIVERY', 'total_amount' => 0, 'delivery_status' => 'Old courier note']);
        $this->assertSame('Old courier note', $order->fresh()->delivery_status);
        $this->assertSame('Old courier note', app(SalesWorkbookExport::class)->saleRow($order->fresh())['Delivery Status']);
    }

    public function test_sales_receipts_handle_change_legacy_payments_and_do_not_add_transfer_twice(): void
    {
        $export = app(SalesWorkbookExport::class);
        foreach ([
            [['payments' => [['method' => 'cash', 'amount' => 120]], 'amount_paid' => 120, 'payment_method' => 'cash'], 100, 0, 'Cash'],
            [['payments' => [['method' => 'cash', 'amount' => 0]], 'amount_paid' => 0, 'payment_method' => 'cash'], 0, 100, 'Cash'],
            [['payments' => null, 'amount_paid' => 60, 'payment_method' => 'transfer'], 60, 40, 'Mbanking'],
            [['payments' => null, 'amount_paid' => null, 'payment_method' => null], null, null, null],
        ] as [$payments, $received, $opening, $type]) {
            $order = new Order($payments + ['total_amount' => 100, 'money_transfer_amount' => 80]);
            $order->setRelation('items', collect())->setRelation('customer', null)->setRelation('user', null);
            $row = $export->saleRow($order);
            $this->assertEquals($received, $row['Received Money']);
            $this->assertEquals($opening, $row['Opening']);
            $this->assertEquals(80, $row['Deposit']);
            $this->assertSame($type, $row['Payment Type']);
            $this->assertNull($row['Pre-Order Payment']);
        }
    }

    public function test_formula_like_text_remains_literal_in_excel(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sales-literal-');
        try {
            app(SalesWorkbookExport::class)->write($path, [['Customer Name' => '=1+1']]);
            $zip = new \ZipArchive();
            $zip->open($path);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringNotContainsString('<f', $xml);
            $this->assertStringContainsString('=1+1', $xml);
            $this->assertStringContainsString('t="inlineStr"', $xml);
            $zip->close();
        } finally {
            unlink($path);
        }
    }
}
