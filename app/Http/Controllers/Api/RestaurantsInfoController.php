<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RestaurantsInfo;
use Exception;

class RestaurantsInfoController extends Controller
{
    // =========================
    // HELPER: FORMAT RESTAURANT INFO
    // =========================
    private function formatInfo(RestaurantsInfo $info)
    {
        $created = $info->created_at->copy()->timezone(config('app.timezone'));
        $updated = $info->updated_at->copy()->timezone(config('app.timezone'));

        return [
            'id' => $info->id,
            'name' => $info->name,
            'address' => $info->address,
            'phone_number' => $info->phone_number,
            'promo_tagline_top' => $info->promo_tagline_top,
            'promo_tagline_bottom' => $info->promo_tagline_bottom,
            'date_created' => $created->format('Y-m-d'),
            'time_created' => $created->format('h:i A'),
            'date_updated' => $updated->format('Y-m-d'),
            'time_updated' => $updated->format('h:i A'),
        ];
    }

    // =========================
    // GET RESTAURANT INFO
    // =========================
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
                'data' => $this->formatInfo($info)
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

    // =========================
    // CREATE OR UPDATE RESTAURANT INFO
    // =========================
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
                'data' => $this->formatInfo($info)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to save restaurant info',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
