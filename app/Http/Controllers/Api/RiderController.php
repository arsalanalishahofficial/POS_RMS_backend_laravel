<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rider;

class RiderController extends Controller
{
public function index()
{
    $riders = Rider::with(['orders'])
        ->get()
        ->map(function($rider) {

            // Total orders
            $totalOrders = $rider->orders->count();

            // Total delivered orders
            $totalDelivered = $rider->orders->where('delivery_status', 'delivered')->count();

            // Total cancelled orders
            $totalCancelled = $rider->orders->where('is_cancelled', 1)->count();

            // Total cash collected orders (count where delivery_status = cash_collected)
            $totalCashCollected = $rider->orders->where('delivery_status', 'cash_collected')->count();

            // Cash collected (sum of net_total where delivery_status = 'cash_collected')
            $cashCollected = $rider->orders
                ->where('delivery_status', 'cash_collected')
                ->sum('net_total');

            // Cash to collect (sum of net_total where delivery_status = 'delivered')
            $cashToCollect = $rider->orders
                ->where('delivery_status', 'delivered')
                ->sum('net_total');

            // Total delivery charges
            $totalDeliveryCharges = $rider->orders->sum('delivery_charge');

            // Total amount
            $totalAmount = $cashCollected + $cashToCollect + $totalDeliveryCharges;

            return [
                'id' => $rider->id,
                'name' => $rider->name,
                'phone' => $rider->phone,
                'total_orders' => $totalOrders,
                'total_delivered' => $totalDelivered,
                'total_cancelled' => $totalCancelled,
                'total_cash_collected' => $totalCashCollected,
                'cash_collected' => round($cashCollected, 2),  // cash already collected
                'cash_to_collect' => round($cashToCollect, 2), // cash still to collect
                'total_delivery_charges' => round($totalDeliveryCharges, 2),
                'total' => round($totalAmount, 2),
            ];
        });

    return response()->json([
        'status' => true,
        'data' => $riders,
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        // Check if a soft-deleted rider exists with the same phone
        $rider = Rider::withTrashed()->where('phone', $request->phone)->first();

        if ($rider) {
            // If soft-deleted, restore it
            if ($rider->trashed()) {
                $rider->restore();
            }

            // Update name if needed
            $rider->update(['name' => $request->name]);

            return response()->json([
                'status' => true,
                'message' => 'Soft-deleted rider restored successfully',
                'data' => $rider,
            ], 200);
        }

        // Otherwise, create a new rider
        $rider = Rider::create($request->only('name', 'phone'));

        return response()->json([
            'status' => true,
            'message' => 'Rider created successfully',
            'data' => $rider,
        ], 201);
    }

    public function show($id)
    {
        $rider = Rider::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $rider,
        ]);
    }

    public function update(Request $request, $id)
    {
        $rider = Rider::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20|unique:riders,phone,' . $id,
        ]);

        $rider->update($request->only('name', 'phone'));

        return response()->json([
            'status' => true,
            'message' => 'Rider updated successfully',
            'data' => $rider,
        ]);
    }

    public function destroy($id)
    {
        $rider = Rider::findOrFail($id);
        $rider->delete();

        return response()->json([
            'status' => true,
            'message' => 'Rider soft-deleted successfully',
        ]);
    }

}
