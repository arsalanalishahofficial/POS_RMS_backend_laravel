<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CashIn;
use App\Models\User;
use App\Models\Shift;

class CashInController extends Controller
{
    // =========================
    // CREATE CASH-IN
    // =========================
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'owner') {
            return response()->json([
                'status' => false,
                'message' => 'Only owner can perform cash-in'
            ], 403);
        }

        $request->validate([
            'casher_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string'
        ]);

        $shift = Shift::whereNull('shift_end')->first();
        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'No active shift'
            ], 403);
        }

        $casher = User::find($request->casher_id);

        if ($casher->role !== 'casher') {
            return response()->json([
                'status' => false,
                'message' => 'Cash-in can only be given to a casher'
            ], 403);
        }

        $cashIn = CashIn::create([
            'owner_id' => auth()->id(),
            'casher_id' => $casher->id,
            'shift_id' => $shift->id,
            'amount' => $request->amount,
            'note' => $request->note
        ]);

        // Format timestamps
        $created = $cashIn->created_at->copy()->timezone(config('app.timezone'));
        $updated = $cashIn->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Cash-in successful',
            'data' => [
                'id' => $cashIn->id,
                'owner_id' => $cashIn->owner_id,
                'casher_id' => $cashIn->casher_id,
                'shift_id' => $cashIn->shift_id,
                'amount' => $cashIn->amount,
                'note' => $cashIn->note,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // OVERALL CASH-IN PER CASHER
    // =========================
    public function overallCashInToCasher()
    {
        $perCasher = CashIn::selectRaw('casher_id, SUM(amount) as total')
            ->with('casher:id,name,email') // eager load casher info
            ->groupBy('casher_id')
            ->get()
            ->map(function ($item) {
                return [
                    'casher_id' => $item->casher_id,
                    'casher_name' => $item->casher?->name,
                    'casher_email' => $item->casher?->email,
                    'total_cash_in' => $item->total,
                ];
            });

        $grandTotal = CashIn::sum('amount');

        return response()->json([
            'status' => true,
            'message' => 'Overall cash-in totals per casher and grand total',
            'data' => [
                'per_casher' => $perCasher,
                'grand_total' => $grandTotal
            ]
        ]);
    }

    // =========================
    // MY CASH-INS WITH TOTAL
    // =========================
    public function myCashInsWithTotal()
    {
        $cashIns = CashIn::with(['owner:id,name', 'shift:id,shift_start,shift_end'])
            ->where('casher_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        $total = $cashIns->sum('amount');

        $cashInsFormatted = $cashIns->map(function ($ci) {
            $created = $ci->created_at->copy()->timezone(config('app.timezone'));
            $updated = $ci->updated_at->copy()->timezone(config('app.timezone'));
            $shiftStart = $ci->shift?->shift_start?->copy()->timezone(config('app.timezone'));
            $shiftEnd = $ci->shift?->shift_end?->copy()->timezone(config('app.timezone'));

            return [
                'id' => $ci->id,
                'owner' => [
                    'id' => $ci->owner?->id,
                    'name' => $ci->owner?->name
                ],
                'shift' => $ci->shift ? [
                    'id' => $ci->shift->id,
                    'shift_start_date' => $shiftStart?->format('Y-m-d'),
                    'shift_start_time' => $shiftStart?->format('h:i A'),
                    'shift_end_date' => $shiftEnd?->format('Y-m-d'),
                    'shift_end_time' => $shiftEnd?->format('h:i A'),
                ] : null,
                'amount' => $ci->amount,
                'note' => $ci->note,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Cash-in list with total retrieved',
            'data' => [
                'total_cash_in' => $total,
                'cash_ins' => $cashInsFormatted
            ]
        ]);
    }
}
