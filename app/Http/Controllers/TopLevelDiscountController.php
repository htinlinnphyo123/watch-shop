<?php

namespace App\Http\Controllers;

use App\Models\TopLevelDiscount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TopLevelDiscountController extends Controller
{
    public function index()
    {
        return Inertia::render('TopLevelDiscounts/Index', [
            'discounts' => TopLevelDiscount::orderBy('amount', 'asc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        TopLevelDiscount::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, TopLevelDiscount $topLevelDiscount)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        $topLevelDiscount->update($validated);

        return redirect()->back();
    }

    public function destroy(TopLevelDiscount $topLevelDiscount)
    {
        $topLevelDiscount->delete();
        return redirect()->back();
    }
}
