<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CashOut;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;

class CashOutController extends Controller
{
    // =========================
    // CREATE CASH-OUT
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'type' => 'required|in:expense,handover,end_shift',
            'note' => 'nullable|string'
        ]);

        $shift = Shift::whereNull('shift_end')->first();
        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'No active shift'
            ], 403);
        }

        $cashOut = CashOut::create([
            'casher_id' => auth()->id(),
            'shift_id' => $shift->id,
            'amount' => $request->amount,
            'type' => $request->type,
            'note' => $request->note
        ]);

        $created = $cashOut->created_at->copy()->timezone(config('app.timezone'));
        $updated = $cashOut->updated_at->copy()->timezone(config('app.timezone'));
        $shiftStart = $cashOut->shift?->shift_start?->copy()->timezone(config('app.timezone'));
        $shiftEnd = $cashOut->shift?->shift_end?->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Cash-out successful',
            'data' => [
                'id' => $cashOut->id,
                'casher_id' => $cashOut->casher_id,
                'shift' => $cashOut->shift ? [
                    'id' => $cashOut->shift->id,
                    'shift_start_date' => $shiftStart?->format('Y-m-d'),
                    'shift_start_time' => $shiftStart?->format('h:i A'),
                    'shift_end_date' => $shiftEnd?->format('Y-m-d'),
                    'shift_end_time' => $shiftEnd?->format('h:i A'),
                ] : null,
                'amount' => $cashOut->amount,
                'type' => $cashOut->type,
                'note' => $cashOut->note,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // OVERALL CASH-OUT BY CASHER
    // =========================
    public function overallCashOutByCasher()
    {
        $perCasher = CashOut::selectRaw('casher_id, SUM(amount) as total')
            ->with('casher:id,name,email') // eager load casher info
            ->groupBy('casher_id')
            ->get()
            ->map(function ($item) {
                return [
                    'casher_id' => $item->casher_id,
                    'casher_name' => $item->casher?->name,
                    'casher_email' => $item->casher?->email,
                    'total_cash_out' => $item->total,
                ];
            });

        $grandTotal = CashOut::sum('amount');

        return response()->json([
            'status' => true,
            'message' => 'Cash-out totals per casher and overall total',
            'data' => [
                'per_casher' => $perCasher,
                'grand_total' => $grandTotal
            ]
        ]);
    }

    // =========================
    // MY CASH-OUTS WITH TOTAL
    // =========================
    public function myCashOutsWithTotal()
    {
        $casherId = Auth::id();

        $cashOuts = CashOut::with('shift:id,shift_start,shift_end')
            ->where('casher_id', $casherId)
            ->orderBy('created_at', 'desc')
            ->get();

        $total = $cashOuts->sum('amount');

        $cashOutsFormatted = $cashOuts->map(function ($co) {
            $created = $co->created_at->copy()->timezone(config('app.timezone'));
            $updated = $co->updated_at->copy()->timezone(config('app.timezone'));
            $shiftStart = $co->shift?->shift_start?->copy()->timezone(config('app.timezone'));
            $shiftEnd = $co->shift?->shift_end?->copy()->timezone(config('app.timezone'));

            return [
                'id' => $co->id,
                'shift' => $co->shift ? [
                    'id' => $co->shift->id,
                    'shift_start_date' => $shiftStart?->format('Y-m-d'),
                    'shift_start_time' => $shiftStart?->format('h:i A'),
                    'shift_end_date' => $shiftEnd?->format('Y-m-d'),
                    'shift_end_time' => $shiftEnd?->format('h:i A'),
                ] : null,
                'amount' => $co->amount,
                'type' => $co->type,
                'note' => $co->note,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Cash-out list with total retrieved',
            'data' => [
                'total_cash_out' => $total,
                'cash_outs' => $cashOutsFormatted
            ]
        ]);
    }
}
