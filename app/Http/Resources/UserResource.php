<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Vinkla\Hashids\Facades\Hashids;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->hashid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'city' => $this->city,
            'state' => $this->state,
        ];

        // Campos adicionais para admin
        if ($request->user() && $request->user()->role === 'admin') {
            $data['is_active'] = $this->is_active;
            $data['last_login'] = $this->last_login?->format('Y-m-d H:i:s');
            $data['email_verified_at'] = $this->email_verified_at?->format('Y-m-d H:i:s');
            $data['created_at'] = $this->created_at->format('Y-m-d H:i:s');
            $data['updated_at'] = $this->updated_at->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
