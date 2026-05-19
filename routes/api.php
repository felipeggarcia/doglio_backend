<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\CartController;
use App\Http\Controllers\V1\OrderController;
use App\Http\Controllers\V1\OrderStatusController;
use App\Http\Controllers\V1\UserAddressController;
use App\Http\Controllers\V1\PromotionController;
use App\Http\Controllers\V1\ReviewController;
use App\Http\Controllers\V1\FavoriteController;
use App\Http\Controllers\V1\PushTokenController;
use App\Http\Controllers\V1\StockMovementController;
use App\Http\Resources\UserResource;
// ==========================================
// API V1
// ==========================================

Route::prefix('v1')->group(function () {
    
    // ==========================================
    // ROTAS PÚBLICAS (Sem autenticação)
    // ==========================================

    // Autenticação — limite mais restrito (10/min por IP)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);//ok
        Route::post('/login', [AuthController::class, 'login']);//ok
    });

    // Produtos (Leitura pública)
    Route::middleware('throttle:public')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);//ok
        Route::get('/products/{product}', [ProductController::class, 'show']);//ok
        Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);//ok

        // Categorias (Leitura pública)
        Route::get('/categories', [CategoryController::class, 'index']);//ok
        Route::get('/categories/{category}', [CategoryController::class, 'show']);//ok
    });

    // ==========================================
    // ROTAS PROTEGIDAS (Requer autenticação)
    // ==========================================

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        
        // Autenticação
        Route::post('/logout', [AuthController::class, 'logout']);//ok
        Route::get('/user', [AuthController::class, 'user']);//ok

        // Carrinho
        Route::post('/cart/sync', [CartController::class, 'sync']);//ok
        Route::get('/cart', [CartController::class, 'show']);//ok
        Route::get('/cart/validate', [CartController::class, 'validate']);//ok
        Route::delete('/cart', [CartController::class, 'clear']);//ok

        // Checkout e Pedidos
        Route::post('/checkout', [OrderController::class, 'checkout']);//ok
        Route::get('/orders', [OrderController::class, 'index']);//ok
        Route::get('/orders/{order}', [OrderController::class, 'show']);//ok

        // Avaliações
        Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

        // Favoritos
        Route::get('/favorites', [FavoriteController::class, 'index']);//ok
        Route::post('/favorites', [FavoriteController::class, 'store']);//ok
        Route::delete('/favorites/{favorite}', [FavoriteController::class, 'destroy']);//ok
        Route::patch('/favorites/{favorite}/notify', [FavoriteController::class, 'toggleNotify']);//ok


        // Endereços do usuário
        Route::get('/addresses', [UserAddressController::class, 'index']);//ok
        Route::post('/addresses', [UserAddressController::class, 'store']);//ok
        Route::put('/addresses/{address}', [UserAddressController::class, 'update']);//ok
        Route::delete('/addresses/{address}', [UserAddressController::class, 'destroy']);//ok
        Route::patch('/addresses/{address}/primary', [UserAddressController::class, 'setPrimary']);//ok

        // ------------------------------------------------------------------
        // MÓDULO ADMIN (Apenas administradores)
        // ------------------------------------------------------------------

        Route::middleware('admin')->prefix('admin')->group(function () {
            // Promoções (CRUD admin)
            Route::get('/promotions', [PromotionController::class, 'adminIndex']);//ok
            Route::get('/promotions/{promotion}', [PromotionController::class, 'adminShow']);//ok
            Route::post('/promotions', [PromotionController::class, 'store']);//ok
            Route::put('/promotions/{promotion}', [PromotionController::class, 'update']);//ok
            Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy']);//ok
            Route::post('/promotions/{promotion}/products', [PromotionController::class, 'attachProducts']);//ok
            Route::delete('/promotions/{promotion}/products', [PromotionController::class, 'detachProducts']);//ok

            // Produtos
            Route::get('/products', [ProductController::class, 'adminIndex']);//ok
            Route::post('/products', [ProductController::class, 'store']);//ok
            Route::put('/products/{product}', [ProductController::class, 'update']);//ok
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);//ok
            Route::get('/products/{product}/stock', [StockMovementController::class, 'index']);//ok
            Route::post('/products/{product}/stock', [StockMovementController::class, 'store']);//ok

            // Pedidos
            Route::get('/orders', [OrderController::class, 'adminIndex']);//ok
            Route::get('/orders/{order}', [OrderController::class, 'adminShow']);//ok
            Route::patch('/orders/{order}/status', [OrderStatusController::class, 'update']);//ok

            // Categorias
            Route::get('/categories', [CategoryController::class, 'adminIndex']);//ok
            Route::post('/categories', [CategoryController::class, 'store']);//ok       
            Route::put('/categories/{category}', [CategoryController::class, 'update']);//ok
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);//ok

            // Usuários
            Route::get('/users', [UserController::class, 'index']);//ok
            Route::post('/users', [UserController::class, 'store']);//ok
            Route::get('/users/{user}', [UserController::class, 'show']);//ok
            Route::put('/users/{user}', [UserController::class, 'update']);//ok
            Route::delete('/users/{user}', [UserController::class, 'destroy']);//ok
        });
    });
});

