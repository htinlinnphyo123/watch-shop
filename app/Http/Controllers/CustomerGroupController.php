<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerGroupController extends Controller
{
    public function index()
    {
        return Inertia::render('CustomerGroups/Index', [
            'groups' => CustomerGroup::latest('updated_at')->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);
        
        CustomerGroup::create($request->all());
        return redirect()->back();
    }

    public function update(Request $request, CustomerGroup $customerGroup)
    {
        $request->validate([
            'name' => 'required|string',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        $customerGroup->update($request->all());
        return redirect()->back();
    }

    public function destroy(CustomerGroup $customerGroup)
    {
        $customerGroup->delete();
        return redirect()->back();
    }
}
