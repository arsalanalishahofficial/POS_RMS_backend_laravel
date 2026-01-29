<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with('kitchen');

        if ($request->has('kitchen_id')) {
            $query->where('kitchen_id', $request->kitchen_id);
        }

        if ($request->has('category_id')) {
            $query->where('id', $request->category_id);
        }

        $categories = $query->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'kitchen_id' => $category->kitchen_id,
                'kitchen_name' => $category->kitchen->name ?? null,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at
            ];
        });

        return response()->json([
            'status' => $categories->isEmpty() ? false : true,
            'data' => $categories,
            'message' => $categories->isEmpty() ? 'No data available' : null
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'kitchen_id' => 'required|exists:kitchens,id'
        ]);

        $category = Category::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Category created',
            'data' => $category
        ], 201);
    }
}
