<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\WalletPaymentType;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletWorkbookExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class WalletExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Use an isolated in-memory database.');
        }
    }

    private function record(User $owner, array $data = []): WalletTransaction
    {
        return $owner->wallet->transactions()->create($data + [
            'created_by' => $owner->id, 'type' => 'debit', 'amount' => '25.50',
            'balance_after' => '-25.50', 'category' => 'delivery', 'payment_type' => 'kbz_pay',
            'created_at' => '2026-09-28 12:00:00',
        ]);
    }

    private function exportRows(array $filters = [], array $literalTexts = []): array
    {
        $response = $this->get(route('wallet.export', $filters))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $path = tempnam(sys_get_temp_dir(), 'wallet-export-');
        $xlsx = $path.'.xlsx';
        try {
            file_put_contents($xlsx, $response->streamedContent());
            $zip = new \ZipArchive();
            $zip->open($xlsx);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringNotContainsString('<f', $xml);
            $this->assertStringContainsString('Category', $xml);
            $this->assertStringContainsString('Payment Type', $xml);
            foreach ($literalTexts as $text) $this->assertStringContainsString($text, $xml);
            $zip->close();

            return (new FastExcel)->import($xlsx)->all();
        } finally {
            @unlink($xlsx);
            @unlink($path);
        }
    }

    public function test_expense_fields_create_edit_clear_and_preserve_balance_and_voucher(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);
        $data = ['type' => 'debit', 'amount' => '50.25', 'category' => 'bill', 'payment_type' => 'cash', 'description' => 'Electricity'];
        $this->post(route('wallet.transactions.store'), $data + ['attachment' => UploadedFile::fake()->create('voucher.pdf', 5, 'application/pdf')])->assertSessionHasNoErrors();
        $transaction = WalletTransaction::sole();
        $this->assertSame('bill', $transaction->category);
        $this->assertSame('cash', $transaction->payment_type);
        $this->assertSame('-50.25', $staff->wallet->fresh()->balance);
        $voucherPath = $transaction->attachment_path;
        Storage::disk('public')->assertExists($voucherPath);
        $this->get(route('wallet.index'))->assertInertia(fn (Assert $page) => $page
            ->where('categoryOptions', ExpenseCategory::options())
            ->where('paymentTypeOptions', WalletPaymentType::options())
            ->where('transactions.data.0.category', 'bill')
            ->where('transactions.data.0.payment_type', 'cash'));

        $this->put(route('wallet.transactions.update', $transaction), array_replace($data, ['amount' => '20.50', 'category' => 'shop', 'payment_type' => 'cb_pay']))->assertSessionHasNoErrors();
        $this->assertSame('shop', $transaction->fresh()->category);
        $this->assertSame('cb_pay', $transaction->fresh()->payment_type);
        $this->assertSame('-20.50', $staff->wallet->fresh()->balance);
        $this->assertSame($voucherPath, $transaction->fresh()->attachment_path);

        // Older clients omitting the new fields do not erase existing metadata.
        $this->put(route('wallet.transactions.update', $transaction), ['type' => 'debit', 'amount' => '20.50'])->assertSessionHasNoErrors();
        $this->assertSame('shop', $transaction->fresh()->category);
        $this->assertSame('cb_pay', $transaction->fresh()->payment_type);
        $this->put(route('wallet.transactions.update', $transaction), array_replace($data, ['category' => '', 'payment_type' => '']))->assertSessionHasNoErrors();
        $this->assertNull($transaction->fresh()->category);
        $this->assertNull($transaction->fresh()->payment_type);
    }

    public function test_enum_values_are_validated_and_staff_cannot_modify_other_wallets_or_add_credit(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);
        foreach (array_keys(ExpenseCategory::options()) as $category) {
            $this->post(route('wallet.transactions.store'), ['type' => 'debit', 'amount' => 1, 'category' => $category])->assertSessionHasNoErrors();
        }
        foreach (array_keys(WalletPaymentType::options()) as $type) {
            $this->post(route('wallet.transactions.store'), ['type' => 'debit', 'amount' => 1, 'payment_type' => $type])->assertSessionHasNoErrors();
        }
        $record = $staff->wallet->transactions()->first();
        foreach (['category', 'payment_type'] as $field) {
            $data = ['type' => 'debit', 'amount' => 1, $field => 'bad'];
            $this->post(route('wallet.transactions.store'), $data)->assertSessionHasErrors($field);
            $this->put(route('wallet.transactions.update', $record), $data)->assertSessionHasErrors($field);
        }
        $this->assertSame(10, $staff->wallet->transactions()->count());
        $this->assertSame('-10.00', $staff->wallet->fresh()->balance);
        $otherRecord = $this->record($other);
        $this->put(route('wallet.transactions.update', $otherRecord), ['type' => 'debit', 'amount' => 1, 'category' => 'bill'])->assertForbidden();
        $this->post(route('wallet.transactions.store'), ['type' => 'credit', 'amount' => 1, 'category' => 'bill'])->assertSessionHasErrors('type');
    }

    public function test_export_includes_labels_typed_amounts_dates_and_literal_text_only_for_own_wallet(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => '=1+1']);
        $other = User::factory()->create(['role' => 'staff']);
        $record = $this->record($staff, ['description' => '=SUM(A1:A2)', 'attachment_name' => 'voucher.pdf']);
        $this->record($other);
        $this->actingAs($staff);
        $rows = $this->exportRows(['user_id' => $other->id], ['=1+1', '=SUM(A1:A2)']);
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(WalletWorkbookExport::HEADERS, array_keys($row));
        $this->assertSame($record->id, $row['ID']);
        $this->assertSame('Delivery', $row['Category']);
        $this->assertSame('KBZ Pay', $row['Payment Type']);
        $this->assertSame('Out', $row['In / Out']);
        $this->assertSame(25.5, $row['Amount']);
        $this->assertSame(-25.5, $row['Balance After']);
        $this->assertSame('MMK', $row['Currency']);
        $this->assertSame('2026-09-28', $row['Date']->format('Y-m-d'));
        // FastExcel's reader reclassifies leading '=' strings; verify literal XML above.
        $this->assertSame('voucher.pdf', $row['Voucher']);

        $manager = User::factory()->create(['role' => 'manager']);
        $this->record($manager, ['category' => null, 'payment_type' => null]);
        $this->actingAs($manager);
        $rows = $this->exportRows(['user_id' => $staff->id]);
        $this->assertCount(1, $rows);
        $this->assertSame($manager->name, $rows[0]['User']);
        $this->assertSame('', $rows[0]['Category']);
        $this->assertSame('', $rows[0]['Payment Type']);
    }

    public function test_admin_export_matches_user_and_date_filters_including_all_pages_and_legacy_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        for ($i = 0; $i < 27; $i++) $this->record($staff);
        $this->record($staff, ['created_at' => '2026-09-27 23:59:59']);
        $this->record($staff, ['created_at' => '2026-09-29 00:00:00']);
        $this->record($admin, ['category' => null, 'payment_type' => null, 'type' => 'credit']);
        $this->actingAs($admin);
        $filters = ['user_id' => $staff->id, 'start_date' => '2026-09-28', 'end_date' => '2026-09-28'];
        $this->assertCount(27, $this->exportRows($filters));
        $this->get(route('wallet.index', $filters))->assertInertia(fn (Assert $page) => $page->where('transactions.total', 27)->has('transactions.data', 25));
        $this->assertCount(30, $this->exportRows());
        $row = $this->exportRows(['user_id' => $admin->id])[0];
        $this->assertSame('In', $row['In / Out']);
        $this->assertSame('', $row['Category']);
        $this->assertSame('', $row['Payment Type']);
        $this->assertCount(0, $this->exportRows(['start_date' => '2099-01-01']));
    }

    public function test_export_requires_authentication_and_valid_filters(): void
    {
        $this->get(route('wallet.export'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('wallet.export', ['start_date' => 'bad']))->assertSessionHasErrors('start_date');
        $this->get(route('wallet.export', ['start_date' => '2026-09-28', 'end_date' => '2026-09-27']))->assertSessionHasErrors('end_date');
        $this->get(route('wallet.export', ['user_id' => 99999]))->assertSessionHasErrors('user_id');
    }
}
