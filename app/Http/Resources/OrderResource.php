<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hashid,
            'status' => $this->status,
            'total_amount' => number_format($this->total_amount, 2, '.', ''),
            'delivery_type' => $this->delivery_type,
            'shipping_address' => $this->delivery_type === 'delivery' ? [
                'street' => $this->shipping_street,
                'number' => $this->shipping_number,
                'complement' => $this->shipping_complement,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'zip' => $this->shipping_zip,
            ] : null,
            'customer' => $this->when(
                $request->user()?->role === 'admin',
                fn() => $this->whenLoaded('user', fn() => [
                    'id'    => $this->user->hashid,
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ])
            ),
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
