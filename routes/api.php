<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\GpsWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// GPS Webhook (authenticated via API key)
Route::post('/webhooks/gps', [GpsWebhookController::class, 'receive']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Assets
    Route::apiResource('assets', AssetController::class);
    Route::get('/assets/{asset}/location', [AssetController::class, 'location']);
    Route::get('/assets/{asset}/route', [AssetController::class, 'route']);
    
    // Fuel Reports
    Route::get('/assets/{asset}/fuel-reports', [App\Http\Controllers\Api\FuelReportController::class, 'index']);
    
    // Subscriptions
    Route::apiResource('subscriptions', App\Http\Controllers\Api\SubscriptionController::class);
    Route::post('/subscriptions/{subscription}/renew', [App\Http\Controllers\Api\SubscriptionController::class, 'renew']);
    
    // Geofences
    Route::apiResource('geofences', App\Http\Controllers\Api\GeofenceController::class);
    Route::get('/assets/{asset}/geofence-breaches', [App\Http\Controllers\Api\GeofenceController::class, 'breaches']);
    
    // Remote Control
    Route::post('/assets/{asset}/remote-shutdown/request', [App\Http\Controllers\Api\RemoteControlController::class, 'requestShutdown']);
    Route::post('/assets/{asset}/remote-shutdown/execute', [App\Http\Controllers\Api\RemoteControlController::class, 'executeShutdown']);
    
    // Notifications
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
});