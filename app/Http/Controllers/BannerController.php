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
        $type = $request->input('type', 'image');
        $mediaRule = $type === 'video'
            ? 'required|file|mimes:mp4,mov,avi,wmv,webm,flv,mkv|max:102400'
            : 'required|file|mimes:jpg,jpeg,png,webp,gif,svg|max:20480';

        $validated = $request->validate([
            'title' => 'nullable|string',
            'type' => 'required|in:image,video',
            'image' => $mediaRule,
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
        $type = $request->input('type', $banner->type ?? 'image');
        $mediaRule = $type === 'video'
            ? 'nullable|file|mimes:mp4,mov,avi,wmv,webm,flv,mkv|max:102400'
            : 'nullable|file|mimes:jpg,jpeg,png,webp,gif,svg|max:20480';

        $validated = $request->validate([
            'title' => 'nullable|string',
            'type' => 'required|in:image,video',
            'image' => $mediaRule,
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
