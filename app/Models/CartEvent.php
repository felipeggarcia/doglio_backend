<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'action',
        'quantity',
        'price_at_moment',
        'snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_at_moment' => 'decimal:2',
        'snapshot' => 'array',
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
