<?php

namespace App\Http\Controllers\V1;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Services\PushTokenService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Support\ApiMessages;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer', // Default role
            'city' => $request->city,
            'state' => $request->state,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => ApiMessages::AUTH_REGISTERED,
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'push_token' => 'nullable|string',
            'platform'   => 'nullable|in:android,ios',
        ]);
        
        $user = User::where('email', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::AUTH_INVALID_CREDENTIALS,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'details' => ApiMessages::AUTH_INVALID_CREDENTIALS
                ]
            ], 401);
        }

        // Verifica se o usuário está ativo
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::AUTH_ACCOUNT_INACTIVE,
                'error' => [
                    'code' => 'ACCOUNT_INACTIVE',
                    'details' => ApiMessages::AUTH_ACCOUNT_INACTIVE
                ]
            ], 403);
        }

        // Atualiza o last_login
        $user->update(['last_login' => now()]);

        // Registra o push token do dispositivo (se informado)
        if ($request->filled('push_token') && $request->filled('platform')) {
            app(PushTokenService::class)->register($user->id, $request->push_token, $request->platform);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => ApiMessages::AUTH_LOGIN,
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        // Remove o push token do dispositivo que está fazendo logout (opcional)
        if ($request->filled('push_token')) {
            app(PushTokenService::class)->remove($request->user()->id, $request->push_token);
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => ApiMessages::AUTH_LOGOUT
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return new UserResource($request->user());
    }
}
