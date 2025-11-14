<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CategoryController;

// ==========================================
// API V1
// ==========================================

Route::prefix('v1')->group(function () {
    
    // ==========================================
    // ROTAS PÚBLICAS (Sem autenticação)
    // ==========================================

    // Autenticação
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Produtos (Leitura pública)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);

    // Categorias (Leitura pública)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    // ==========================================
    // ROTAS PROTEGIDAS (Requer autenticação)
    // ==========================================

    Route::middleware('auth:sanctum')->group(function () {
        
        // Autenticação
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // ------------------------------------------------------------------
        // MÓDULO ADMIN (Apenas administradores)
        // ------------------------------------------------------------------
        
        Route::middleware('admin')->group(function () {
            // Produtos
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);

            // Categorias
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        });
    });
});

