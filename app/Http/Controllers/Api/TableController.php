<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Table;

class TableController extends Controller
{
    // Get all tables
    public function index()
    {
        $tables = Table::with('floor')->get(); // include floor info

        return response()->json([
            'status' => true,
            'data' => $tables
        ]);
    }

    // Create new table
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'floor_id' => 'required|exists:floors,id',
            'status' => 'nullable|in:free,reserved',
            'waiter_id' => 'nullable|exists:waiters,id', // new
        ]);

        $table = Table::create([
            'name' => $request->name,
            'floor_id' => $request->floor_id,
            'status' => $request->status ?? 'free',
            'waiter_id' => $request->waiter_id ?? null,
        ]);


        $table->load('floor'); // include floor info

        return response()->json([
            'status' => true,
            'message' => 'Table added successfully',
            'data' => $table
        ]);
    }

    // Update table
    public function update(Request $request, $id)
    {
        $table = Table::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'floor_id' => 'required|exists:floors,id',      // validate floor exists
            'status' => 'nullable|in:free,reserved',       // updated to match ENUM
            'waiter_id' => 'nullable|exists:waiters,id',   // new waiter_id field
        ]);

        $table->update([
            'name' => $request->name,
            'floor_id' => $request->floor_id,
            'status' => $request->status ?? $table->status,
            'waiter_id' => $request->waiter_id ?? $table->waiter_id,  // assign waiter if provided
        ]);


        $table->load('floor'); // include floor info

        return response()->json([
            'status' => true,
            'message' => 'Table updated successfully',
            'data' => $table
        ]);
    }

    public function destroy($id)
    {
        $table = Table::with('subTables')->findOrFail($id);

        // Reserved table delete nahi ho sakti
        if ($table->status === 'reserved') {
            return response()->json([
                'status' => false,
                'message' => 'Reserved table cannot be deleted'
            ], 400);
        }

        // Parent table delete nahi ho sakti jab tak sub-tables exist hon
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


    // Get all reserved tables
    public function reservedTables()
    {
        $tables = Table::with(['floor', 'waiter']) // load waiter relationship
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
            return [
                'id' => $table->id,
                'name' => $table->name,
                'floor_id' => $table->floor_id,
                'floor_name' => $table->floor ? $table->floor->name : null,
                'waiter_id' => $table->waiter_id,
                'waiter_name' => $table->waiter ? $table->waiter->name : null, // added
                'status' => $table->status,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function reservedTablesByWaiter($waiterId)
    {
        $tables = Table::with(['floor', 'waiter', 'orders.items.menuItem'])
            ->where('status', 'reserved')
            ->where('waiter_id', $waiterId)
            ->get();

        if ($tables->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No reserved tables found for this waiter',
                'data' => []
            ]);
        }

        $data = $tables->map(function ($table) {
            // Filter only in-progress orders
            $ordersData = $table->orders
                ->where('status', 'in_progress')
                ->map(function ($order) {
                    $items = $order->items->map(function ($item) {
                        return [
                            'menu_item_id' => $item->menu_item_id,
                            'menu_item_name' => $item->menuItem ? $item->menuItem->name : null,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'total' => $item->total,
                        ];
                    });

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
                    ];
                })
                ->values(); // reset keys

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

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

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

        return response()->json([
            'status' => true,
            'message' => 'Table divided successfully',
            'data' => $subTable
        ]);
    }

}
