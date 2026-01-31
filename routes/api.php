<?php

use App\Http\Controllers\Api\FloorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\CashInController;
use App\Http\Controllers\Api\CashOutController;
use App\Http\Controllers\Api\RestaurantsInfoController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WaiterController;
use App\Http\Controllers\Api\TableController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::get('/restaurant-info', [RestaurantsInfoController::class, 'index']);
Route::get('/terminals', [TerminalController::class, 'index']);
Route::get('/terminals/getByClientIp', [TerminalController::class, 'getByClientIp']);
Route::post('/terminals/updateIP', [TerminalController::class, 'updateIP']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/in-progress', [OrderController::class, 'inProgress']); // put before {id}
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/complete', [OrderController::class, 'complete']);
    Route::post('/orders/complete-all', [OrderController::class, 'completeAll']);
    // Update an existing order (add/update items)
    Route::put('orders/{id}', [OrderController::class, 'update']);
    // Get active dine-in order by table ID
    Route::get('/orders/dinein/table/{tableId}', [OrderController::class, 'dineInOrderByTable']);
    // Get takeaway order by order ID
    Route::get('/orders/takeaway/{orderId}', [OrderController::class, 'takeawayOrderById']);

    Route::post(
        '/orders/{order}/collect-remaining',
        [OrderController::class, 'collectRemaining']
    );
    Route::get('/getallwaiters', [WaiterController::class, 'index']);
    Route::post('/addwaiter', [WaiterController::class, 'store']);
    Route::put('/waiters/{id}', [WaiterController::class, 'update']);
    Route::delete('/waiters/{id}', [WaiterController::class, 'destroy']);

    Route::get('/getalltables', [TableController::class, 'index']);
    Route::post('/addtables', [TableController::class, 'store']);
    Route::put('tables/{id}', [TableController::class, 'update']);
    Route::delete('tables/{id}', [TableController::class, 'destroy']);
    Route::get('/tables/reserved', [TableController::class, 'reservedTables']);
    Route::get('tables/reserved/waiter/{waiterId}', [TableController::class, 'reservedTablesByWaiter']);
    Route::post('/tables/{id}/divide', [TableController::class, 'divideTable']);
    Route::post('waiters/{waiterId}/insert-money', [TableController::class, 'insertMoneyToWaiter']);
    Route::post('waiters/{waiterId}/return-money', [TableController::class, 'returnMoneyFromWaiter']);
    Route::get('waiters/{waiterId}/transactions', [TableController::class, 'waiterTransactions']);


    Route::get('/getallfloors', [FloorController::class, 'index']);
    Route::post('/addfloors', [FloorController::class, 'store']);
    Route::put('floors/{id}', [FloorController::class, 'update']);
    Route::delete('floors/{id}', [FloorController::class, 'destroy']);
    Route::get('/floors/reserved-count', [FloorController::class, 'floorsWithReservedCount']);



    Route::get('/menu', [KitchenController::class, 'fullMenu']);
    Route::post('/kitchens', [KitchenController::class, 'store']);
    Route::get('/kitchens', [KitchenController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories', [CategoryController::class, 'index']);

    Route::post('/menu-items', [MenuItemController::class, 'store']);
    Route::get('/menu-items', [MenuItemController::class, 'index']);
    Route::patch('/menu-items/{id}/toggle', [MenuItemController::class, 'toggleAvailability']);



    Route::middleware('role:manager')->group(function () {
        Route::post('/shift/pause', [ShiftController::class, 'pauseShift']);
        Route::post('/shift/resume', [ShiftController::class, 'resumeShift']);
    });


    Route::middleware('role:owner')->group(function () {

        Route::post('/restaurant-info', [RestaurantsInfoController::class, 'store']);
        Route::post('/terminals', [TerminalController::class, 'store']);

        Route::post('/shift/start', [ShiftController::class, 'startShift']);
        Route::post('/shift/close', [ShiftController::class, 'closeShift']);
        Route::post('/shift/pauseHistory', [ShiftController::class, 'pauseHistory']);
        Route::get(
            '/shifts/{shift_id}/user/{user_id}',
            [ShiftController::class, 'userLogHistory']
        );

        Route::post('/cash-in', [CashInController::class, 'store']);
        Route::get('/reports/overall-cash-in', [CashInController::class, 'overallCashInToCasher']);
        Route::get('/reports/overall-cash-out', [CashOutController::class, 'overallCashOutByCasher']);

        Route::get('/menu-items/{id}/price-history', [OrderController::class, 'priceHistory']);

    });

    Route::middleware('role:casher')->group(function () {
        Route::get('/my/cash-ins', [CashInController::class, 'myCashInsWithTotal']);
        Route::get('/my/cash-outs', [CashOutController::class, 'myCashOutsWithTotal']);
        Route::post('/cash-out', [CashOutController::class, 'store']);
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
