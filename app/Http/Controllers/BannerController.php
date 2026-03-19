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
            'banners' => Banner::latest('updated_at')->paginate(10),
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
            $validated['image'] = $request->file('image')->store('banners', env('FILESYSTEM_DISK', 's3'));
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

        $dataToUpdate = collect($validated)->except('image')->toArray();

        if ($request->hasFile('image')) {
            if ($banner->image) {
                Storage::disk(env('FILESYSTEM_DISK', 's3'))->delete($banner->image);
            }
            $dataToUpdate['image'] = $request->file('image')->store('banners', env('FILESYSTEM_DISK', 's3'));
        }

        $banner->update($dataToUpdate);

        return redirect()->back();
    }

    public function destroy(Banner $banner)
    {
        // Soft delete — do NOT remove the image so it can be restored later.
        $banner->delete();
        return redirect()->back();
    }
}
