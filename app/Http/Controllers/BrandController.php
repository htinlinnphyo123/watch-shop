<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function index()
    {
        return Inertia::render('Brands/Index', [
            'brands' => Brand::withCount('products')->latest('updated_at')->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'website' => 'nullable|url',
            'logo' => 'nullable|image|max:1024',
            'bg_logo' => 'nullable|image|max:1024',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('brands', env('FILESYSTEM_DISK', 's3'));
        }
        
        if ($request->hasFile('bg_logo')) {
            $validated['bg_logo'] = $request->file('bg_logo')->store('brands/bg', env('FILESYSTEM_DISK', 's3'));
        }

        Brand::create($validated);
        return redirect()->back();
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required',
            'website' => 'nullable|url',
            'logo' => 'nullable|image|max:1024',
            'bg_logo' => 'nullable|image|max:1024',
        ]);

        if ($request->hasFile('logo')) {
             if ($brand->logo) {
                // simple cleanup if needed, or just overwrite reference
            }
            $validated['logo'] = $request->file('logo')->store('brands', env('FILESYSTEM_DISK', 's3'));
        } else {
            unset($validated['logo']);
        }
        
        if ($request->hasFile('bg_logo')) {
            if ($brand->bg_logo) {
                // cleanup logic if needed
            }
            $validated['bg_logo'] = $request->file('bg_logo')->store('brands/bg', env('FILESYSTEM_DISK', 's3'));
        } else {
            unset($validated['bg_logo']);
        }

        $brand->update($validated);
        return redirect()->back();
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();
        return redirect()->back();
    }
}
