<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Setting;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'usd_rate' => 'required|numeric|min:0',
            'thb_rate' => 'required|numeric|min:0',
            'sgd_rate' => 'required|numeric|min:0',
            'cny_rate' => 'required|numeric|min:0',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->back()->with('success', 'Exchange rates updated successfully.');
    }
}
