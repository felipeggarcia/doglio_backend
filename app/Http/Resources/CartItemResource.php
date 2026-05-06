<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $availableStock = $product->stock_quantity;
        $requestedQty = $this->quantity;

        // Preço atual de mercado (com promoção vigente, se houver)
        $currentPrice = $product->getEffectivePrice();
        // Preço que estava quando foi adicionado ao carrinho
        $priceAtAdd = (float) $this->unit_price;
        // Detecta se o preço mudou desde que foi adicionado
        $priceChanged = abs($currentPrice - $priceAtAdd) > 0.001;

        $promotion = $this->promotion;

        return [
            'id' => $this->hashid,
            'quantity' => $requestedQty,
            'unit_price' => number_format($priceAtAdd, 2, '.', ''),
            'current_price' => number_format($currentPrice, 2, '.', ''),
            'price_changed' => $priceChanged,
            'subtotal' => number_format($priceAtAdd * $requestedQty, 2, '.', ''),
            'promotion' => $promotion ? [
                'id' => $promotion->hashid,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'discount_value' => (float) $promotion->discount_value,
                'is_still_active' => $promotion->isCurrentlyActive(),
            ] : null,
            'product' => [
                'id' => $product->hashid,
                'name' => $product->name,
                'original_price' => number_format((float) $product->price, 2, '.', ''),
                'effective_price' => number_format($currentPrice, 2, '.', ''),
                'stock_quantity' => $availableStock,
                'in_stock' => $availableStock > 0,
                'primary_image' => $product->primaryImage
                    ? new ProductImageResource($product->primaryImage)
                    : null,
            ],
            'stock_warning' => $requestedQty > $availableStock,
        ];
    }
}

