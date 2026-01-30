<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RestaurantsInfo;
use Exception;

class RestaurantsInfoController extends Controller
{
    public function index()
    {
        try {
            $info = RestaurantsInfo::first();

            if (!$info) {
                return response()->json([
                    'status' => false,
                    'message' => 'No restaurant info found',
                    'data' => null  
                ], 200); 
            }

            return response()->json([
                'status' => true,
                'data' => $info
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch restaurant info',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'address' => 'nullable|string',
                'phone_number' => 'nullable|string',
                'promo_tagline_top' => 'nullable|string',
                'promo_tagline_bottom' => 'nullable|string',
            ]);

            $info = RestaurantsInfo::first();

            if ($info) {
                $info->update($request->all());
            } else {
                $info = RestaurantsInfo::create($request->all());
            }

            return response()->json([
                'status' => true,
                'message' => 'Restaurant info saved successfully',
                'data' => $info
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to save restaurant info',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
