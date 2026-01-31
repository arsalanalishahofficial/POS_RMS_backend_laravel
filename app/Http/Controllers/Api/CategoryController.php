<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    // =========================
    // LIST CATEGORIES
    // =========================
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
            $created = $category->created_at->copy()->timezone(config('app.timezone'));
            $updated = $category->updated_at->copy()->timezone(config('app.timezone'));

            return [
                'id' => $category->id,
                'name' => $category->name,
                'kitchen_id' => $category->kitchen_id,
                'kitchen_name' => $category->kitchen->name ?? null,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => $categories->isEmpty() ? false : true,
            'data' => $categories,
            'message' => $categories->isEmpty() ? 'No data available' : null
        ]);
    }

    // =========================
    // CREATE CATEGORY
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'kitchen_id' => 'required|exists:kitchens,id'
        ]);

        $category = Category::create($request->all());

        $created = $category->created_at->copy()->timezone(config('app.timezone'));
        $updated = $category->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Category created',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'kitchen_id' => $category->kitchen_id,
                'kitchen_name' => $category->kitchen->name ?? null,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ], 201);
    }
}
