<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['parent', 'children'])->withCount('products')->orderBy('sort_order', 'asc')->latest('updated_at');

        if ($request->has('parent_id') && $request->parent_id !== 'all') {
            if ($request->parent_id === 'top_level') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        return Inertia::render('Categories/Index', [
            'categories' => $query->paginate(10)->withQueryString(),
            'all_categories' => Category::all(),
            'filters' => $request->only('parent_id'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'photo' => 'nullable|image',
            'sort_order' => 'nullable|integer',
        ]);
        
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('categories', env('FILESYSTEM_DISK', 's3'));
        }
        
        Category::create($validated);
        return redirect()->back();
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'photo' => 'nullable|image',
            'sort_order' => 'nullable|integer',
        ]);

        if ($request->hasFile('photo')) {
            // New photo uploaded — delete the old one from storage first
            if ($category->photo) {
                Storage::disk(env('FILESYSTEM_DISK', 's3'))->delete($category->photo);
            }
            $validated['photo'] = $request->file('photo')->store('categories', env('FILESYSTEM_DISK', 's3'));
        } else {
            // No new photo — remove the key entirely so the existing photo is NOT overwritten
            unset($validated['photo']);
        }

        $category->update($validated);
        return redirect()->back();
    }

    public function toggleShow(Category $category)
    {
        $category->update([
            'is_show' => !$category->is_show,
        ]);

        return redirect()->back()->with('success', 'Category visibility updated.');
    }

    public function destroy(Category $category)
    {
        // Soft delete — do NOT remove the photo file so it can be restored later.
        $category->delete();
        return redirect()->back();
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:categories,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            Category::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return redirect()->back();
    }
}
