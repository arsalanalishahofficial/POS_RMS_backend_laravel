<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Floor;

class FloorController extends Controller
{
    // =========================
    // LIST ALL FLOORS
    // =========================
    public function index()
    {
        $floors = Floor::all()->map(function ($floor) {
            $created = $floor->created_at->copy()->timezone(config('app.timezone'));
            $updated = $floor->updated_at->copy()->timezone(config('app.timezone'));

            return [
                'id' => $floor->id,
                'name' => $floor->name,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $floors
        ]);
    }

    // =========================
    // CREATE FLOOR
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $floor = Floor::create([
            'name' => $request->name,
        ]);

        $created = $floor->created_at->copy()->timezone(config('app.timezone'));
        $updated = $floor->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Floor added successfully',
            'data' => [
                'id' => $floor->id,
                'name' => $floor->name,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // UPDATE FLOOR
    // =========================
    public function update(Request $request, $id)
    {
        $floor = Floor::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $floor->update([
            'name' => $request->name,
        ]);

        $created = $floor->created_at->copy()->timezone(config('app.timezone'));
        $updated = $floor->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Floor updated successfully',
            'data' => [
                'id' => $floor->id,
                'name' => $floor->name,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ]);
    }

    // =========================
    // DELETE FLOOR
    // =========================
    public function destroy($id)
    {
        $floor = Floor::findOrFail($id);
        $floor->delete();

        return response()->json([
            'status' => true,
            'message' => 'Floor deleted successfully',
        ]);
    }

    // =========================
    // FLOORS WITH RESERVED TABLES COUNT
    // =========================
    public function floorsWithReservedCount()
    {
        $floors = Floor::withCount([
            'tables as reserved_tables_count' => function ($query) {
                $query->where('status', 'reserved');
            }
        ])->get()->map(function ($floor) {
            $created = $floor->created_at->copy()->timezone(config('app.timezone'));
            $updated = $floor->updated_at->copy()->timezone(config('app.timezone'));

            return [
                'id' => $floor->id,
                'name' => $floor->name,
                'reserved_tables_count' => $floor->reserved_tables_count,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $floors
        ]);
    }
}
