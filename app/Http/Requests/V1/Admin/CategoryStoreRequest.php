<?php

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorização feita no Controller, mas garantindo que o Admin logado esteja fazendo a requisição
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'is_highlighted' => ['sometimes', 'boolean'],
        ];
    }
}
