<?php

namespace App\Models;

use App\Traits\UsesHashids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, UsesHashids;

    protected $fillable = [
        'order_id',
        'payment_method_id',
        'status',
        'amount',
        'pix_code',
        'pix_expires_at',
        'boleto_code',
        'boleto_expires_at',
        'card_last_four',
        'card_brand',
        'installments',
        'external_reference',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'pix_expires_at' => 'datetime',
        'boleto_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'installments' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
