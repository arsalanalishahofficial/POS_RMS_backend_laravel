<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Waiter;
use Exception;

class WaiterController extends Controller
{
    // =========================
    // LIST ALL WAITERS
    // =========================
    public function index()
    {
        try {
            // Get the app timezone from config
            $timezone = config('app.timezone');

            // Fetch waiters with reserved table count
            $waiters = Waiter::withCount([
                'tables as reserved_table_count' => function ($query) {
                    $query->where('status', 'reserved');
                }
            ])->get();

            $data = $waiters->map(function ($waiter) use ($timezone) {
                return [
                    'id' => $waiter->id,
                    'name' => $waiter->name,
                    'reserved_table_count' => $waiter->reserved_table_count,
                    'created' => [
                        'date' => $waiter->created_at ? $waiter->created_at->timezone($timezone)->toDateString() : null,
                        'time' => $waiter->created_at ? $waiter->created_at->timezone($timezone)->toTimeString() : null,
                    ]
                ];
            });

            return response()->json([
                'status' => true,
                'message' => $data->isEmpty() ? 'No waiters found' : 'Waiters retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch waiters',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }



    // =========================
    // CREATE NEW WAITER
    // =========================
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $waiter = Waiter::create([
                'name' => $request->name,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Waiter added successfully',
                'data' => $waiter
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to add waiter',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // UPDATE EXISTING WAITER
    // =========================
    public function update(Request $request, $id)
    {
        try {
            $waiter = Waiter::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $waiter->update([
                'name' => $request->name,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Waiter updated successfully',
                'data' => $waiter
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update waiter',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // DELETE WAITER
    // =========================
    public function destroy($id)
    {
        try {
            $waiter = Waiter::findOrFail($id);
            $waiter->delete();

            return response()->json([
                'status' => true,
                'message' => 'Waiter deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete waiter',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
