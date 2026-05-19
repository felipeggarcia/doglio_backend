<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Vinkla\Hashids\Facades\Hashids;

class UserResource extends JsonResource
{
    protected bool $showSensitive = false;

    /** Usado em register/login onde não há auth:sanctum ativo. */
    public function withSensitive(): static
    {
        $this->showSensitive = true;
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user() && $request->user()->role === 'admin';
        $isOwner = $request->user() && $request->user()->id === $this->id;

        $data = [
            'id'    => $this->hashid,
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => $this->role,
            'city'  => $this->city,
            'state' => $this->state,
        ];

        // CPF/CNPJ e data de nascimento visíveis apenas para o próprio usuário ou admin
        if ($isOwner || $isAdmin || $this->showSensitive) {
            $data['cpf_cnpj']    = $this->formatted_cpf_cnpj;
            $data['birth_date']  = $this->birth_date?->format('Y-m-d');
        }

        // Campos adicionais exclusivos para admin
        if ($isAdmin) {
            $data['is_active']          = $this->is_active;
            $data['last_login']         = $this->last_login?->format('Y-m-d H:i:s');
            $data['email_verified_at']  = $this->email_verified_at?->format('Y-m-d H:i:s');
            $data['created_at']         = $this->created_at->format('Y-m-d H:i:s');
            $data['updated_at']         = $this->updated_at->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
