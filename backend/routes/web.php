<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'healthy',
        'service' => 'Boarding House Management System API',
        'version' => '1.0.0',
        'api_base' => '/api',
        'endpoints' => [
            'auth' => ['POST /api/login', 'POST /api/register', 'GET /api/me', 'POST /api/logout'],
            'rooms' => ['GET /api/rooms', 'POST /api/rooms', 'PUT /api/rooms/{id}', 'DELETE /api/rooms/{id}'],
            'leases' => ['GET /api/leases', 'POST /api/leases', 'POST /api/leases/{id}/end'],
            'inquiries' => ['GET /api/inquiries', 'POST /api/rooms/{id}/inquiries', 'POST /api/inquiries/{id}/approve', 'POST /api/inquiries/{id}/reject'],
            'billing' => ['GET /api/invoices', 'POST /api/invoices/generate', 'POST /api/invoices/{id}/payments', 'POST /api/leases/{id}/charges'],
            'dashboard' => ['GET /api/landlord/dashboard'],
            'admin' => ['GET /api/admin/users', 'POST /api/admin/users', 'PATCH /api/admin/users/{id}', 'GET /api/admin/activity-logs'],
        ],
    ]);
});
