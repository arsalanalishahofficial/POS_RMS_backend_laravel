<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Table;

class TableController extends Controller
{
    public function index()
    {
        $tables = Table::all(); 
        return response()->json([
            'status' => true,
            'data' => $tables
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $table = Table::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Table added successfully',
            'data' => $table
        ]);
    }

    public function update(Request $request, $id)
    {
        $table = Table::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $table->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Table updated successfully',
            'data' => $table
        ]);
    }


    public function destroy($id)
    {
        $table = Table::findOrFail($id);
        $table->delete();

        return response()->json([
            'status' => true,
            'message' => 'Table deleted successfully',
        ]);
    }

}
