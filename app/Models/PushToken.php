<?php

namespace App\Models;

use App\Traits\UsesHashids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushToken extends Model
{
    use UsesHashids;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
