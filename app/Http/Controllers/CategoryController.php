<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['parent', 'children'])->withCount('products');

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
        $request->validate([
            'name' => 'required',
            'parent_id' => 'nullable|exists:categories,id',
        ]);
        Category::create($request->all());
        return redirect()->back();
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required',
            'parent_id' => 'nullable|exists:categories,id',
        ]);
        $category->update($request->all());
        return redirect()->back();
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->back();
    }
}
