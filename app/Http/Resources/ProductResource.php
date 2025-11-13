<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => number_format($this->price, 2, '.', ''),
            'stock_quantity' => (int) $this->stock_quantity,
            'image_url' => $this->image_url ? url('storage/' . $this->image_url) : null,
            'is_highlighted' => (bool) $this->is_highlighted,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}
