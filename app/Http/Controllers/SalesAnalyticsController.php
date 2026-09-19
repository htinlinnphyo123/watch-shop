<?php

namespace App\Http\Controllers;

use App\Services\SalesAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SalesAnalyticsController extends Controller
{
    public function __invoke(Request $request, SalesAnalyticsService $analytics)
    {
        $today = now()->toDateString();
        $filters = array_merge(['from' => now()->subDays(29)->toDateString(), 'to' => $today], $request->validate([
            'from' => 'sometimes|required|date_format:Y-m-d',
            'to' => 'sometimes|required|date_format:Y-m-d|before_or_equal:'.$today,
        ]));
        $days = Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to']), false);
        if ($days < 0 || $days > 365) {
            throw ValidationException::withMessages(['to' => 'Choose an end date on or after the start date, within 366 days.']);
        }

        return Inertia::render('Sales/Analytics', [
            'report' => $analytics->report($filters), 'filters' => $filters, 'today' => $today,
            'presets' => [
                ['label' => 'Today', 'from' => $today, 'to' => $today],
                ['label' => 'Last 7 days', 'from' => now()->subDays(6)->toDateString(), 'to' => $today],
                ['label' => 'Last 30 days', 'from' => now()->subDays(29)->toDateString(), 'to' => $today],
                ['label' => 'This month', 'from' => now()->startOfMonth()->toDateString(), 'to' => $today],
            ],
        ]);
    }
}
