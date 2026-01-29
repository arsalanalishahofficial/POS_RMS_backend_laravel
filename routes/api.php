<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\ShiftController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {


    Route::get('/menu', [KitchenController::class, 'fullMenu']);
    Route::post('/kitchens', [KitchenController::class, 'store']);
    Route::get('/kitchens', [KitchenController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories', [CategoryController::class, 'index']);

    Route::post('/menu-items', [MenuItemController::class, 'store']);
    Route::get('/menu-items', [MenuItemController::class, 'index']);
    Route::patch('/menu-items/{id}/toggle', [MenuItemController::class, 'toggleAvailability']);



    Route::post('/shift/pause', [ShiftController::class, 'pauseShift']);
    Route::post('/shift/resume', [ShiftController::class, 'resumeShift']);

    Route::middleware('role:owner')->group(function () {
        Route::post('/shift/start', [ShiftController::class, 'startShift']);
        Route::post('/shift/close', [ShiftController::class, 'closeShift']);
        Route::post('/shift/todayShiftPauses', [ShiftController::class, 'todayShiftPauses']);
        Route::get(
            '/shifts/{shift_id}/user/{user_id}',
            [ShiftController::class, 'userLoginLogoutByShift']
        );
    });




    Route::middleware('role:owner')->get(
        '/owner/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome owner!'])
    );

    Route::middleware('role:casher')->get(
        '/casher/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome casher!'])
    );

    Route::middleware('role:manager')->get(
        '/manager/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome Manager!'])
    );

    Route::middleware('role:waiter')->get(
        '/waiter/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome waiter!'])
    );

    Route::middleware('role:kitchen_staff')->get(
        '/kitchen_staff/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome kitchen staff!'])
    );

    Route::middleware('role:store_manager')->get(
        '/store_manager/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome store manager!'])
    );

    Route::middleware('role:accountant')->get(
        '/accountant/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome accountant!'])
    );

    Route::middleware('role:branch_manager')->get(
        '/branch_manager/dashboard',
        fn() =>
        response()->json(['status' => true, 'message' => 'Welcome branch manager!'])
    );

    Route::post('/logout', [AuthController::class, 'logout']);
});
