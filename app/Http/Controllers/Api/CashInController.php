<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CashIn;
use App\Models\User;
use App\Models\Shift;

class CashInController extends Controller
{
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

        return response()->json([
            'status' => true,
            'message' => 'Cash-in successful',
            'data' => $cashIn
        ]);
    }

    public function overallCashInToCasher()
    {
        $perCasher = CashIn::selectRaw('casher_id, SUM(amount) as total')
            ->with('casher:id,name,email') // eager load casher info
            ->groupBy('casher_id')
            ->get();

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


    public function myCashInsWithTotal()
    {
        $cashIns = CashIn::with(['owner:id,name', 'shift:id,shift_start'])
            ->where('casher_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();


        $total = $cashIns->sum('amount');

        return response()->json([
            'status' => true,
            'message' => 'Cash-in list with total retrieved',
            'data' => [
                'total_cash_in' => $total,
                'cash_ins' => $cashIns
            ]
        ]);
    }

}
