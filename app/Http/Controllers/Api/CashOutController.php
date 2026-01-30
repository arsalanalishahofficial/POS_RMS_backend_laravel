<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CashOut;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;


class CashOutController extends Controller
{
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

        return response()->json([
            'status' => true,
            'message' => 'Cash-out successful',
            'data' => $cashOut
        ]);
    }




    public function overallCashOutByCasher()
    {
        $perCasher = CashOut::selectRaw('casher_id, SUM(amount) as total')
            ->with('casher:id,name,email') // eager load casher info
            ->groupBy('casher_id')
            ->get();

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



    // Casher report
    public function myCashOutsWithTotal()
    {
        $casherId = Auth::id();

        $total = CashOut::where('casher_id', $casherId)->sum('amount');

        $cashOuts = CashOut::where('casher_id', $casherId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Cash-out list with total retrieved',
            'data' => [
                'total_cash_out' => $total,
                'cash_outs' => $cashOuts
            ]
        ]);
    }


}
