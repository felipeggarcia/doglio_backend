<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'reference' => $this->reference_type ? [
                'type' => $this->reference_type,
                'id' => $this->reference?->hashid ?? $this->reference_id,
            ] : null,
            'performed_by' => $this->user_id
                ? ($this->whenLoaded('user', fn() => $this->user->name, 'loaded'))
                : 'system',
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
