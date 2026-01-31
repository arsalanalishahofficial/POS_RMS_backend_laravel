<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItemAdjustment;
use App\Models\Shift;

class OrderItemAdjustmentController extends Controller
{
    public function currentShiftReport()
    {
        $shift = Shift::whereNull('shift_end')->latest('shift_start')->first();

        if (!$shift) {
            return response()->json([
                'status' => false,
                'message' => 'No active shift found'
            ], 404);
        }

        $adjustments = OrderItemAdjustment::with(['order', 'menuItem', 'user'])
            ->whereBetween('created_at', [$shift->shift_start, now()])
            ->orderBy('created_at', 'desc')
            ->get();

        $report = $adjustments->map(function ($adj) {
            return [
                'adjustment_id' => $adj->id,
                'receipt_number' => $adj->receipt_number,
                'order_type' => $adj->order->type ?? null,
                'menu_item' => $adj->menuItem->name ?? null,
                'old_quantity' => $adj->old_quantity,
                'new_quantity' => $adj->new_quantity,
                'adjusted_quantity' => $adj->adjusted_quantity,
                'action' => $adj->action,
                'new_price' => $adj->price,
                'old_price' => $adj->old_quantity * $adj->price, // previous amount
                'amount_return' => $adj->amount_impact,
                'user' => $adj->user->name ?? null,
                'reason' => $adj->reason,
                'adjusted_at' => $adj->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status' => true,
            'shift_id' => $shift->id,
            'shift_start' => $shift->shift_start->format('Y-m-d H:i:s'),
            'shift_end' => $shift->shift_end ? $shift->shift_end->format('Y-m-d H:i:s') : null,
            'count' => $report->count(),
            'data' => $report,
        ]);
    }

}
