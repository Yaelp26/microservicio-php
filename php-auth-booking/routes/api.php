<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas protegidas con JWT
Route::middleware(['jwt.verify'])->group(function () {
    Route::post('/bookings',            [BookingController::class, 'store']);
    Route::get('/bookings',             [BookingController::class, 'index']);
    Route::post('/bookings/{id}/cancel',[BookingController::class, 'cancel']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
});
