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
            'collections' => Collection::withCount('products')->latest('updated_at')->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
        ]);

        Collection::create($validated);
        return redirect()->back();
    }

    public function update(Request $request, Collection $collection)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
        ]);

        $collection->update($validated);
        return redirect()->back();
    }

    public function destroy(Collection $collection)
    {
        $collection->delete();
        return redirect()->back();
    }
}
