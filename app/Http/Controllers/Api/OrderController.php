<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use App\Models\PriceHistory;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    // =========================
    // CREATE ORDER
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:takeaway,dinein,delivery,udhar',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'waiter_id' => 'nullable|exists:waiters,id',
            'table_id' => 'nullable|exists:tables,id',
            'floor_id' => 'nullable|exists:floors,id',
            'discount' => 'nullable|numeric|min:0',
            'cash_received' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $grandTotal = 0;

            // -------------------------
            // CALCULATE TOTAL
            // -------------------------
            foreach ($request->items as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $price = $item['price'] ?? $menuItem->price;

                if ($price != $menuItem->price) {
                    PriceHistory::create([
                        'menu_item_id' => $menuItem->id,
                        'user_id' => auth()->id(),
                        'old_price' => $menuItem->price,
                        'new_price' => $price
                    ]);
                }

                $grandTotal += $price * $item['qty'];
            }

            $discount = $request->discount ?? 0;
            $netTotal = $grandTotal - $discount;

            // -------------------------
            // PAYMENT LOGIC (no advance)
            // -------------------------
            $cashReceived = $request->cash_received ?? 0;
            $changeDue = max($cashReceived - $netTotal, 0);

            // -------------------------
            // CREATE ORDER
            // -------------------------
            $order = Order::create([
                'type' => $request->type,
                'waiter_id' => $request->waiter_id,
                'table_id' => $request->table_id,
                'floor_id' => $request->floor_id,
                'grand_total' => $grandTotal,
                'discount' => $discount,
                'net_total' => $netTotal,
                'cash_received' => $cashReceived,
                'change_due' => $changeDue,
            ]);

            // -------------------------
            // ORDER ITEMS
            // -------------------------
            foreach ($request->items as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $price = $item['price'] ?? $menuItem->price;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => (int) $item['qty'],
                    'price' => $price,
                    'total' => $price * (int) $item['qty'],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => new OrderResource($order->load(['items.menuItem', 'waiter', 'table', 'floor']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // LIST ORDERS
    // =========================
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => OrderResource::collection(
                Order::with(['items.menuItem', 'waiter', 'table', 'floor'])->latest()->get()
            )
        ]);
    }

    // =========================
    // SHOW ORDER
    // =========================
    public function show($id)
    {
        return response()->json([
            'status' => true,
            'data' => new OrderResource(
                Order::with(['items.menuItem', 'waiter', 'table', 'floor'])->findOrFail($id)
            )
        ]);
    }

    // =========================
    // CANCEL ORDER
    // =========================
    public function cancel($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['is_cancelled' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Order cancelled successfully',
            'data' => new OrderResource($order)
        ]);
    }
}
