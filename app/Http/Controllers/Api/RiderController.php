<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rider;

class RiderController extends Controller
{
    public function index()
    {
        $riders = Rider::all();
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
