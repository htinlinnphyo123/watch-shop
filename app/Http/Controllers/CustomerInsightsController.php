<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerInsightsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerInsightsController extends Controller
{
    public function dashboard(Request $request, CustomerInsightsService $insights)
    {
        if ($request->user()->role !== 'admin') {
            return Inertia::render('Dashboard');
        }

        return Inertia::render('Dashboard', $this->data($request, $insights));
    }

    public function index(Request $request, CustomerInsightsService $insights)
    {
        return Inertia::render('Customers/Leaderboard', $this->data($request, $insights));
    }

    private function data(Request $request, CustomerInsightsService $insights): array
    {
        $filters = array_merge([
            'from' => now()->subDays(89)->toDateString(),
            'to' => now()->toDateString(),
            'rank_by' => 'value',
        ], $request->validate([
            'from' => 'sometimes|required|date_format:Y-m-d',
            'to' => 'sometimes|required|date_format:Y-m-d',
            'rank_by' => 'sometimes|required|in:value,orders',
            'page' => 'sometimes|integer|min:1',
        ]));
        $days = Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to']), false);
        if ($days < 0 || $days > 365) {
            throw ValidationException::withMessages(['to' => 'Choose an end date on or after the start date, within 366 days.']);
        }

        return ['report' => $insights->report($filters), 'filters' => $filters, 'sourceOptions' => Customer::SOURCES];
    }
}
