<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;
use App\Models\ShiftPause;
use Illuminate\Support\Facades\Hash;

class ShiftController extends Controller
{
    // Start a new shift
    public function startShift(Request $request)
    {
        $shift = Shift::whereNull('shift_end')->first();

        if ($shift) {
            return response()->json([
                'status' => true,
                'message' => 'Shift already started',
                'shift' => $shift
            ]);
        }

        $shift = Shift::create([
            'shift_start' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Shift started successfully',
            'shift' => $shift
        ]);
    }

    // Close current shift
    public function closeShift(Request $request)
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

        // Resume last pause if not resumed
        $lastPause = $shift->pauses()->latest()->first();
        if ($lastPause && !$lastPause->resumed_at) {
            $lastPause->update(['resumed_at' => now()]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift closed successfully',
            'shift' => $shift
        ]);
    }

    // Pause shift
    public function pauseShift(Request $request)
    {
        $request->validate([
            'manager_password' => 'required|string',
        ]);

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

        // Record pause
        $shift->pauses()->create(['paused_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'Shift paused',
            'shift' => $shift
        ]);
    }

    // Resume shift
    public function resumeShift(Request $request)
    {
        $request->validate([
            'manager_password' => 'required|string',
        ]);

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

        // Update last pause with resumed_at
        $lastPause = $shift->pauses()->latest()->first();
        if ($lastPause && !$lastPause->resumed_at) {
            $lastPause->update(['resumed_at' => now()]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift resumed',
            'shift' => $shift
        ]);
    }

    // Get current shift info
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
            'shift' => $shift,
            'paused_times' => $shift->pauses()->count(),
            'pauses' => $shift->pauses()->get(),
        ]);
    }

    // Get all shifts
    public function allShifts()
    {
        $shifts = Shift::with('pauses')->orderBy('shift_start', 'desc')->get();

        return response()->json([
            'status' => true,
            'shifts' => $shifts
        ]);
    }

    // Count today's shift pauses
    public function todayShiftPauses(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|exists:shifts,id',
        ]);

        $shift = Shift::with([
            'pauses' => function ($query) {
                $query->select('id', 'shift_id', 'paused_at', 'resumed_at'); // only select needed columns
            }
        ])->find($request->shift_id);

        return response()->json([
            'status' => true,
            'shift_id' => $shift->id,
            'shift_start' => $shift->shift_start,
            'shift_end' => $shift->shift_end,
            'paused_times' => $shift->pauses->count(),
            'pauses' => $shift->pauses,
        ]);
    }


}
