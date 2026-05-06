<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hashid,
            'status' => $this->status,
            'amount' => number_format($this->amount, 2, '.', ''),
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'pix_code' => $this->pix_code,
            'pix_expires_at' => $this->pix_expires_at?->toIso8601String(),
            'boleto_code' => $this->boleto_code,
            'boleto_expires_at' => $this->boleto_expires_at?->toIso8601String(),
            'card_last_four' => $this->card_last_four,
            'card_brand' => $this->card_brand,
            'installments' => $this->installments,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
