<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Rutas protegidas con JWT
Route::middleware(['jwt.verify'])->group(function () {
    // Rutas accesibles para todos los usuarios autenticados
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    
    // Rutas para clientes (client y admin pueden acceder)
    Route::post('/bookings',              [BookingController::class, 'store']);
    Route::get('/bookings',               [BookingController::class, 'index']);
    Route::delete('/bookings/{id}/cancel',[BookingController::class, 'cancel']);
    
    // Rutas exclusivas para admin
    Route::middleware(['role:admin'])->group(function () {
        // Aquí puedes agregar rutas solo para administradores
        // Ejemplo: Route::get('/admin/users', [AdminController::class, 'listUsers']);
    });
});
