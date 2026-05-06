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
            'is_currently_active' => $this->isCurrentlyActive(),
            'min_quantity' => $this->min_quantity,
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
        ];
    }
}
