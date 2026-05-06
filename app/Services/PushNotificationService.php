<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Envia uma notificação push para um ou mais tokens FCM.
     *
     * @param  string|array  $tokens  Token(s) FCM do dispositivo
     * @param  string  $title
     * @param  string  $body
     * @param  array  $data  Payload extra (ex: ['product_id' => 'jR', 'type' => 'restock'])
     */
    public function send(string|array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = (array) $tokens;

        $serverKey = config('services.fcm.server_key');

        // Sem chave configurada: apenas loga (ambiente de desenvolvimento)
        if (empty($serverKey)) {
            Log::info('[PushNotification] FCM server key not configured. Notification skipped.', [
                'tokens' => $tokens,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
            return;
        }

        foreach (array_chunk($tokens, 500) as $chunk) {
            $payload = [
                'registration_ids' => $chunk,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            if (!$response->successful()) {
                Log::error('[PushNotification] FCM request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }
    }
}
