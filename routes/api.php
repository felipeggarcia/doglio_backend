<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CategoryController;
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

    // ==========================================
    // ROTAS PROTEGIDAS (Requer autenticação)
    // ==========================================

    Route::middleware('auth:sanctum')->group(function () {
        
        // Autenticação
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return new UserResource($request->user());
        });

        // ------------------------------------------------------------------
        // MÓDULO ADMIN (Apenas administradores)
        // ------------------------------------------------------------------
        
        Route::middleware('admin')->group(function () {
            // Produtos
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);

            // Categorias
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        });
    });
});

