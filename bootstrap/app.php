<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Support\ApiMessages;

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
                    'message' => ApiMessages::HTTP_UNAUTHENTICATED,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'details' => ApiMessages::HTTP_UNAUTHENTICATED_DETAILS
                    ]
                ], 401);
            }
        });

        // Acesso negado (403)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => ApiMessages::HTTP_FORBIDDEN,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'details' => ApiMessages::HTTP_FORBIDDEN_DETAILS
                    ]
                ], 403);
            }
        });

        // Rota não encontrada (404) - Endpoint não existe OU Model não existe
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                // Verifica se a exceção anterior era ModelNotFoundException
                if ($e->getPrevious() instanceof ModelNotFoundException) {
                    $modelException = $e->getPrevious();
                    $model = strtolower(class_basename($modelException->getModel()));
                    
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(ApiMessages::HTTP_RESOURCE_NOT_FOUND, ucfirst($model)),
                        'error' => [
                            'code' => 'RESOURCE_NOT_FOUND',
                            'details' => sprintf(ApiMessages::HTTP_RESOURCE_NOT_FOUND_DETAILS, $model)
                        ]
                    ], 404);
                }
                
                // Endpoint realmente não existe
                return response()->json([
                    'success' => false,
                    'message' => ApiMessages::HTTP_ENDPOINT_NOT_FOUND,
                    'error' => [
                        'code' => 'ENDPOINT_NOT_FOUND',
                        'details' => ApiMessages::HTTP_ENDPOINT_NOT_FOUND_DETAILS
                    ]
                ], 404);
            }
        });

        // Validação falhou (422) - já é tratado pelo Laravel mas vamos padronizar
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => ApiMessages::HTTP_VALIDATION_ERROR,
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
                    'message' => ApiMessages::HTTP_METHOD_NOT_ALLOWED,
                    'error' => [
                        'code' => 'METHOD_NOT_ALLOWED',
                        'details' => ApiMessages::HTTP_METHOD_NOT_ALLOWED_DETAILS
                    ]
                ], 405);
            }
        });

        // Rate limit atingido (429)
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => ApiMessages::HTTP_TOO_MANY_REQUESTS,
                    'error' => [
                        'code' => 'TOO_MANY_REQUESTS',
                        'details' => sprintf(ApiMessages::HTTP_TOO_MANY_REQUESTS_DETAILS, $e->getHeaders()['Retry-After'])
                    ]
                ], 429);
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
                            'message' => ApiMessages::HTTP_SERVER_ERROR,
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
                        'message' => ApiMessages::HTTP_SERVER_ERROR,
                        'error' => [
                            'code' => 'INTERNAL_ERROR',
                            'details' => ApiMessages::HTTP_SERVER_ERROR_DETAILS
                        ]
                    ], 500);
                }
            }
        });
    })->create();
