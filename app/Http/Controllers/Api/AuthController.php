<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserShift;
use App\Models\Shift;
use App\Models\Terminal;
use Illuminate\Support\Facades\Hash;
use Exception;

class AuthController extends Controller
{
    // =========================
    // HELPER: Get terminal by client IP (Ethernet Adapter or fallback)
    // =========================
    private function getTerminalByEthernetIp()
{
    try {
        // Check Ethernet adapter status
        $adapterStatus = trim(shell_exec('powershell -Command "(Get-NetAdapter -Name \'Ethernet\').Status"'));

        if (strtolower($adapterStatus) !== 'up') {
            // Ethernet not connected -> fallback to static IP
            $clientIp = '192.168.1.4';
        } else {
            // Ethernet connected -> get IPv4
            $clientIp = trim(shell_exec('powershell -Command "(Get-NetIPAddress -InterfaceAlias \'Ethernet\' -AddressFamily IPv4 | Select-Object -First 1 -ExpandProperty IPAddress)"'));

            // If somehow IP not found, fallback
            if (!$clientIp) {
                $clientIp = '192.168.1.4';
            }
        }

        // Match terminal in DB
        return Terminal::where('ip_address', $clientIp)->first();

    } catch (Exception $e) {
        // On any error, fallback to default terminal IP
        return Terminal::where('ip_address', '192.168.1.4')->first();
    }
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

        // Casher must wait for active shift
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

        // Get terminal by Ethernet IPv4 or fallback
        $terminal = $this->getTerminalByEthernetIp();

        // Log shift login
        $shift = Shift::whereNull('shift_end')->latest('shift_start')->first();
        $userShiftData = null;

        try {
            if ($shift) {
                $userShift = UserShift::create([
                    'user_id'     => $user->id,
                    'shift_id'    => $shift->id,
                    'terminal_id' => $terminal?->id,
                    'login_at'    => now(),
                ]);

                $login = $userShift->login_at->copy()->timezone(config('app.timezone'));

                $userShiftData = [
                    'id' => $userShift->id,
                    'shift_id' => $userShift->shift_id,
                    'terminal' => $terminal ? [
                        'id' => $terminal->id,
                        'name' => $terminal->terminal_name,
                        'ip' => $terminal->ip_address,
                    ] : null,
                    'login_at_date' => $login->format('Y-m-d'),
                    'login_at_time' => $login->format('h:i A'),
                ];
            }
        } catch (Exception $e) {
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

    // final method
    //  public function login(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required|string'
    //     ]);

    //     $user = User::where('email', $request->email)->first();

    //     if (!$user || !Hash::check($request->password, $user->password)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Invalid Credentials'
    //         ], 401);
    //     }

    //     // Casher must wait for active shift
    //     if ($user->role === 'casher') {
    //         $activeShift = Shift::whereNull('shift_end')->latest('shift_start')->first();
    //         if (!$activeShift) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Shift has not started yet. Please wait for owner to start the shift.'
    //             ], 403);
    //         }
    //     }

    //     // Get terminal by Ethernet IPv4 or fallback
    //     $terminal = $this->getTerminalByEthernetIp();

    //     // If terminal null -> block login
    //     if (!$terminal) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Ethernet adapter not connected or terminal not found. Login cannot proceed.',
    //         ], 403);
    //     }

    //     // Delete previous tokens
    //     $user->tokens()->delete();

    //     $token = $user->createToken('api-token')->plainTextToken;

    //     // Log shift login
    //     $shift = Shift::whereNull('shift_end')->latest('shift_start')->first();
    //     $userShiftData = null;

    //     try {
    //         if ($shift) {
    //             $userShift = UserShift::create([
    //                 'user_id' => $user->id,
    //                 'shift_id' => $shift->id,
    //                 'terminal_id' => $terminal->id,
    //                 'login_at' => now(),
    //             ]);

    //             $login = $userShift->login_at->copy()->timezone(config('app.timezone'));

    //             $userShiftData = [
    //                 'id' => $userShift->id,
    //                 'shift_id' => $userShift->shift_id,
    //                 'terminal' => [
    //                     'id' => $terminal->id,
    //                     'name' => $terminal->terminal_name,
    //                     'ip' => $terminal->ip_address,
    //                 ],
    //                 'login_at_date' => $login->format('Y-m-d'),
    //                 'login_at_time' => $login->format('h:i A'),
    //             ];
    //         }
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Error creating login record',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }

    //     $created = $user->created_at->copy()->timezone(config('app.timezone'));
    //     $updated = $user->updated_at->copy()->timezone(config('app.timezone'));

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Login successful',
    //         'token' => $token,
    //         'role' => $user->role,
    //         'user' => [
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'email' => $user->email,
    //             'role' => $user->role,
    //             'date_created' => $created->format('Y-m-d'),
    //             'time_created' => $created->format('h:i A'),
    //             'date_updated' => $updated->format('Y-m-d'),
    //             'time_updated' => $updated->format('h:i A'),
    //         ],
    //         'user_shift' => $userShiftData
    //     ]);
    // }

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
                $terminal = $userShift->terminal;

                $userShiftData = [
                    'id' => $userShift->id,
                    'shift_id' => $userShift->shift_id,
                    'terminal' => $terminal ? [
                        'id' => $terminal->id,
                        'name' => $terminal->terminal_name,
                        'ip' => $terminal->ip_address,
                    ] : null,
                    'login_at_date' => $login->format('Y-m-d'),
                    'login_at_time' => $login->format('h:i A'),
                    'logout_at_date' => $logout->format('Y-m-d'),
                    'logout_at_time' => $logout->format('h:i A'),
                ];
            } catch (Exception $e) {
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
