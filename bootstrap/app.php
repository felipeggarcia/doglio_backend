<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        
        // Força Accept: application/json em todas as rotas API
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // IMPORTANTE: No Laravel 11+, ModelNotFoundException é convertida automaticamente em NotFoundHttpException
        // Precisamos interceptar ANTES dessa conversão acontecer
        
        // Autenticação não autorizada (401)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'details' => 'You must be authenticated to access this resource'
                    ]
                ], 401);
            }
        });

        // Acesso negado (403)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'details' => 'You do not have permission to access this resource'
                    ]
                ], 403);
            }
        });

        // Rota não encontrada (404) - Endpoint não existe OU Model não existe
        // No Laravel 11+, ModelNotFoundException é automaticamente convertida em NotFoundHttpException
        // Então verificamos se a exceção anterior era ModelNotFoundException
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                // Verifica se a exceção anterior era ModelNotFoundException
                if ($e->getPrevious() instanceof ModelNotFoundException) {
                    $modelException = $e->getPrevious();
                    $model = strtolower(class_basename($modelException->getModel()));
                    
                    return response()->json([
                        'success' => false,
                        'message' => ucfirst($model) . ' not found',
                        'error' => [
                            'code' => 'RESOURCE_NOT_FOUND',
                            'details' => "The requested {$model} does not exist or has been deleted"
                        ]
                    ], 404);
                }
                
                // Endpoint realmente não existe
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint not found',
                    'error' => [
                        'code' => 'ENDPOINT_NOT_FOUND',
                        'details' => 'The requested API endpoint does not exist'
                    ]
                ], 404);
            }
        });

        // Validação falhou (422) - já é tratado pelo Laravel mas vamos padronizar
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'details' => $e->errors()
                    ]
                ], 422);
            }
        });

        // Método não permitido (405)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Method not allowed',
                    'error' => [
                        'code' => 'METHOD_NOT_ALLOWED',
                        'details' => 'The HTTP method used is not supported for this endpoint'
                    ]
                ], 405);
            }
        });

        // Erro genérico não mapeado (500)
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                // Só captura se não foi tratado anteriormente
                if (!($e instanceof AuthenticationException) && 
                    !($e instanceof ModelNotFoundException) && 
                    !($e instanceof NotFoundHttpException) &&
                    !($e instanceof \Illuminate\Validation\ValidationException) &&
                    !($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException)) {
                    
                    // Em produção, oculta detalhes do erro
                    if (config('app.debug')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Internal server error',
                            'error' => [
                                'code' => 'INTERNAL_ERROR',
                                'details' => [
                                    'exception' => get_class($e),
                                    'message' => $e->getMessage(),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
                                ]
                            ]
                        ], 500);
                    }
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Internal server error',
                        'error' => [
                            'code' => 'INTERNAL_ERROR',
                            'details' => 'An unexpected error occurred. Please try again later'
                        ]
                    ], 500);
                }
            }
        });
    })->create();
