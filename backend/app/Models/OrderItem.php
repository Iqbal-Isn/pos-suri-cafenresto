<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'station',
        'unit_price',
        'variant_price_adjustment',
        'modifiers_price',
        'quantity',
        'discount_type',
        'discount_value',
        'discount_amount',
        'subtotal',
        'notes',
        'is_voided',
        'void_reason',
        'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'variant_price_adjustment' => 'integer',
            'modifiers_price' => 'integer',
            'quantity' => 'integer',
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'subtotal' => 'integer',
            'is_voided' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
