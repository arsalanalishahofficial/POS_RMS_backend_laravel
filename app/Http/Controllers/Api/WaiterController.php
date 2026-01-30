<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Waiter;

class WaiterController extends Controller
{
    public function index()
    {
        $waiters = Waiter::all(); 
        return response()->json([
            'status' => true,
            'data' => $waiters
        ]);
    }


    public function store(Request $request)
    {
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
        ]);
    }

    public function update(Request $request, $id)
    {
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
    }

    public function destroy($id)
    {
        $waiter = Waiter::findOrFail($id);
        $waiter->delete();

        return response()->json([
            'status' => true,
            'message' => 'Waiter deleted successfully',
        ]);
    }
}
