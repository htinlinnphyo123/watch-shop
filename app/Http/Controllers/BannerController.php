<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BannerController extends Controller
{
    public function index()
    {
        return Inertia::render('Banners/Index', [
            'banners' => Banner::orderBy('order')->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image',
            'title' => 'nullable|string',
            'link' => 'nullable|string',
            'order' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('banners', 'public');
        }

        Banner::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'image' => 'nullable|image',
            'title' => 'nullable|string',
            'link' => 'nullable|string',
            'order' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $validated['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($validated);

        return redirect()->back();
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        $banner->delete();
        return redirect()->back();
    }
}
