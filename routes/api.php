<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\CartController;
use App\Http\Controllers\V1\OrderController;
use App\Http\Controllers\V1\UserAddressController;
use App\Http\Controllers\V1\PromotionController;
use App\Http\Resources\UserResource;

// ==========================================
// API V1
// ==========================================

Route::prefix('v1')->group(function () {
    
    // ==========================================
    // ROTAS PÚBLICAS (Sem autenticação)
    // ==========================================

    // Autenticação
    Route::post('/register', [AuthController::class, 'register']);//ok
    Route::post('/login', [AuthController::class, 'login']);//ok

    // Produtos (Leitura pública)
    Route::get('/products', [ProductController::class, 'index']);//ok
    Route::get('/products/{product}', [ProductController::class, 'show']);//ok

    // Categorias (Leitura pública)
    Route::get('/categories', [CategoryController::class, 'index']);//ok
    Route::get('/categories/{category}', [CategoryController::class, 'show']);//ok

    // Promoções (Leitura pública — somente ativas)
    Route::get('/promotions', [PromotionController::class, 'index']);//ok
    Route::get('/promotions/{promotion}', [PromotionController::class, 'show']);//ok

    // ==========================================
    // ROTAS PROTEGIDAS (Requer autenticação)
    // ==========================================

    Route::middleware('auth:sanctum')->group(function () {
        
        // Autenticação
        Route::post('/logout', [AuthController::class, 'logout']);//ok
        Route::get('/user', [AuthController::class, 'user']);//ok

        // Carrinho
        Route::post('/cart/sync', [CartController::class, 'sync']);//ok
        Route::get('/cart', [CartController::class, 'show']);//ok
        Route::get('/cart/validate', [CartController::class, 'validate']);//ok
        Route::delete('/cart', [CartController::class, 'clear']);

        // Checkout e Pedidos
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);

        // Endereços do usuário
        Route::get('/addresses', [UserAddressController::class, 'index']);
        Route::post('/addresses', [UserAddressController::class, 'store']);
        Route::put('/addresses/{address}', [UserAddressController::class, 'update']);
        Route::delete('/addresses/{address}', [UserAddressController::class, 'destroy']);
        Route::patch('/addresses/{address}/primary', [UserAddressController::class, 'setPrimary']);

        // ------------------------------------------------------------------
        // MÓDULO ADMIN (Apenas administradores)
        // ------------------------------------------------------------------
        
        Route::middleware('admin')->group(function () {
            // Promoções (CRUD admin)
            Route::post('/promotions', [PromotionController::class, 'store']);
            Route::put('/promotions/{promotion}', [PromotionController::class, 'update']);
            Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy']);
            Route::post('/promotions/{promotion}/products', [PromotionController::class, 'attachProducts']);
            Route::delete('/promotions/{promotion}/products', [PromotionController::class, 'detachProducts']);

            // Produtos
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);

            // Categorias
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

            // Usuários (Gerenciamento)
            Route::get('/users', [UserController::class, 'index']); 
            Route::post('/users', [UserController::class, 'store']); 
            Route::get('/users/{user}', [UserController::class, 'show']);
            Route::put('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        });
    });
});

