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
use App\Models\OrderReceipt;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    // =========================
    // HELPER: Generate Receipt Number (2-day cycle)
    // =========================
    private function generateReceiptNumber()
    {
        $now = now();
        $dayNumber = $now->diffInDays(now()->startOfYear());
        $cycleStart = now()->startOfYear()->addDays(intdiv($dayNumber, 2) * 2)->toDateString();

        $receipt = OrderReceipt::lockForUpdate()->firstOrCreate(
            ['cycle_start' => $cycleStart],
            ['current_number' => 0]
        );

        $receiptNumber = $receipt->current_number + 1;
        $receipt->increment('current_number');

        return $receiptNumber;
    }

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
    // LIST IN-PROGRESS ORDERS
    // =========================
    public function inProgress()
    {
        $orders = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])
            ->where('status', 'in_progress')
            ->where('is_cancelled', false)
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
        $order = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])->findOrFail($id);

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

                $grandTotal += $price * (int) $item['qty'];
            }

            $discount = $request->discount ?? 0;
            $netTotal = $grandTotal - $discount;
            $cashReceived = $request->cash_received ?? 0;
            $changeDue = $cashReceived - $netTotal;
            $status = $request->type === 'dinein' ? 'in_progress' : 'completed';

            if ($request->type === 'dinein') {
                $table = Table::lockForUpdate()->findOrFail($request->table_id);
                if ($table->status === 'reserved') {
                    return response()->json(['status' => false, 'message' => 'Table already reserved'], 409);
                }
                $table->update(['status' => 'reserved', 'waiter_id' => $request->waiter_id]);
            }

            // Generate persistent receipt number
            $receiptNumber = $this->generateReceiptNumber();

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
                'receipt_number' => $receiptNumber,
            ]);

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
            $order->load(['items.menuItem', 'waiter', 'table', 'floor']);

            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => new OrderResource($order),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================
    // UPDATE ORDER
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
                return response()->json(['status' => false, 'message' => 'Cannot update a cancelled order'], 400);
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
                        'quantity' => $existingItem->quantity + (int) $itemData['qty'],
                        'price' => $price,
                        'total' => $price * ($existingItem->quantity + (int) $itemData['qty']),
                    ]);
                } else {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $menuItem->id,
                        'quantity' => (int) $itemData['qty'],
                        'price' => $price,
                        'total' => $price * (int) $itemData['qty'],
                    ]);
                }
            }

            $order->refresh();
            $grandTotal = $order->items->sum(fn($i) => $i->total);
            $discount = $request->discount ?? $order->discount ?? 0;
            $netTotal = $grandTotal - $discount;
            $cashReceived = $request->cash_received ?? $order->cash_received ?? 0;
            $changeDue = $cashReceived - $netTotal;

            $order->update([
                'grand_total' => $grandTotal,
                'discount' => $discount,
                'net_total' => $netTotal,
                'cash_received' => $cashReceived,
                'change_due' => $changeDue,
            ]);

            DB::commit();
            $order->load(['items.menuItem', 'waiter', 'table', 'floor']);

            return response()->json([
                'status' => true,
                'message' => 'Order updated successfully',
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================
    // COMPLETE ORDER
    // =========================
    public function complete(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            if ($order->status !== 'in_progress') {
                return response()->json(['status' => false, 'message' => 'Order is not in progress'], 400);
            }

            $cashReceived = $request->cash_received ?? $order->cash_received ?? 0;
            $discount = $request->discount ?? $order->discount ?? 0;
            $netTotal = $order->grand_total - $discount;
            $changeDue = $cashReceived - $netTotal;

            $order->update([
                'status' => 'completed',
                'cash_received' => $cashReceived,
                'discount' => $discount,
                'net_total' => $netTotal,
                'change_due' => $changeDue,
            ]);

            if ($order->type === 'dinein' && $order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => 'free']);
            }

            DB::commit();
            $order->load(['items.menuItem', 'waiter', 'table', 'floor']);

            return response()->json([
                'status' => true,
                'message' => 'Order completed successfully',
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================
// CANCEL ORDER BY KOT / RECEIPT NUMBER
// =========================
    public function cancel($receiptNumber)
    {
        DB::beginTransaction();
        try {
            // Find order by receipt_number
            $order = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])
                ->where('receipt_number', $receiptNumber)
                ->where('is_cancelled', false)
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found or already cancelled',
                ], 404);
            }

            // Cancel the order
            $order->update(['status' => 'cancelled', 'is_cancelled' => true]);

            // Free table if dine-in
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
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }


    // =========================
    // COMPLETE ALL DINE-IN ORDERS
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
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================
    // GET DINE-IN ORDER BY TABLE ID
    // =========================
    public function dineInOrderByTable($tableId)
    {
        $order = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])
            ->where('type', 'dinein')
            ->where('table_id', $tableId)
            ->where('status', 'in_progress')
            ->where('is_cancelled', false)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'No active dine-in order found for this table',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => new OrderResource($order),
        ]);
    }

    // =========================
    // TAKEAWAY ORDER BY RECEIPT NUMBER
    // =========================
    public function takeawayOrderById($receiptNumber)
    {
        $now = now();
        $dayNumber = $now->diffInDays(now()->startOfYear());
        $cycleStart = now()->startOfYear()->addDays(intdiv($dayNumber, 2) * 2)->toDateString();

        $order = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])
            ->where('type', 'takeaway')
            ->where('receipt_number', $receiptNumber)
            ->whereDate('created_at', '>=', $cycleStart)
            ->where('is_cancelled', false)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Takeaway order not found in current cycle',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => new OrderResource($order),
        ]);
    }
}
