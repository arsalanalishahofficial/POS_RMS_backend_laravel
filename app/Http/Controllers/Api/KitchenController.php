<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kitchen;

class KitchenController extends Controller
{
    // =========================
    // LIST ALL KITCHENS
    // =========================
    public function index()
    {
        $kitchens = Kitchen::all()->map(function ($kitchen) {
            $created = $kitchen->created_at->copy()->timezone(config('app.timezone'));
            $updated = $kitchen->updated_at->copy()->timezone(config('app.timezone'));

            return [
                'id' => $kitchen->id,
                'name' => $kitchen->name,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $kitchens
        ]);
    }

    // =========================
    // CREATE KITCHEN
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        $kitchen = Kitchen::create($request->only('name'));

        $created = $kitchen->created_at->copy()->timezone(config('app.timezone'));
        $updated = $kitchen->updated_at->copy()->timezone(config('app.timezone'));

        return response()->json([
            'status' => true,
            'message' => 'Kitchen created',
            'data' => [
                'id' => $kitchen->id,
                'name' => $kitchen->name,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
            ]
        ], 201);
    }

    // =========================
    // FULL MENU WITH KITCHENS, CATEGORIES, MENU ITEMS
    // =========================
    public function fullMenu()
    {
        $kitchens = Kitchen::with(['categories.menuItems'])->get()->map(function ($kitchen) {
            $created = $kitchen->created_at->copy()->timezone(config('app.timezone'));
            $updated = $kitchen->updated_at->copy()->timezone(config('app.timezone'));

            return [
                'id' => $kitchen->id,
                'name' => $kitchen->name,
                'date_created' => $created->format('Y-m-d'),
                'time_created' => $created->format('h:i A'),
                'date_updated' => $updated->format('Y-m-d'),
                'time_updated' => $updated->format('h:i A'),
                'categories' => $kitchen->categories->map(function ($category) {
                    $catCreated = $category->created_at->copy()->timezone(config('app.timezone'));
                    $catUpdated = $category->updated_at->copy()->timezone(config('app.timezone'));

                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'date_created' => $catCreated->format('Y-m-d'),
                        'time_created' => $catCreated->format('h:i A'),
                        'date_updated' => $catUpdated->format('Y-m-d'),
                        'time_updated' => $catUpdated->format('h:i A'),
                        'menu_items' => $category->menuItems->map(function ($menuItem) {
                            $miCreated = $menuItem->created_at->copy()->timezone(config('app.timezone'));
                            $miUpdated = $menuItem->updated_at->copy()->timezone(config('app.timezone'));

                            return [
                                'id' => $menuItem->id,
                                'name' => $menuItem->name,
                                'price' => $menuItem->price,
                                'date_created' => $miCreated->format('Y-m-d'),
                                'time_created' => $miCreated->format('h:i A'),
                                'date_updated' => $miUpdated->format('Y-m-d'),
                                'time_updated' => $miUpdated->format('h:i A'),
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $kitchens
        ]);
    }
}
