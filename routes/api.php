<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\Admin\CategoryController as AdminCategoryController;

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

    // Produtos
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);

    // Categorias públicas
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);

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
        // MÓDULO ADMIN
        // ------------------------------------------------------------------
        
        // Produtos (Admin)
        Route::middleware('admin')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        });

        // Categorias Admin (CRUD completo)
        Route::resource('admin/categories', AdminCategoryController::class)->except(['index', 'show']);
    });
});

