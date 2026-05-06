<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hashid,
            'quantity' => (int) $this->quantity,
            'unit_price' => number_format($this->unit_price, 2, '.', ''),
            'subtotal' => number_format($this->unit_price * $this->quantity, 2, '.', ''),
            'product' => [
                'id' => $this->product?->hashid,
                'name' => $this->product?->name,
                'primary_image' => $this->product?->primaryImage
                    ? new ProductImageResource($this->product->primaryImage)
                    : null,
            ],
        ];
    }
}
