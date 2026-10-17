<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\CustomerPhone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosCustomerController extends Controller
{
    public function store(Request $request)
    {
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => CustomerPhone::normalize($request->input('phone'))]);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:255', 'regex:/^\+?[0-9]+$/'],
            'email' => 'nullable|email|max:255',
            'region' => ['nullable', 'string', Rule::in(Customer::REGIONS)],
            'address' => 'nullable|string|max:5000',
            'gender' => 'nullable|in:man,woman,other',
            'source' => ['nullable', Rule::in(array_keys(Customer::SOURCES))],
            'source_details' => 'nullable|string|max:2048',
        ]);

        // Compare legacy phone formatting too. Never merge identities without staff confirmation.
        $matches = Customer::withTrashed()->select(['id', 'name', 'phone', 'email', 'is_guest', 'deleted_at', 'customer_group_id'])
            ->get()->filter(fn ($customer) => CustomerPhone::normalize($customer->phone) === $data['phone']
                || (! empty($data['email']) && strcasecmp($customer->email ?? '', $data['email']) === 0));
        if ($matches->isNotEmpty()) {
            return response()->json([
                'message' => 'A customer already has this phone or email. Confirm the existing customer below.',
                'matches' => $matches->load('group')->values(),
            ], 409);
        }

        $data['is_guest'] = true;
        $data['customer_group_id'] = null;
        $data['password'] = Hash::make('password');
        try {
            $customer = Customer::create($data);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['phone' => 'This phone or email was just saved. Close this form and search for the existing customer.']);
        }

        return response()->json(['customer' => $customer->load('group')], 201);
    }

    public function promote(Customer $customer)
    {
        $customer->update(['is_guest' => false]);

        return response()->json(['customer' => $customer->load('group')]);
    }
}
