<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Vinkla\Hashids\Facades\Hashids;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $promo = $this->relationLoaded('promotions') ? $this->getActivePromotion() : null;
        $originalPrice  = (float) $this->price;
        $effectivePrice = $promo ? $this->getEffectivePrice() : null;

        return [
            'id' => $this->hashid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => number_format($originalPrice, 2, '.', ''),
            'original_price'  => $promo ? number_format($originalPrice, 2, '.', '') : null,
            'effective_price' => $promo ? number_format($effectivePrice, 2, '.', '') : null,
            'discount_amount' => $promo ? number_format($originalPrice - $effectivePrice, 2, '.', '') : null,
            'promotion' => $promo ? [
                'id'             => $promo->hashid,
                'name'           => $promo->name,
                'type'           => $promo->type,
                'discount_value' => (float) $promo->discount_value,
            ] : null,
            'stock_quantity' => $this->when(
                (\Illuminate\Support\Facades\Auth::guard('sanctum')->user()?->role ?? $request->user()?->role) === 'admin',
                (int) $this->stock_quantity
            ),
            'in_stock' => $this->stock_quantity > 0,
            'is_highlighted' => (bool) $this->is_highlighted,
            'is_active' => (bool) $this->is_active,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'average_rating' => $this->whenLoaded('reviews', fn() => $this->reviews->avg('rating') ? round($this->reviews->avg('rating'), 1) : null),
            'reviews_count' => $this->whenLoaded('reviews', fn() => $this->reviews->count()),
        ];
    }
}
