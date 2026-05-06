<?php

namespace App\Models;

use App\Traits\UsesHashids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavorite extends Model
{
    use UsesHashids;

    protected $fillable = [
        'user_id',
        'product_id',
        'notify_on_restock',
    ];

    protected $casts = [
        'notify_on_restock' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
