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
        $id = null;
        
        // Se hashids está desabilitado, usa o ID diretamente
        if (!config('app.use_hashids', true)) {
            $id = $value;
        } else {
            // Decodifica o Hashid para obter o ID real
            $decoded = Hashids::decode($value);

            // Se a decodificação falhar ou retornar vazio, lança exceção
            if (empty($decoded)) {
                throw (new ModelNotFoundException())->setModel(get_class($this), [$value]);
            }

            // Pega o primeiro valor decodificado (ID real)
            $id = $decoded[0];
        }

        // Busca o model pelo ID real
        // Não usamos withoutGlobalScopes() para respeitar soft deletes
        $model = $this->where($this->getRouteKeyName(), $id)->first();
        
        // Se não encontrou ou está soft deleted, lança ModelNotFoundException
        if (!$model) {
            throw (new ModelNotFoundException())->setModel(get_class($this), [$value]);
        }
        
        return $model;
    }

    /**
     * Get the encoded ID attribute.
     *
     * @return string|int
     */
    public function getHashidAttribute()
    {
        // Se hashids está desabilitado, retorna o ID normal
        if (!config('app.use_hashids', true)) {
            return $this->id;
        }

        return Hashids::encode($this->id);
    }
}
