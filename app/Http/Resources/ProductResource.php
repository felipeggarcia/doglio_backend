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
        return [
            'id' => Hashids::encode($this->id),
            'name' => $this->name,
            'description' => $this->description,
            'price' => number_format($this->price, 2, '.', ''),
            'stock_quantity' => (int) $this->stock_quantity,
            'is_highlighted' => (bool) $this->is_highlighted,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}
