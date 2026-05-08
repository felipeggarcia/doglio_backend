<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;
use App\Support\ApiMessages;

class PushTokenController extends Controller
{
    /**
     * Registra ou atualiza o token de push do dispositivo.
     * POST /api/v1/push-tokens
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:android,ios',
        ]);

        // Upsert: se o token já existe em outro user, reatribui; se não, cria
        PushToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform']]
        );

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PUSH_TOKEN_REGISTERED,
        ], 201);
    }

    /**
     * Remove o token de push do dispositivo.
     * DELETE /api/v1/push-tokens
     */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
        ]);

        $request->user()
            ->pushTokens()
            ->where('token', $data['token'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PUSH_TOKEN_REMOVED,
        ]);
    }
}
