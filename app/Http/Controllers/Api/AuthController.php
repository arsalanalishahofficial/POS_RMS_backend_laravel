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
    // =========================
    // REGISTER USER
    // =========================
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

        $created = $user->created_at->copy()->timezone(config('app.timezone'));
        $updated = $user->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'User Registered Successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ], 201);
    }

    // =========================
    // LOGIN
    // =========================
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

        // Casher must wait for shift
        if ($user->role === 'casher') {
            $activeShift = Shift::whereNull('shift_end')->latest('shift_start')->first();
            if (!$activeShift) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shift has not started yet. Please wait for owner to start the shift.'
                ], 403);
            }
        }

        // Delete previous tokens
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        // Log shift login
        $shift = Shift::whereNull('shift_end')->latest('shift_start')->first();
        $userShiftData = null;

        try {
            if ($shift) {
                $userShift = UserShift::create([
                    'user_id' => $user->id,
                    'shift_id' => $shift->id,
                    'login_at' => now(),
                ]);

                $login = $userShift->login_at->copy()->timezone(config('app.timezone'));

                $userShiftData = [
                    'id' => $userShift->id,
                    'shift_id' => $userShift->shift_id,
                    'login_at_date' => $login->format('Y-m-d'),
                    'login_at_time' => $login->format('h:i A'),
                ];
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error creating login record',
                'error' => $e->getMessage()
            ], 500);
        }

        $created = $user->created_at->copy()->timezone(config('app.timezone'));
        $updated = $user->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'role' => $user->role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ],
            'user_shift' => $userShiftData
        ]);
    }

    // =========================
    // LOGOUT
    // =========================
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found or not authenticated'
            ], 401);
        }

        // Delete current token
        $user->currentAccessToken()?->delete();

        // Update logout_at
        $userShift = UserShift::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first();

        $userShiftData = null;
        if ($userShift) {
            try {
                $userShift->update(['logout_at' => now()]);

                $login = $userShift->login_at->copy()->timezone(config('app.timezone'));
                $logout = $userShift->logout_at->copy()->timezone(config('app.timezone'));

                $userShiftData = [
                    'id' => $userShift->id,
                    'shift_id' => $userShift->shift_id,
                    'login_at_date' => $login->format('Y-m-d'),
                    'login_at_time' => $login->format('h:i A'),
                    'logout_at_date' => $logout->format('Y-m-d'),
                    'logout_at_time' => $logout->format('h:i A'),
                ];
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
            'message' => 'Logged out successfully',
            'user_shift' => $userShiftData
        ]);
    }
}
