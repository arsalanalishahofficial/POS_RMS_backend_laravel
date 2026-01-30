<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserShift;
use App\Models\Shift;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:owner,casher,manager,waiter,kitchen_staff,store_manager,accountant,branch_manager'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => strtolower($request->role),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User Registered Successfully',
            'user' => $user
        ], 201);
    }

   
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Credentials'
            ], 401);
        }

        if ($user->role === 'casher') {
            $activeShift = Shift::whereNull('shift_end')->latest('shift_start')->first();
            if (!$activeShift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift has not started yet. Please wait for owner to start the shift.'
                ], 403);
            }
        }

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        $shift = Shift::whereNull('shift_end')->latest('shift_start')->first();

        try {
            if ($shift) { 
                UserShift::create([
                    'user_id' => $user->id,
                    'shift_id' => $shift->id,
                    'login_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error creating login record',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'role' => $user->role,
            'user' => $user
        ]);
    }

 
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found or not authenticated'
            ], 401);
        }

   
        $user->currentAccessToken()?->delete();

        $userShift = UserShift::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first();

        if ($userShift) {
            try {
                $userShift->update([
                    'logout_at' => now()
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error updating logout time',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
