<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MenuItem;

class MenuItemController extends Controller
{

    public function index(Request $request)
    {
        $categoryId = $request->query('category_id');
        $kitchenId = $request->query('kitchen_id');

        $query = MenuItem::with(['category.kitchen']);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($kitchenId) {
            $query->whereHas('category', function ($q) use ($kitchenId) {
                $q->where('kitchen_id', $kitchenId);
            });
        }

        $items = $query->get();

        $data = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'is_available' => $item->is_available,
                'category_id' => $item->category->id ?? null,
                'category_name' => $item->category->name ?? null,
                'kitchen_id' => $item->category->kitchen->id ?? null,
                'kitchen_name' => $item->category->kitchen->name ?? null,
            ];
        });

        return response()->json([
            'status' => $data->isEmpty() ? false : true,
            'message' => $data->isEmpty() ? 'No menu items found for this category & kitchen' : "",
            'data' => $data
        ]);
    }



    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'category_id' => 'required|exists:categories,id'
        ]);

        $item = MenuItem::create([
            'name' => $request->name,
            'price' => $request->price,
            'category_id' => $request->category_id,
            'is_available' => true
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Menu item added',
            'data' => $item
        ], 201);
    }

    public function toggleAvailability($id)
    {
        $item = MenuItem::findOrFail($id);
        $item->is_available = !$item->is_available;
        $item->save();

        return response()->json([
            'status' => true,
            'message' => 'Availability updated',
            'data' => $item
        ]);
    }


}
