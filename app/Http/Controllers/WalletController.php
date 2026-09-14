<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin';
        $rules = [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];

        if ($isAdmin) {
            $rules['user_id'] = ['nullable', 'integer', Rule::exists('users', 'id')];
        }

        $validated = $request->validate($rules);

        if ($isAdmin) {
            $transactions = WalletTransaction::with(['wallet.user:id,name,email,role', 'createdBy:id,name'])
                ->latest();

            if (! empty($validated['user_id'])) {
                $transactions->whereHas('wallet', function ($query) use ($validated) {
                    $query->where('user_id', $validated['user_id']);
                });
            }

            $this->applyDateFilters($transactions, $validated);
            $outTotal = (clone $transactions)->where('type', 'debit')->sum('amount');

            return Inertia::render('Wallets/Index', [
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

        $transactions = $wallet->transactions()->with('createdBy:id,name')->latest();
        $this->applyDateFilters($transactions, $validated);
        $outTotal = (clone $transactions)->where('type', 'debit')->sum('amount');

        return Inertia::render('Wallets/Index', [
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
            'user_id' => [Rule::requiredIf($isAdmin), 'nullable', 'integer', Rule::exists('users', 'id')],
            'type' => ['required', Rule::in($isAdmin ? ['credit', 'debit'] : ['debit'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $actorId = $request->user()->getKey();
        $walletOwnerId = $isAdmin ? $validated['user_id'] : $actorId;

        DB::transaction(function () use ($validated, $actorId, $walletOwnerId) {
            $wallet = $this->lockedWalletForUser($walletOwnerId);
            $wallet->transactions()->create([
                'created_by' => $actorId,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'balance_after' => 0,
                'description' => $validated['description'] ?? null,
            ]);

            $this->recalculateWallet($wallet);
        });

        return redirect()->back()->with('success', 'Wallet transaction recorded.');
    }

    public function updateTransaction(Request $request, WalletTransaction $walletTransaction)
    {
        $this->authorizeTransactionAccess($request, $walletTransaction);

        $validated = $request->validate([
            'type' => ['required', Rule::in($request->user()->role === 'admin' ? ['credit', 'debit'] : ['debit'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($walletTransaction, $validated) {
            $wallet = $this->lockedWalletForUser($walletTransaction->wallet->user_id);
            $walletTransaction->update([
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->recalculateWallet($wallet);
        });

        return redirect()->back()->with('success', 'Wallet transaction updated.');
    }

    public function destroyTransaction(Request $request, WalletTransaction $walletTransaction)
    {
        $this->authorizeTransactionAccess($request, $walletTransaction);

        DB::transaction(function () use ($walletTransaction) {
            $wallet = $this->lockedWalletForUser($walletTransaction->wallet->user_id);
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
