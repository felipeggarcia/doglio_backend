<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hashid,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'discount_value' => (float) $this->discount_value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'is_currently_active' => $this->resolveIsCurrentlyActive(),
            'min_quantity' => $this->min_quantity,
            // Campos de pivô — presentes quando acessado via produto (product->promotions)
            'use_limit'  => $this->when($this->pivot !== null, fn() => $this->pivot->use_limit),
            'uses_count' => $this->when($this->pivot !== null, fn() => $this->pivot->uses_count),
            // Produtos vinculados — presentes quando carregados explicitamente
            'products' => $this->when($this->relationLoaded('products'), function () {
                $promotionId = $this->resource->id;

                return $this->products->map(function ($product) use ($promotionId) {
                    $originalPrice  = (float) $product->price;
                    $effectivePrice = $this->type === 'percentage'
                        ? round($originalPrice * (1 - $this->discount_value / 100), 2)
                        : max(0.0, $originalPrice - (float) $this->discount_value);

                    // is_currently_active por produto: verifica se ESTA promoção é a ativa para o produto
                    $activePromoId = $product->relationLoaded('promotions')
                        ? $product->getActivePromotion()?->id
                        : null;

                    return [
                        'id'                 => $product->hashid,
                        'name'               => $product->name,
                        'original_price'     => number_format($originalPrice, 2, '.', ''),
                        'effective_price'    => number_format($effectivePrice, 2, '.', ''),
                        'discount_amount'    => number_format($originalPrice - $effectivePrice, 2, '.', ''),
                        'is_currently_active' => $activePromoId === $promotionId,
                        'use_limit'          => $product->pivot->use_limit,
                        'uses_count'         => $product->pivot->uses_count,
                        'primary_image'      => $product->primaryImage?->path ?? null,
                    ];
                });
            }),
        ];
    }

    /**
     * is_currently_active da promoção:
     * - Se os produtos estão carregados: true só se ao menos 1 produto tem esta como promoção ativa.
     * - Caso contrário: usa isCurrentlyActive() da promoção (datas + is_active).
     */
    private function resolveIsCurrentlyActive(): bool
    {
        if (!$this->isCurrentlyActive()) {
            return false;
        }

        if (!$this->relationLoaded('products')) {
            return true;
        }

        $promotionId = $this->resource->id;

        return $this->products->contains(function ($product) use ($promotionId) {
            if (!$product->relationLoaded('promotions')) {
                return false;
            }
            return $product->getActivePromotion()?->id === $promotionId;
        });
    }
}
