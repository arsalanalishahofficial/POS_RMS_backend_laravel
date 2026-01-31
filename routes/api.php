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

// -------------------
// PUBLIC ROUTES
// -------------------
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::get('/restaurant-info', [RestaurantsInfoController::class, 'index']);
Route::get('/terminals', [TerminalController::class, 'index']);
Route::get('/terminals/getByClientIp', [TerminalController::class, 'getByClientIp']);
Route::post('/terminals/updateIP', [TerminalController::class, 'updateIP']);

// -------------------
// AUTHENTICATED ROUTES
// -------------------
Route::middleware('auth:sanctum')->group(function () {

    // -------------------
    // ORDERS
    // -------------------
    Route::get('/orders/dinein/table/{tableId}', [OrderController::class, 'dineInOrderByTable']);
    Route::get('/orders/takeaway/{receiptNumber}', [OrderController::class, 'takeawayOrderById']);
    Route::get('/orders/delivery', [OrderController::class, 'deliveryOrdersByStatus']);
    Route::get('/orders/in-progress', [OrderController::class, 'inProgress']);
    Route::get('/orders/{id}', [OrderController::class, 'show']); // generic order route last

    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::patch('/orders/{receiptNumber}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/complete', [OrderController::class, 'complete']);
    Route::post('/orders/complete-all', [OrderController::class, 'completeAll']);
    Route::post('/orders/complete-by-waiter/{waiterId}', [OrderController::class, 'completeAllByWaiter']);
    Route::post('/orders/{order}/collect-remaining', [OrderController::class, 'collectRemaining']);
    Route::post('/orders/{orderId}/delivery-status', [OrderController::class, 'changeDeliveryStatus']);
    Route::post('/orders/bulk/delivery-status', [OrderController::class, 'bulkChangeDeliveryStatus']);

    Route::get('/riders/{riderId}/stats', [OrderController::class, 'riderStats']);
    Route::get('/riders/stats', [OrderController::class, 'allRidersStats']);

    // -------------------
    // WAITERS
    // -------------------
    Route::get('/getallwaiters', [WaiterController::class, 'index']);
    Route::post('/addwaiter', [WaiterController::class, 'store']);
    Route::put('/waiters/{id}', [WaiterController::class, 'update']);
    Route::delete('/waiters/{id}', [WaiterController::class, 'destroy']);

    // -------------------
    // TABLES
    // -------------------
    Route::get('/getalltables', [TableController::class, 'index']);
    Route::get('/tables/reserved/summary', [TableController::class, 'reservedTablesSummary']);
    Route::get('/tables/reserved', [TableController::class, 'reservedTables']);
    Route::get('tables/reserved/waiter/{waiterId}', [TableController::class, 'reservedTablesByWaiter']);

    Route::post('/addtables', [TableController::class, 'store']);
    Route::put('tables/{id}', [TableController::class, 'update']);
    Route::delete('tables/{id}', [TableController::class, 'destroy']);
    Route::post('/tables/{id}/divide', [TableController::class, 'divideTable']);

    Route::post('waiters/{waiterId}/insert-money', [TableController::class, 'insertMoneyToWaiter']);
    Route::post('waiters/{waiterId}/return-money', [TableController::class, 'returnMoneyFromWaiter']);
    Route::get('waiters/{waiterId}/transactions', [TableController::class, 'waiterTransactions']);

    // -------------------
    // FLOORS
    // -------------------
    Route::get('/getallfloors', [FloorController::class, 'index']);
    Route::post('/addfloors', [FloorController::class, 'store']);
    Route::put('floors/{id}', [FloorController::class, 'update']);
    Route::delete('floors/{id}', [FloorController::class, 'destroy']);
    Route::get('/floors/reserved-count', [FloorController::class, 'floorsWithReservedCount']);

    // -------------------
    // MENU & KITCHEN
    // -------------------
    Route::get('/menu', [KitchenController::class, 'fullMenu']);
    Route::post('/kitchens', [KitchenController::class, 'store']);
    Route::get('/kitchens', [KitchenController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/menu-items', [MenuItemController::class, 'store']);
    Route::get('/menu-items', [MenuItemController::class, 'index']);
    Route::patch('/menu-items/{id}/toggle', [MenuItemController::class, 'toggleAvailability']);

    // -------------------
    // SHIFT (MANAGER)
    // -------------------
    Route::middleware('role:manager')->group(function () {
        Route::post('/shift/pause', [ShiftController::class, 'pauseShift']);
        Route::post('/shift/resume', [ShiftController::class, 'resumeShift']);
        Route::get('/manager/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome Manager!']));
    });

    // -------------------
    // OWNER ONLY
    // -------------------
    Route::middleware('role:owner')->group(function () {
        Route::post('/restaurant-info', [RestaurantsInfoController::class, 'store']);
        Route::post('/terminals', [TerminalController::class, 'store']);
        Route::post('/shift/start', [ShiftController::class, 'startShift']);
        Route::post('/shift/close', [ShiftController::class, 'closeShift']);
        Route::post('/shift/pauseHistory', [ShiftController::class, 'pauseHistory']);
        Route::get('/shifts/{shift_id}/user/{user_id}', [ShiftController::class, 'userLogHistory']);
        Route::post('/cash-in', [CashInController::class, 'store']);
        Route::get('/reports/overall-cash-in', [CashInController::class, 'overallCashInToCasher']);
        Route::get('/reports/overall-cash-out', [CashOutController::class, 'overallCashOutByCasher']);
        Route::get('/menu-items/{id}/price-history', [OrderController::class, 'priceHistory']);
        Route::get('/owner/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome owner!']));
    });

    // -------------------
    // CASHER ONLY
    // -------------------
    Route::middleware('role:casher')->group(function () {
        Route::get('/my/cash-ins', [CashInController::class, 'myCashInsWithTotal']);
        Route::get('/my/cash-outs', [CashOutController::class, 'myCashOutsWithTotal']);
        Route::post('/cash-out', [CashOutController::class, 'store']);
        Route::get('/casher/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome casher!']));
    });

    // -------------------
    // OTHER DASHBOARDS
    // -------------------
    Route::middleware('role:waiter')->get('/waiter/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome waiter!']));
    Route::middleware('role:kitchen_staff')->get('/kitchen_staff/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome kitchen staff!']));
    Route::middleware('role:store_manager')->get('/store_manager/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome store manager!']));
    Route::middleware('role:accountant')->get('/accountant/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome accountant!']));
    Route::middleware('role:branch_manager')->get('/branch_manager/dashboard', fn() => response()->json(['status' => true, 'message' => 'Welcome branch manager!']));

    // -------------------
    // LOGOUT
    // -------------------
    Route::post('/logout', [AuthController::class, 'logout']);
});
