<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\WalletPaymentType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin';
        $validated = $this->validatedFilters($request);
        $transactions = $this->transactionQuery($request, $validated);
        $outTotal = (clone $transactions)->where('type', 'debit')->sum('amount');
        $fieldOptions = [
            'categoryOptions' => ExpenseCategory::options(),
            'paymentTypeOptions' => WalletPaymentType::options(),
        ];

        if ($isAdmin) {
            return Inertia::render('Wallets/Index', [
                ...$fieldOptions,
                'isAdmin' => true,
                'wallets' => Wallet::with('user:id,name,email,role')->orderByDesc('balance')->paginate(20, ['*'], 'wallet_page')->withQueryString(),
                'transactions' => $transactions
                    ->paginate(25, ['*'], 'transaction_page')
                    ->withQueryString(),
                'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role']),
                'currentWallet' => null,
                'filters' => $request->only(['user_id', 'start_date', 'end_date']),
                'summary' => [
                    'out_total' => $outTotal,
                ],
            ]);
        }

        $wallet = $request->user()->wallet()->firstOrCreate([], [
            'balance' => 0,
            'currency' => 'MMK',
        ]);

        return Inertia::render('Wallets/Index', [
            ...$fieldOptions,
            'isAdmin' => false,
            'wallets' => null,
            'transactions' => $transactions->paginate(25)->withQueryString(),
            'users' => [],
            'currentWallet' => $wallet,
            'filters' => $request->only(['start_date', 'end_date']),
            'summary' => [
                'out_total' => $outTotal,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $transactions = $this->transactionQuery($request, $this->validatedFilters($request));

        return app(\App\Services\WalletWorkbookExport::class)->download($transactions->lazy(500));
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            ...($request->user()->role === 'admin'
                ? ['user_id' => ['nullable', 'integer', Rule::exists('users', 'id')]] : []),
        ]);
    }

    private function transactionQuery(Request $request, array $filters)
    {
        $query = WalletTransaction::with(['wallet.user:id,name,email,role', 'createdBy:id,name'])
            ->latest()->latest('id');
        $ownerId = $request->user()->role === 'admin' ? ($filters['user_id'] ?? null) : $request->user()->id;
        if ($ownerId) {
            $query->whereHas('wallet', fn ($wallet) => $wallet->where('user_id', $ownerId));
        }
        $this->applyDateFilters($query, $filters);

        return $query;
    }

    private function expenseFieldRules(): array
    {
        return [
            'category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'payment_type' => ['nullable', Rule::enum(WalletPaymentType::class)],
        ];
    }

    private function applyDateFilters($query, array $filters): void
    {
        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }
    }

    public function storeTransaction(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin';
        $validated = $request->validate([
            ...$this->expenseFieldRules(),
            'user_id' => [Rule::requiredIf($isAdmin), 'nullable', 'integer', Rule::exists('users', 'id')],
            'type' => ['required', Rule::in($isAdmin ? ['credit', 'debit'] : ['debit'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $actorId = $request->user()->getKey();
        $walletOwnerId = $isAdmin ? $validated['user_id'] : $actorId;

        DB::transaction(function () use ($validated, $actorId, $walletOwnerId, $request) {
            $wallet = $this->lockedWalletForUser($walletOwnerId);
            $data = [
                'created_by' => $actorId,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'balance_after' => 0,
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? null,
                'payment_type' => $validated['payment_type'] ?? null,
            ];
            if ($request->hasFile('attachment')) {
                $data['attachment_path'] = $request->file('attachment')->store('wallet-vouchers', 'public');
                $data['attachment_name'] = $request->file('attachment')->getClientOriginalName();
            }
            $wallet->transactions()->create($data);

            $this->recalculateWallet($wallet);
        });

        return redirect()->back()->with('success', 'Wallet transaction recorded.');
    }

    public function updateTransaction(Request $request, WalletTransaction $walletTransaction)
    {
        $this->authorizeTransactionAccess($request, $walletTransaction);

        $validated = $request->validate([
            ...$this->expenseFieldRules(),
            'type' => ['required', Rule::in($request->user()->role === 'admin' ? ['credit', 'debit'] : ['debit'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        DB::transaction(function () use ($walletTransaction, $validated, $request) {
            $wallet = $this->lockedWalletForUser($walletTransaction->wallet->user_id);
            $walletTransaction->update([
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                ...array_intersect_key($validated, array_flip(['category', 'payment_type'])),
            ]);
            if ($request->hasFile('attachment')) {
                if ($walletTransaction->attachment_path) Storage::disk('public')->delete($walletTransaction->attachment_path);
                $walletTransaction->update([
                    'attachment_path' => $request->file('attachment')->store('wallet-vouchers', 'public'),
                    'attachment_name' => $request->file('attachment')->getClientOriginalName(),
                ]);
            }

            $this->recalculateWallet($wallet);
        });

        return redirect()->back()->with('success', 'Wallet transaction updated.');
    }

    public function attachment(Request $request, WalletTransaction $walletTransaction)
    {
        $this->authorizeTransactionAccess($request, $walletTransaction);
        abort_unless($walletTransaction->attachment_path && Storage::disk('public')->exists($walletTransaction->attachment_path), 404);
        return Storage::disk('public')->download($walletTransaction->attachment_path, $walletTransaction->attachment_name);
    }

    public function destroyTransaction(Request $request, WalletTransaction $walletTransaction)
    {
        $this->authorizeTransactionAccess($request, $walletTransaction);

        DB::transaction(function () use ($walletTransaction) {
            $wallet = $this->lockedWalletForUser($walletTransaction->wallet->user_id);
            if ($walletTransaction->attachment_path) Storage::disk('public')->delete($walletTransaction->attachment_path);
            $walletTransaction->delete();
            $this->recalculateWallet($wallet);
        });

        return redirect()->back()->with('success', 'Wallet transaction deleted.');
    }

    private function authorizeTransactionAccess(Request $request, WalletTransaction $walletTransaction): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        $isOwnOutRecord = $walletTransaction->wallet->user_id === $request->user()->id
            && $walletTransaction->created_by === $request->user()->id
            && $walletTransaction->type === 'debit';

        if (! $isOwnOutRecord) {
            abort(403, 'You can only manage Out records that you created.');
        }
    }

    private function lockedWalletForUser(int $userId): Wallet
    {
        return Wallet::where('user_id', $userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recalculateWallet(Wallet $wallet): void
    {
        $balanceInMinorUnits = 0;
        $transactions = $wallet->transactions()
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($transactions as $transaction) {
            $amountInMinorUnits = (int) round(((float) $transaction->amount) * 100);
            $balanceInMinorUnits += $transaction->type === 'credit'
                ? $amountInMinorUnits
                : -$amountInMinorUnits;

            // if ($balanceInMinorUnits < 0) {
            //     throw ValidationException::withMessages([
            //         'amount' => 'This change would make the wallet balance negative.',
            //     ]);
            // }

            // if ($balanceInMinorUnits > 999999999999999) {
            //     throw ValidationException::withMessages([
            //         'amount' => 'The resulting wallet balance is too large.',
            //     ]);
            // }

            $transaction->updateQuietly([
                'balance_after' => $balanceInMinorUnits / 100,
            ]);
        }

        $wallet->update(['balance' => $balanceInMinorUnits / 100]);
    }
}
