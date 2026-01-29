<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kitchen;

class KitchenController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' =>   Kitchen::all()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        $kitchen = Kitchen::create($request->only('name'));

        return response()->json([
            'status' => true,
            'message' => 'Kitchen created',
            'data' => $kitchen
        ], 201);
    }

    public function fullMenu()
    {
        return response()->json([
            'status' => true,
            'data' => Kitchen::with('categories.menuItems')->get()
        ]);
    }

}
