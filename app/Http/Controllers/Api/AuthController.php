<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Login Success',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user_info' => $user,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user =User::where('id', $request->id)->first();
        $user->currentAccessToken()->delete();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}
