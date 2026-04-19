<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public function index()
    {
        return Inertia::render('Collections/Index', [
            'collections' => Collection::withCount('products')->orderBy('sort_order', 'asc')->latest('updated_at')->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'sort_order' => 'nullable|integer',
        ]);

        Collection::create($validated);
        return redirect()->back();
    }

    public function update(Request $request, Collection $collection)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'sort_order' => 'nullable|integer',
        ]);

        $collection->update($validated);
        return redirect()->back();
    }

    public function destroy(Collection $collection)
    {
        $collection->delete();
        return redirect()->back();
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:collections,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            \App\Models\Collection::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return redirect()->back();
    }
}
