<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Table;
use App\Models\WaiterTransaction;

class TableController extends Controller
{
    // =========================
    // LIST ALL TABLES WITH FLOOR SUMMARY
    // =========================
    public function index()
    {
        $overallSummary = [
            'total_tables' => Table::count(),
            'free_tables' => Table::where('status', 'free')->count(),
            'reserved_tables' => Table::where('status', 'reserved')->count(),
            'sub_tables' => Table::where('is_sub_table', 1)->count(),
        ];

        $tables = Table::with(['floor', 'waiter'])->get();

        $floorSummary = $tables
            ->groupBy('floor_id')
            ->map(function ($tables, $floorId) {
                $floorName = $tables->first()->floor->name ?? null;

                return [
                    'floor_id' => $floorId,
                    'floor_name' => $floorName,
                    'total_tables' => $tables->count(),
                    'free_tables' => $tables->where('status', 'free')->count(),
                    'reserved_tables' => $tables->where('status', 'reserved')->count(),
                    'tables' => $tables->map(function ($table) {
                        $created = $table->created_at->copy()->timezone(config('app.timezone'));
                        $updated = $table->updated_at->copy()->timezone(config('app.timezone'));

                        return [
                            'id' => $table->id,
                            'name' => $table->name,
                            'status' => $table->status,
                            'is_sub_table' => $table->is_sub_table,
                            'waiter_id' => $table->waiter ? $table->waiter->id : null,
                            'waiter_name' => $table->waiter ? $table->waiter->name : null,
                            'date_created' => $created->format('Y-m-d'),
                            'time_created' => $created->format('h:i A'),
                            'date_updated' => $updated->format('Y-m-d'),
                            'time_updated' => $updated->format('h:i A'),
                        ];
                    }),
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'overall_summary' => $overallSummary,
            'floor_summary' => $floorSummary
        ]);
    }

    // =========================
    // CREATE NEW TABLE
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'floor_id' => 'required|exists:floors,id',
            'status' => 'nullable|in:free,reserved',
            'waiter_id' => 'nullable|exists:waiters,id',
        ]);

        $table = Table::create([
            'name' => $request->name,
            'floor_id' => $request->floor_id,
            'status' => $request->status ?? 'free',
            'waiter_id' => $request->waiter_id ?? null,
        ]);

        $table->load('floor');

        $created = $table->created_at->copy()->timezone(config('app.timezone'));
        $updated = $table->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Table added successfully',
            'data' => [
                'id' => $table->id,
                'name' => $table->name,
                'status' => $table->status,
                'is_sub_table' => $table->is_sub_table,
                'waiter_id' => $table->waiter ? $table->waiter->id : null,
                'waiter_name' => $table->waiter ? $table->waiter->name : null,
                'floor_id' => $table->floor_id,
                'floor_name' => $table->floor ? $table->floor->name : null,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // UPDATE TABLE
    // =========================
    public function update(Request $request, $id)
    {
        $table = Table::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'floor_id' => 'required|exists:floors,id',
            'status' => 'nullable|in:free,reserved',
            'waiter_id' => 'nullable|exists:waiters,id',
        ]);

        $table->update([
            'name' => $request->name,
            'floor_id' => $request->floor_id,
            'status' => $request->status ?? $table->status,
            'waiter_id' => $request->waiter_id ?? $table->waiter_id,
        ]);

        $table->load('floor');

        $created = $table->created_at->copy()->timezone(config('app.timezone'));
        $updated = $table->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Table updated successfully',
            'data' => [
                'id' => $table->id,
                'name' => $table->name,
                'status' => $table->status,
                'is_sub_table' => $table->is_sub_table,
                'waiter_id' => $table->waiter ? $table->waiter->id : null,
                'waiter_name' => $table->waiter ? $table->waiter->name : null,
                'floor_id' => $table->floor_id,
                'floor_name' => $table->floor ? $table->floor->name : null,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // DELETE TABLE
    // =========================
    public function destroy($id)
    {
        $table = Table::with('subTables')->findOrFail($id);

        if ($table->status === 'reserved') {
            return response()->json([
                'status' => false,
                'message' => 'Reserved table cannot be deleted'
            ], 400);
        }

        if (!$table->is_sub_table && $table->subTables->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Parent table cannot be deleted while sub tables exist'
            ], 400);
        }

        $table->delete();

        return response()->json([
            'status' => true,
            'message' => 'Table deleted successfully'
        ]);
    }

    // =========================
    // RESERVED TABLES
    // =========================
    public function reservedTables()
    {
        $tables = Table::with(['floor', 'waiter'])
            ->where('status', 'reserved')
            ->get();

        if ($tables->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No reserved tables found',
                'data' => []
            ]);
        }

        $data = $tables->map(function ($table) {
            $created = $table->created_at->copy()->timezone(config('app.timezone'));
            $updated = $table->updated_at->copy()->timezone(config('app.timezone'));

            return [
                'id' => $table->id,
                'name' => $table->name,
                'floor_id' => $table->floor_id,
                'floor_name' => $table->floor ? $table->floor->name : null,
                'waiter_id' => $table->waiter_id,
                'waiter_name' => $table->waiter ? $table->waiter->name : null,
                'status' => $table->status,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // =========================
    // RESERVED TABLES BY WAITER (WITH NET TOTAL)
    // =========================
    public function reservedTablesByWaiter($waiterId)
    {
        $waiterId = (int) $waiterId;

        $tables = Table::with(['floor', 'waiter', 'orders.items.menuItem'])
            ->where('status', 'reserved')
            ->where('waiter_id', $waiterId)
            ->get();

        if ($tables->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No reserved tables found for this waiter',
                'data' => [],
                'total_order_price' => 0,
                'net_total' => 0
            ]);
        }

        $totalOrderPrice = 0;

        $data = $tables->map(function ($table) use (&$totalOrderPrice) {
            $ordersData = $table->orders
                ->where('status', 'in_progress')
                ->map(function ($order) use (&$totalOrderPrice) {
                    $items = $order->items->map(function ($item) {
                        return [
                            'menu_item_id' => $item->menu_item_id,
                            'menu_item_name' => $item->menuItem ? $item->menuItem->name : null,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'total' => $item->total,
                        ];
                    });

                    $totalOrderPrice += $order->grand_total;

                    return [
                        'order_id' => $order->id,
                        'type' => $order->type,
                        'status' => $order->status,
                        'grand_total' => $order->grand_total,
                        'discount' => $order->discount,
                        'net_total' => $order->net_total,
                        'cash_received' => $order->cash_received,
                        'change_due' => $order->change_due,
                        'items' => $items,
                        'date_created' => $order->created_at->copy()->timezone(config('app.timezone'))->format('Y-m-d'),
                        'time_created' => $order->created_at->copy()->timezone(config('app.timezone'))->format('h:i A'),
                    ];
                })
                ->values();

            return [
                'table_id' => $table->id,
                'table_name' => $table->name,
                'floor_id' => $table->floor_id,
                'floor_name' => $table->floor ? $table->floor->name : null,
                'waiter_id' => $table->waiter_id,
                'waiter_name' => $table->waiter ? $table->waiter->name : null,
                'status' => $table->status,
                'orders' => $ordersData,
            ];
        });

        $moneyInserted = WaiterTransaction::where('waiter_id', $waiterId)
            ->sum(DB::raw("CASE WHEN type='deposit' THEN amount ELSE -amount END"));

        $netTotal = $totalOrderPrice - $moneyInserted;

        return response()->json([
            'status' => true,
            'data' => $data,
            'total_order_price' => $totalOrderPrice,
            'money_inserted' => $moneyInserted,
            'net_total' => $netTotal
        ]);
    }

    // =========================
    // INSERT MONEY TO WAITER
    // =========================
    public function insertMoneyToWaiter(Request $request, $waiterId)
    {
        $waiterId = (int) $waiterId;

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        $transaction = WaiterTransaction::create([
            'waiter_id' => $waiterId,
            'type' => 'deposit',
            'amount' => $request->amount,
            'note' => $request->note,
        ]);

        $created = $transaction->created_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Money inserted successfully',
            'data' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'note' => $transaction->note,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // RETURN MONEY FROM WAITER
    // =========================
    public function returnMoneyFromWaiter(Request $request, $waiterId)
    {
        $waiterId = (int) $waiterId;

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        $transaction = WaiterTransaction::create([
            'waiter_id' => $waiterId,
            'type' => 'return',
            'amount' => $request->amount,
            'note' => $request->note,
        ]);

        $created = $transaction->created_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Money returned successfully',
            'data' => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'note' => $transaction->note,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // WAITER TRANSACTION LOG
    // =========================
    public function waiterTransactions($waiterId)
    {
        $waiterId = (int) $waiterId;

        $waiter = \App\Models\Waiter::with('transactions')->find($waiterId);

        if (!$waiter) {
            return response()->json([
                'status' => false,
                'message' => 'Waiter not found',
                'summary' => [
                    'total_inserted' => 0,
                    'total_returned' => 0,
                    'net_total' => 0,
                ],
                'transactions' => []
            ]);
        }

        $transactions = $waiter->transactions->sortByDesc('created_at')->values();

        $totalInserted = $transactions->where('type', 'deposit')->sum('amount');
        $totalReturned = $transactions->where('type', 'return')->sum('amount');
        $netTotal = $totalInserted - $totalReturned;

        $transactionsFormatted = $transactions->map(function ($tx) {
            $created = $tx->created_at->copy()->timezone(config('app.timezone'));

            return [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => $tx->amount,
                'note' => $tx->note,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => true,
            'waiter' => [
                'id' => $waiter->id,
                'name' => $waiter->name,
            ],
            'summary' => [
                'total_inserted' => $totalInserted,
                'total_returned' => $totalReturned,
                'net_total' => $netTotal,
            ],
            'transactions' => $transactionsFormatted
        ]);
    }

    // =========================
    // DIVIDE TABLE INTO SUB-TABLE
    // =========================
    public function divideTable($id)
    {
        $table = Table::with('subTables')->findOrFail($id);

        if ($table->is_sub_table) {
            return response()->json([
                'status' => false,
                'message' => 'Sub tables cannot be divided'
            ], 400);
        }

        $count = $table->subTables->count();
        $nextLetter = chr(65 + $count);

        $subTable = Table::create([
            'name' => $table->name . $nextLetter,
            'floor_id' => $table->floor_id,
            'waiter_id' => $table->waiter_id,
            'status' => 'free',
            'parent_table_id' => $table->id,
            'is_sub_table' => true,
        ]);

        $created = $subTable->created_at->copy()->timezone(config('app.timezone'));
        $updated = $subTable->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Table divided successfully',
            'data' => [
                'id' => $subTable->id,
                'name' => $subTable->name,
                'floor_id' => $subTable->floor_id,
                'waiter_id' => $subTable->waiter_id,
                'is_sub_table' => true,
                'parent_table_id' => $subTable->parent_table_id,
                'status' => $subTable->status,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ]);
    }
}
