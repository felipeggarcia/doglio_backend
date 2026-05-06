<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\UserFavorite;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyRestockJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $productId) {}

    public function handle(PushNotificationService $push): void
    {
        $product = Product::find($this->productId);

        if (!$product) {
            return;
        }

        $favorites = UserFavorite::where('product_id', $this->productId)
            ->where('notify_on_restock', true)
            ->with('user.pushTokens')
            ->get();

        $tokens = $favorites
            ->flatMap(fn($fav) => $fav->user->pushTokens->pluck('token'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return;
        }

        $push->send(
            tokens: $tokens,
            title: 'De volta ao estoque! 🎉',
            body: "{$product->name} está disponível novamente.",
            data: [
                'type' => 'restock',
                'product_id' => $product->hashid,
                'product_name' => $product->name,
            ]
        );
    }
}
