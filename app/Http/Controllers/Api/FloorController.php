<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Floor;

class FloorController extends Controller
{
    public function index()
    {
        $floors = Floor::all(); // withTrashed() if needed
        return response()->json([
            'status' => true,
            'data' => $floors
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $floor = Floor::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Floor added successfully',
            'data' => $floor
        ]);
    }

    public function update(Request $request, $id)
    {
        $floor = Floor::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $floor->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Floor updated successfully',
            'data' => $floor
        ]);
    }

    public function destroy($id)
    {
        $floor = Floor::findOrFail($id);
        $floor->delete();

        return response()->json([
            'status' => true,
            'message' => 'Floor deleted successfully',
        ]);
    }
}
