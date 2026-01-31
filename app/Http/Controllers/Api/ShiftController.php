<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Shift;
use App\Models\ShiftPause;
use App\Models\UserShift;

class ShiftController extends Controller
{
    // =========================
    // HELPER: FORMAT SHIFT
    // =========================
    private function formatShift(Shift $shift)
    {
        return [
            'id' => $shift->id,
            'shift_start' => $shift->shift_start,
            'shift_end' => $shift->shift_end,
            'is_paused' => $shift->is_paused,
            'date_created' => $shift->created_at->format('Y-m-d'),
            'time_created' => $shift->created_at->format('h:i A'),
            'date_updated' => $shift->updated_at->format('Y-m-d'),
            'time_updated' => $shift->updated_at->format('h:i A'),
        ];
    }

    // =========================
    // START SHIFT
    // =========================
    public function startShift()
    {
        $shift = Shift::whereNull('shift_end')->first();

        if ($shift) {
            return response()->json([
                'status' => true,
                'message' => 'Shift already started',
                'data' => $this->formatShift($shift)
            ]);
        }

        $shift = Shift::create(['shift_start' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'Shift started successfully',
            'data' => $this->formatShift($shift)
        ]);
    }

    // =========================
    // CLOSE SHIFT
    // =========================
    public function closeShift()
    {
        $shift = Shift::whereNull('shift_end')->first();

        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'No active shift to close',
            ], 400);
        }

        $shift->update([
            'shift_end' => now(),
            'is_paused' => false,
        ]);

        // Resume last pause if still paused
        $lastPause = $shift->pauses()->latest()->first();
        if ($lastPause && !$lastPause->resumed_at) {
            $lastPause->update(['resumed_at' => now()]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift closed successfully',
            'data' => $this->formatShift($shift)
        ]);
    }

    // =========================
    // PAUSE SHIFT
    // =========================
    public function pauseShift(Request $request)
    {
        $request->validate(['manager_password' => 'required|string']);

        $manager = auth()->user();

        if ($manager->role !== 'manager' || !Hash::check($request->manager_password, $manager->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $shift = Shift::whereNull('shift_end')->first();
        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'No active shift to pause',
            ], 400);
        }

        $shift->update(['is_paused' => true]);
        $shift->pauses()->create(['paused_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'Shift paused',
            'data' => $this->formatShift($shift)
        ]);
    }

    // =========================
    // RESUME SHIFT
    // =========================
    public function resumeShift(Request $request)
    {
        $request->validate(['manager_password' => 'required|string']);

        $manager = auth()->user();

        if ($manager->role !== 'manager' || !Hash::check($request->manager_password, $manager->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $shift = Shift::whereNull('shift_end')->first();
        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'No active shift to resume',
            ], 400);
        }

        $shift->update(['is_paused' => false]);

        $lastPause = $shift->pauses()->latest()->first();
        if ($lastPause && !$lastPause->resumed_at) {
            $lastPause->update(['resumed_at' => now()]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift resumed',
            'data' => $this->formatShift($shift)
        ]);
    }

    // =========================
    // SHIFT STATUS
    // =========================
    public function shiftStatus()
    {
        $shift = Shift::whereNull('shift_end')->first();

        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'No active shift',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->formatShift($shift),
            'paused_times' => $shift->pauses()->count(),
            'pauses' => $shift->pauses()->get()
        ]);
    }

    // =========================
    // ALL SHIFTS
    // =========================
    public function allShifts()
    {
        $shifts = Shift::with('pauses')->orderBy('shift_start', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $shifts
        ]);
    }

    // =========================
    // SHIFT PAUSE HISTORY
    // =========================
    public function pauseHistory(Request $request)
    {
        $request->validate(['shift_id' => 'required|exists:shifts,id']);

        $shift = Shift::with('pauses')->find($request->shift_id);

        return response()->json([
            'status' => true,
            'shift' => $this->formatShift($shift),
            'paused_times' => $shift->pauses->count(),
            'pauses' => $shift->pauses
        ]);
    }

    // =========================
    // USER LOG HISTORY FOR SHIFT
    // =========================
    public function userLogHistory($shift_id, $user_id)
    {
        $shift = Shift::find($shift_id);

        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'Shift not found'
            ], 404);
        }

        $logs = UserShift::with('user:id,name,role')
            ->where('shift_id', $shift_id)
            ->where('user_id', $user_id)
            ->orderBy('login_at', 'asc')
            ->get();

        if ($logs->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No login/logout record found for this user in this shift'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'shift' => $this->formatShift($shift),
            'user' => [
                'id' => $logs->first()->user->id,
                'name' => $logs->first()->user->name,
                'role' => $logs->first()->user->role
            ],
            'total_records' => $logs->count(),
            'logs' => $logs->map(fn($log) => [
                'login_at' => $log->login_at,
                'logout_at' => $log->logout_at,
                'status' => $log->logout_at ? 'Logged Out' : 'Logged In'
            ])
        ]);
    }
}
