<?php

namespace App\Traits;

use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait UsesHashids
{
    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Decodifica o Hashid para obter o ID real
        $decoded = Hashids::decode($value);

        // Se a decodificação falhar ou retornar vazio, lança exceção
        if (empty($decoded)) {
            throw new ModelNotFoundException();
        }

        // Pega o primeiro valor decodificado (ID real)
        $id = $decoded[0];

        // Busca o model pelo ID real
        return $this->where($this->getRouteKeyName(), $id)->first();
    }

    /**
     * Get the encoded ID attribute.
     *
     * @return string
     */
    public function getHashidAttribute()
    {
        return Hashids::encode($this->id);
    }
}
