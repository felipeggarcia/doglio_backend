<?php

namespace App\Services;

use App\Models\PushToken;

class PushTokenService
{
    public function register(int $userId, string $token, string $platform): void
    {
        // Delete + insert: garante que o token pertence a exatamente um user
        // e a linha nunca é alterada (imutável após criação)
        PushToken::where('token', $token)->delete();

        PushToken::create([
            'user_id'  => $userId,
            'token'    => $token,
            'platform' => $platform,
        ]);
    }

    public function remove(int $userId, string $token): void
    {
        PushToken::where('token', $token)
            ->where('user_id', $userId)
            ->delete();
    }
}
