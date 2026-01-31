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
use App\Models\Customer;
use App\Models\Rider;
use App\Http\Resources\OrderResource;
use App\Models\OrderItemAdjustment;

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
        $orders = Order::with(['items.menuItem', 'waiter', 'table', 'floor', 'customer', 'rider'])
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
        $orders = Order::with(['items.menuItem', 'waiter', 'table', 'floor', 'customer', 'rider'])
            ->where('status', 'in_progress')
            ->where(function ($q) {
                $q->where('is_cancelled', false)
                    ->orWhereNull('is_cancelled');
            })
            ->latest()
            ->get();

        $grandTotal = $orders->sum('grand_total');
        $netTotal = $orders->sum('net_total');

        return response()->json([
            'status' => true,
            'summary' => [
                'grand_total' => $grandTotal,
                'net_total' => $netTotal
            ],
            'data' => OrderResource::collection($orders),
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
            'customer' => 'required_if:type,delivery|array',
            'customer.name' => 'required_if:type,delivery|string|max:255',
            'customer.phone' => 'required_if:type,delivery|string|max:20',
            'customer.address' => 'required_if:type,delivery|string|max:500',
            'customer.area' => 'required_if:type,delivery|string|max:100',
            'rider_id' => 'nullable|exists:riders,id',
            'delivery_charge' => 'nullable|numeric|min:0'
        ]);

        DB::beginTransaction();
        try {
            $grandTotal = 0;

            // Calculate grand total & track price changes
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
            $deliveryCharge = $request->delivery_charge ?? 0;
            $netTotal = $grandTotal - $discount + $deliveryCharge;
            $cashReceived = $request->cash_received ?? 0;
            $changeDue = $cashReceived - $netTotal;

            // Statuses
            $status = $request->type === 'takeaway' ? 'completed' : 'in_progress';
            $deliveryStatus = $request->type === 'delivery' ? 'preparing' : null;

            // Reserve table if dine-in
            if ($request->type === 'dinein') {
                $table = Table::lockForUpdate()->findOrFail($request->table_id);
                if ($table->status === 'reserved') {
                    return response()->json(['status' => false, 'message' => 'Table already reserved'], 409);
                }
                $table->update(['status' => 'reserved', 'waiter_id' => $request->waiter_id]);
            }

            // Auto-create customer if delivery
            $customerId = null;
            if ($request->type === 'delivery') {
                $customer = Customer::firstOrCreate(
                    ['phone' => $request->customer['phone']], // Check by phone
                    [
                        'name' => $request->customer['name'],
                        'address' => $request->customer['address'],
                        'area' => $request->customer['area']
                    ]
                );
                $customerId = $customer->id;
            }

            $receiptNumber = $this->generateReceiptNumber();

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
                'receipt_number' => $receiptNumber,
                'customer_id' => $customerId,
                'rider_id' => $request->rider_id ?? null,
                'delivery_charge' => $deliveryCharge,
                'delivery_status' => $deliveryStatus,
            ]);

            // Create order items
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
            $order->load(['items.menuItem', 'waiter', 'table', 'floor', 'customer', 'rider']);

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
            'customer_id' => 'nullable|exists:customers,id',
            'rider_id' => 'nullable|exists:riders,id',
            'delivery_charge' => 'nullable|numeric|min:0',
            'delivery_status' => 'nullable|in:preparing,delivered,cash_collected,cancelled'
        ]);

        DB::beginTransaction();
        try {
            // Fetch the order by ID from the database
            $order = Order::with('items')->findOrFail($id);

            // **Check the current delivery_status from the database**
            if ($order->type === 'delivery' && $order->delivery_status !== 'preparing') {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Only delivery orders with status preparing can be updated.'
                ], 400);
            }

            // Update items
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
                    $newQty = $existingItem->quantity + (int) $itemData['qty'];
                    $existingItem->update([
                        'quantity' => $newQty,
                        'price' => $price,
                        'total' => $price * $newQty,
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

            // Recalculate totals
            $grandTotal = $order->items->sum('total');
            $discount = $request->discount ?? $order->discount ?? 0;
            $deliveryCharge = $request->delivery_charge ?? $order->delivery_charge ?? 0;
            $netTotal = $grandTotal - $discount + $deliveryCharge;
            $cashReceived = $request->cash_received ?? $order->cash_received ?? 0;
            $changeDue = $cashReceived - $netTotal;

            $order->update([
                'grand_total' => $grandTotal,
                'discount' => $discount,
                'net_total' => $netTotal,
                'cash_received' => $cashReceived,
                'change_due' => $changeDue,
                'customer_id' => $request->customer_id ?? $order->customer_id,
                'rider_id' => $request->rider_id ?? $order->rider_id,
                'delivery_charge' => $deliveryCharge,
                'delivery_status' => $request->delivery_status ?? $order->delivery_status,
            ]);

            DB::commit();

            $order->load(['items.menuItem', 'waiter', 'table', 'floor', 'customer', 'rider']);

            return response()->json([
                'status' => true,
                'message' => 'Order updated successfully',
                'data' => new OrderResource($order),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTakeawayByReceipt(Request $request, $receiptNumber)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.qty' => 'required|integer|min:0',
            'items.*.price' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {

            $order = Order::with('items')
                ->where('receipt_number', $receiptNumber)
                ->where('type', 'takeaway')
                ->firstOrFail();

            foreach ($request->items as $itemData) {

                $menuItem = MenuItem::findOrFail($itemData['menu_item_id']);
                $price = $itemData['price'] ?? $menuItem->price;
                $newQty = (int) $itemData['qty'];

                $existingItem = $order->items
                    ->firstWhere('menu_item_id', $menuItem->id);

                /**
                 * 🟢 NEW ITEM ADD
                 */
                if (!$existingItem && $newQty > 0) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $menuItem->id,
                        'quantity' => $newQty,
                        'price' => $price,
                        'total' => $price * $newQty,
                    ]);
                    continue;
                }

                if (!$existingItem) {
                    continue;
                }

                $oldQty = $existingItem->quantity;

                /**
                 * 🔴 ITEM CANCEL
                 */
                if ($newQty === 0) {

                    OrderItemAdjustment::create([
                        'order_id' => $order->id,
                        'order_item_id' => $existingItem->id,
                        'menu_item_id' => $menuItem->id,
                        'receipt_number' => $order->receipt_number,

                        'old_quantity' => $oldQty,
                        'new_quantity' => 0,
                        'adjusted_quantity' => $oldQty,

                        'price' => $price,
                        'amount_impact' => $oldQty * $price,

                        'action' => 'cancelled',
                        'user_id' => auth()->id(),
                        'reason' => $request->reason,
                        'ip_address' => request()->ip(),
                    ]);

                    $existingItem->delete();
                    continue;
                }

                /**
                 * 🟠 QUANTITY DECREASE
                 */
                if ($newQty < $oldQty) {

                    $difference = $oldQty - $newQty;

                    OrderItemAdjustment::create([
                        'order_id' => $order->id,
                        'order_item_id' => $existingItem->id,
                        'menu_item_id' => $menuItem->id,
                        'receipt_number' => $order->receipt_number,

                        'old_quantity' => $oldQty,
                        'new_quantity' => $newQty,
                        'adjusted_quantity' => $difference,

                        'price' => $price,
                        'amount_impact' => $difference * $price,

                        'action' => 'decreased',
                        'user_id' => auth()->id(),
                        'reason' => $request->reason,
                        'ip_address' => request()->ip(),
                    ]);
                }

                /**
                 * ✏️ UPDATE ITEM (increase or decrease)
                 */
                $existingItem->update([
                    'quantity' => $newQty,
                    'price' => $price,
                    'total' => $price * $newQty,
                ]);
            }

            // 🔄 Recalculate totals
            $order->load('items');

            $grandTotal = $order->items->sum('total');
            $netTotal = $grandTotal - ($order->discount ?? 0);

            $order->update([
                'grand_total' => $grandTotal,
                'net_total' => $netTotal,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Takeaway order updated successfully',
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
    // DELIVERY ORDERS BY STATUS
    // =========================
    public function deliveryOrdersByStatus(Request $request)
    {
        // Validate delivery_status if provided
        $request->validate([
            'delivery_status' => 'nullable|in:preparing,delivered,cash_collected,cancelled'
        ]);

        // Correctly get delivery_status from request
        $deliveryStatus = $request->delivery_status;

        // Base query for delivery orders
        $query = Order::with(['items.menuItem', 'customer', 'rider'])
            ->where('type', 'delivery');

        // Filter by delivery_status if provided
        if ($deliveryStatus) {
            $query->where('delivery_status', $deliveryStatus);
        }

        // Get orders latest first
        $orders = $query->latest()->get();

        // Transform orders for API response
        $data = $orders->map(function ($order) {
            $array = (new OrderResource($order))->toArray(request());

            // Add separate date & time fields
            $array['date'] = $order->created_at->format('Y-m-d');
            $array['time'] = $order->created_at->format('H:i:s');

            // Remove unnecessary timestamps
            unset($array['created_at'], $array['updated_at']);

            return $array;
        });

        // Prepare response
        return response()->json([
            'status' => $orders->isEmpty() ? false : true,
            'data' => $data,
            'message' => $orders->isEmpty()
                ? ($deliveryStatus ? "No delivery orders found with status '$deliveryStatus'." : "No delivery orders found.")
                : ($deliveryStatus ? "Delivery orders retrieved with status '$deliveryStatus'." : "Delivery orders retrieved successfully."),
        ]);
    }



    // =========================
    // DELIVERY STATUS CHANGE (SINGLE)
    // =========================
    public function changeDeliveryStatus(Request $request, $orderId)
    {
        $request->validate([
            'delivery_status' => 'required|in:preparing,delivered,cash_collected,cancelled'
        ]);

        $order = Order::findOrFail($orderId);

        if ($order->type !== 'delivery') {
            return response()->json(['status' => false, 'message' => 'Not a delivery order'], 400);
        }

        $order->delivery_status = $request->delivery_status;

        // Automatically update cash_received if status is delivered
        if ($request->delivery_status === 'delivered') {
            // Cash received = net_total + delivery_charge
            $order->cash_received = $order->net_total + $order->delivery_charge;
            $order->change_due = $order->cash_received - $order->net_total - $order->delivery_charge; // Optional, if you track change_due
            $order->status = 'completed';
        } elseif ($request->delivery_status === 'cash_collected') {
            $order->status = 'completed';
        } elseif ($request->delivery_status === 'cancelled') {
            $order->status = 'cancelled';
            $order->is_cancelled = true;
        }

        $order->save();
        $order->load(['items.menuItem', 'customer', 'rider']);

        return response()->json([
            'status' => true,
            'message' => 'Delivery status updated',
            'data' => $order
        ]);
    }


    // =========================
    // BULK DELIVERY STATUS CHANGE
    // =========================
    public function bulkChangeDeliveryStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'delivery_status' => 'required|in:preparing,delivered,cash_collected,cancelled'
        ]);

        // Fetch the delivery orders
        $orders = Order::whereIn('id', $request->order_ids)
            ->where('type', 'delivery')
            ->get();

        $alreadySet = [];
        $toUpdate = [];

        foreach ($orders as $order) {
            if ($order->delivery_status === $request->delivery_status) {
                $alreadySet[] = $order->id; // Already in requested status
            } else {
                $toUpdate[] = $order->id; // Needs update
            }
        }

        // Update only the ones that need it
        if (!empty($toUpdate)) {
            Order::whereIn('id', $toUpdate)
                ->update(['delivery_status' => $request->delivery_status]);
        }

        // Build message
        $messageParts = [];

        if (!empty($toUpdate)) {
            $messageParts[] = "Delivery status changed to '{$request->delivery_status}' for orders ID: " . implode(', ', $toUpdate);
        }

        if (!empty($alreadySet)) {
            $messageParts[] = "Orders ID '" . implode(', ', $alreadySet) . "' already in {$request->delivery_status}";

        }

        $message = implode('. ', $messageParts);

        if (empty($message)) {
            $message = "No orders found to update.";
        }

        return response()->json([
            'status' => true,
            'message' => $message
        ]);
    }



    // =========================
    // RIDER STATS
    // =========================
    public function riderStats($riderId)
    {
        $orders = Order::where('rider_id', $riderId)->get();

        $totalOrders = $orders->count();
        $totalDelivered = $orders->where('delivery_status', 'delivered')->count();
        $totalCancelled = $orders->where('delivery_status', 'cancelled')->count();

        // Cash already given to owner
        $totalCashCollected = $orders->where('delivery_status', 'cash_collected')->sum('cash_received');

        // Cash still with rider
        $cashInHand = $orders->where('delivery_status', 'delivered')
            ->sum(function ($order) {
                return $order->net_total + $order->delivery_charge;
            });

        // Total order price (exclude cancelled)
        $totalOrderPrice = $orders->whereNotIn('delivery_status', ['cancelled'])->sum('net_total');

        // Total delivery charges (exclude cancelled)
        $totalDeliveryCharges = $orders->whereNotIn('delivery_status', ['cancelled'])->sum('delivery_charge');

        $stats = [
            'total_orders' => $totalOrders,
            'total_delivered' => $totalDelivered,
            'total_cancelled' => $totalCancelled,
            'total_cash_collected' => $totalCashCollected,
            'cash_in_hand' => $cashInHand,
            'total_delivery_charges' => $totalDeliveryCharges,
            'total_order_price' => $totalOrderPrice,
        ];

        return response()->json([
            'status' => true,
            'data' => $stats
        ]);
    }


    // =========================
    // ALL RIDERS STATS
    // =========================
    public function allRidersStats()
    {
        $orders = Order::where('type', 'delivery')->get();

        $stats = [
            'total_orders' => $orders->count(),
            'grand_total_orders_price' => $orders->sum('net_total'),
            'grand_total_delivery_charges' => $orders->sum('delivery_charge'),
        ];

        return response()->json(['status' => true, 'data' => $stats]);
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
            $netTotal = $order->grand_total - $discount + ($order->delivery_charge ?? 0);
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
    // CANCEL ORDER BY RECEIPT NUMBER
    // =========================
    public function cancel($receiptNumber)
    {
        DB::beginTransaction();
        try {
            // Fetch order by receipt number
            $order = Order::with(['items.menuItem', 'waiter', 'table', 'floor', 'customer', 'rider'])
                ->where('receipt_number', $receiptNumber)
                ->first();

            if (!$order) {
                return response()->json(['status' => false, 'message' => 'Order not found'], 404);
            }

            if ($order->is_cancelled) {
                return response()->json(['status' => false, 'message' => 'Order already cancelled'], 400);
            }

            // Cancel the order
            $updateData = [
                'status' => 'cancelled',
                'is_cancelled' => true,
            ];

            // If delivery, cancel delivery_status
            if ($order->type === 'delivery') {
                $updateData['delivery_status'] = 'cancelled';
            }

            $order->update($updateData);

            // Free the table if dine-in
            if ($order->type === 'dinein' && $order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => 'free']);
            }

            DB::commit();

            // Reload relations for response
            $order->load(['items.menuItem', 'waiter', 'table', 'floor', 'customer', 'rider']);

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
// CANCEL DINE-IN ORDER BY ORDER ID
// =========================
    public function cancelDineInByOrderId($orderId)
    {
        DB::beginTransaction();
        try {
            $order = Order::with(['items.menuItem', 'waiter', 'table', 'floor'])
                ->where('id', $orderId)
                ->where('type', 'dinein')
                ->first();

            if (!$order) {
                return response()->json(['status' => false, 'message' => 'Dine-in order not found'], 404);
            }

            if ($order->is_cancelled) {
                return response()->json(['status' => false, 'message' => 'Order already cancelled'], 400);
            }

            // Cancel the order
            $order->update([
                'status' => 'cancelled',
                'is_cancelled' => true
            ]);

            // Free the table
            if ($order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => 'free']);
            }

            DB::commit();

            $order->load(['items.menuItem', 'waiter', 'table', 'floor']);

            return response()->json([
                'status' => true,
                'message' => 'Dine-in order cancelled successfully',
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
                ->where(function ($q) {
                    $q->where('is_cancelled', false)
                        ->orWhereNull('is_cancelled');
                })
                ->get();

            if ($orders->isEmpty()) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'No dine-in orders to complete'], 404);
            }

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
    // COMPLETE ALL ORDERS BY WAITER
    // =========================
    public function completeAllByWaiter($waiterId)
    {
        DB::beginTransaction();
        try {
            $orders = Order::where('type', 'dinein')
                ->where('waiter_id', $waiterId)
                ->where('status', 'in_progress')
                ->where('is_cancelled', false)
                ->get();

            if ($orders->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'No in-progress orders found for this waiter'], 404);
            }

            foreach ($orders as $order) {
                $order->update(['status' => 'completed']);
                if ($order->table_id) {
                    Table::where('id', $order->table_id)->update(['status' => 'free']);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'All orders for this waiter completed successfully',
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
            return response()->json(['status' => false, 'message' => 'No active dine-in order found for this table'], 404);
        }

        return response()->json(['status' => true, 'data' => new OrderResource($order)]);
    }

    // =========================
    // TAKEAWAY ORDER BY RECEIPT NUMBER
    // =========================
    public function takeawayOrderByReceiptNumber($receiptNumber)
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
            return response()->json(['status' => false, 'message' => 'Takeaway order not found in current cycle'], 404);
        }

        return response()->json(['status' => true, 'data' => new OrderResource($order)]);
    }
}
