<?php

namespace App\Models;

use App\Traits\UsesHashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasFactory, SoftDeletes, UsesHashids;

    protected $fillable = [
        'name',
        'description',
        'type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
        'min_quantity',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'min_quantity' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('use_limit', 'uses_count');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Verifica se a promoção está vigente neste momento.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at <= $now) {
            return false;
        }

        return true;
    }
}
