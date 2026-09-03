<?php

namespace App\Http\Controllers;

use App\Models\LowStockNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LowStockNotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(LowStockNotification::STATUSES)],
        ]);

        $query = LowStockNotification::with(['product.brand'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('LowStockNotifications/Index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'statusCounts' => LowStockNotification::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'filters' => $request->only('status'),
        ]);
    }

    public function update(Request $request, LowStockNotification $lowStockNotification)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(LowStockNotification::STATUSES)],
        ]);

        $lowStockNotification->update($validated);

        return redirect()->back()->with('success', 'Notification status updated.');
    }
}
