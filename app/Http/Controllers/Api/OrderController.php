<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use App\Models\PriceHistory;
use App\Models\Table;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    // =========================
    // LIST ALL ORDERS
    // =========================
    public function index()
    {
        $orders = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => OrderResource::collection($orders),
        ]);
    }

    // =========================
    // LIST ALL IN-PROGRESS ORDERS
    // =========================
    public function inProgress()
    {
        $orders = Order::where('status', 'in_progress')
            ->where('is_cancelled', false)
            ->with(['items.menuItem', 'waiter', 'table', 'floor'])
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => OrderResource::collection($orders),
        ]);
    }

    // =========================
    // SHOW SINGLE ORDER
    // =========================
    public function show($id)
    {
        $order = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => new OrderResource($order),
        ]);
    }

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
            'waiter_id' => 'required_if:type,dinein|nullable|exists:waiters,id',
            'table_id' => 'required_if:type,dinein|nullable|exists:tables,id',
            'floor_id' => 'nullable|exists:floors,id',
            'discount' => 'nullable|numeric|min:0',
            'cash_received' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $grandTotal = 0;

            foreach ($request->items as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $price = $item['price'] ?? $menuItem->price;

                if ($price != $menuItem->price) {
                    PriceHistory::create([
                        'menu_item_id' => $menuItem->id,
                        'user_id' => auth()->id(),
                        'old_price' => $menuItem->price,
                        'new_price' => $price,
                    ]);
                }

                $grandTotal += $price * (int)$item['qty'];
            }

            $discount = $request->discount ?? 0;
            $netTotal = $grandTotal - $discount;
            $cashReceived = $request->cash_received ?? 0;
            $changeDue = $cashReceived - $netTotal; // allow negative

            $status = $request->type === 'dinein' ? 'in_progress' : 'completed';

            // Reserve table for dine-in
            if ($request->type === 'dinein') {
                $table = Table::lockForUpdate()->findOrFail($request->table_id);

                if ($table->status === 'reserved') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Table already reserved',
                    ], 409);
                }

                $table->update([
                    'status' => 'reserved',
                    'waiter_id' => $request->waiter_id,
                ]);
            }

            // Create order
            $order = Order::create([
                'type' => $request->type,
                'status' => $status,
                'waiter_id' => $request->waiter_id,
                'table_id' => $request->table_id,
                'floor_id' => $request->floor_id,
                'grand_total' => $grandTotal,
                'discount' => $discount,
                'net_total' => $netTotal,
                'cash_received' => $cashReceived,
                'change_due' => $changeDue,
            ]);

            // Add order items
            foreach ($request->items as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $price = $item['price'] ?? $menuItem->price;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => (int)$item['qty'],
                    'price' => $price,
                    'total' => $price * (int)$item['qty'],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => new OrderResource(
                    $order->load(['items.menuItem', 'waiter', 'table', 'floor'])
                ),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================
    // COMPLETE SINGLE ORDER
    // =========================
    public function complete(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $order = Order::findOrFail($id);

            if ($order->status !== 'in_progress') {
                return response()->json([
                    'status' => false,
                    'message' => 'Order is not in progress',
                ], 400);
            }

            $cashReceived = $request->cash_received ?? $order->cash_received ?? 0;
            $discount = $request->discount ?? $order->discount ?? 0;
            $netTotal = $order->grand_total - $discount;
            $changeDue = $cashReceived - $netTotal; // allow negative

            $order->update([
                'status' => 'completed',
                'cash_received' => $cashReceived,
                'discount' => $discount,
                'net_total' => $netTotal,
                'change_due' => $changeDue,
            ]);

            // Free table if dine-in
            if ($order->type === 'dinein' && $order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => 'free']);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order completed successfully',
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================
    // CANCEL ORDER
    // =========================
    public function cancel($id)
    {
        DB::beginTransaction();

        try {
            $order = Order::findOrFail($id);

            $order->update([
                'status' => 'cancelled',
                'is_cancelled' => true,
            ]);

            if ($order->type === 'dinein' && $order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => 'free']);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order cancelled successfully',
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================
    // COMPLETE ALL ACTIVE DINE-IN ORDERS
    // =========================
    public function completeAll()
    {
        DB::beginTransaction();

        try {
            $orders = Order::where('type', 'dinein')
                ->where('status', 'in_progress')
                ->where('is_cancelled', false)
                ->get();

            foreach ($orders as $order) {
                $order->update(['status' => 'completed']);

                if ($order->table_id) {
                    Table::where('id', $order->table_id)->update(['status' => 'free']);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'All dine-in orders completed successfully',
                'completed_orders' => $orders->count(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================
    // UPDATE ORDER (ADD/UPDATE ITEMS)
    // =========================
    public function update(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'cash_received' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $order = Order::with('items')->findOrFail($id);

            if ($order->status === 'cancelled') {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot update a cancelled order',
                ], 400);
            }

            foreach ($request->items as $itemData) {
                $menuItem = MenuItem::findOrFail($itemData['menu_item_id']);
                $price = $itemData['price'] ?? $menuItem->price;

                if ($price != $menuItem->price) {
                    PriceHistory::create([
                        'menu_item_id' => $menuItem->id,
                        'user_id' => auth()->id(),
                        'old_price' => $menuItem->price,
                        'new_price' => $price,
                    ]);
                }

                $existingItem = $order->items->firstWhere('menu_item_id', $menuItem->id);

                if ($existingItem) {
                    $existingItem->update([
                        'quantity' => $existingItem->quantity + (int)$itemData['qty'],
                        'price' => $price,
                        'total' => $price * ($existingItem->quantity + (int)$itemData['qty']),
                    ]);
                } else {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $menuItem->id,
                        'quantity' => (int)$itemData['qty'],
                        'price' => $price,
                        'total' => $price * (int)$itemData['qty'],
                    ]);
                }
            }

            $order->refresh();
            $grandTotal = $order->items->sum(fn($i) => $i->total);

            $discount = $request->discount ?? $order->discount ?? 0;
            $netTotal = $grandTotal - $discount;
            $cashReceived = $request->cash_received ?? $order->cash_received ?? 0;
            $changeDue = $cashReceived - $netTotal; // allow negative

            $order->update([
                'grand_total' => $grandTotal,
                'discount' => $discount,
                'net_total' => $netTotal,
                'cash_received' => $cashReceived,
                'change_due' => $changeDue,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order updated successfully',
                'data' => new OrderResource($order->load(['items.menuItem', 'waiter', 'table', 'floor'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
