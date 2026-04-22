<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Order\Http\Controllers\CartController;
use App\Modules\Order\Http\Controllers\OrderController;
use App\Modules\User\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public catalog routes — no auth required
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', fn (Request $request) => $request->user());

    // Cart
    Route::get('/cart', [CartController::class, 'viewCart']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);

    // Checkout & orders
    Route::post('/checkout', [OrderController::class, 'checkout']);

    // Seller product management
    Route::post('/seller/products', [ProductController::class, 'store']);
    Route::put('/seller/products/{id}', [ProductController::class, 'update']);
    Route::delete('/seller/products/{id}', [ProductController::class, 'destroy']);
});
