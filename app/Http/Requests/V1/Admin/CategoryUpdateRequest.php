<?php

namespace App\Http\Requests\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorização feita no Controller, mas garantindo que o Admin logado esteja fazendo a requisição
        return $this->user() && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        // Regra para garantir que o nome seja único, exceto para o nome atual da categoria sendo editada
        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('categories')->ignore($this->route('category'))],
            'is_highlighted' => ['sometimes', 'boolean'],
        ];
    }
}
