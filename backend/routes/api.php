<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{room}', [RoomController::class, 'show']);
    Route::post('/rooms', [RoomController::class, 'store'])->middleware('role:landlord,admin');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->middleware('role:landlord,admin');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->middleware('role:landlord,admin');
    Route::patch('/rooms/{room}/availability', [RoomController::class, 'toggleAvailability'])->middleware('role:landlord,admin');
    Route::post('/rooms/{room}/images', [RoomController::class, 'uploadImages'])->middleware('role:landlord,admin');
    Route::delete('/rooms/{room}/images/{image}', [RoomController::class, 'destroyImage'])->middleware('role:landlord,admin');

    Route::post('/rooms/{room}/inquiries', [InquiryController::class, 'store'])->middleware('role:tenant');
    Route::get('/inquiries', [InquiryController::class, 'index']);
    Route::post('/inquiries/{inquiry}/approve', [InquiryController::class, 'approve'])->middleware('role:landlord,admin');
    Route::post('/inquiries/{inquiry}/reject', [InquiryController::class, 'reject'])->middleware('role:landlord,admin');

    Route::get('/leases', [LeaseController::class, 'index']);
    Route::post('/leases', [LeaseController::class, 'store'])->middleware('role:landlord,admin');
    Route::post('/leases/{lease}/end', [LeaseController::class, 'end'])->middleware('role:landlord,admin');
    Route::post('/leases/{lease}/charges', [LeaseController::class, 'storeCharge'])->middleware('role:landlord,admin');
    Route::post('/charges/{charge}/void', [LeaseController::class, 'voidCharge'])->middleware('role:landlord,admin');

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->middleware('role:landlord,admin');
    Route::get('/invoices/current', [InvoiceController::class, 'current'])->middleware('role:tenant');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->middleware('role:landlord,admin');

    Route::get('/landlord/dashboard', [DashboardController::class, 'landlord'])->middleware('role:landlord,admin');

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });

    Route::get('/tenants', [TenantController::class, 'index'])->middleware('role:landlord,admin');
});
