<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hashid,
            'notify_on_restock' => (bool) $this->notify_on_restock,
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
