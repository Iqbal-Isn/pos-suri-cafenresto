<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'outlet_id',
        'shift_id',
        'table_id',
        'parent_order_id',
        'user_id',
        'client_uuid',
        'order_number',
        'order_type',
        'customer_name',
        'guest_count',
        'status',
        'gross_subtotal',
        'item_discount_total',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'voucher_code',
        'service_charge_amount',
        'tax_amount',
        'rounding_amount',
        'grand_total',
        'paid_amount',
        'change_amount',
        'notes',
        'held_at',
        'paid_at',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'gross_subtotal' => 'integer',
            'item_discount_total' => 'integer',
            'subtotal' => 'integer',
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'service_charge_amount' => 'integer',
            'tax_amount' => 'integer',
            'rounding_amount' => 'integer',
            'grand_total' => 'integer',
            'paid_amount' => 'integer',
            'change_amount' => 'integer',
            'held_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class);
    }

    public function splitChildren(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }
}
