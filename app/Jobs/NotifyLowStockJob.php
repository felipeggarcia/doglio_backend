<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\UserFavorite;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyLowStockJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $productId,
        public readonly int $stockQuantity
    ) {}

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
            title: 'Estoque baixo ⚠️',
            body: "Restam apenas {$this->stockQuantity} unidades de {$product->name}. Corra!",
            data: [
                'type' => 'low_stock',
                'product_id' => $product->hashid,
                'product_name' => $product->name,
                'stock_quantity' => $this->stockQuantity,
            ]
        );
    }
}
