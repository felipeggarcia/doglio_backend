<?php

namespace App\Models;

use App\Traits\UsesHashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Product extends Model
{
    use HasFactory, SoftDeletes, UsesHashids;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock_quantity',
        'image_url',
        'is_highlighted',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_highlighted' => 'boolean',
        'stock_quantity' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('created_at');
    }

    /**
     * Retorna a promoção ativa mais vantajosa para este produto.
     * Requer que a relação 'promotions' já esteja carregada (eager loaded).
     */
    public function getActivePromotion(): ?Promotion
    {
        $now = Carbon::now();

        return $this->promotions
            ->filter(fn(Promotion $p) =>
                $p->is_active
                && $p->starts_at <= $now
                && ($p->ends_at === null || $p->ends_at > $now)
                && ($p->max_uses === null || $p->uses_count < $p->max_uses)
            )
            ->sortByDesc('discount_value')
            ->first();
    }

    /**
     * Calcula o preço efetivo (com desconto se houver promoção ativa).
     * Requer que a relação 'promotions' já esteja carregada.
     */
    public function getEffectivePrice(): float
    {
        $promo = $this->getActivePromotion();

        if (!$promo) {
            return (float) $this->price;
        }

        if ($promo->type === 'percentage') {
            return round((float) $this->price * (1 - $promo->discount_value / 100), 2);
        }

        return max(0.0, (float) $this->price - (float) $promo->discount_value);
    }
}
