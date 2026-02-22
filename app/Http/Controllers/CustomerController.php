<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        return Inertia::render('Customers/Index', [
            'customers' => Customer::with('group')->paginate(10),
            'groups' => CustomerGroup::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:customers',
            'phone' => 'nullable|string',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'address' => 'nullable|string',
        ]);

        Customer::create($validated);
        return redirect()->back();
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|string',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'address' => 'nullable|string',
        ]);

        $customer->update($validated);
        return redirect()->back();
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->back();
    }
}
