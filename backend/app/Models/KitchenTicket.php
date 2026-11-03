<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicket extends Model
{
    protected $fillable = [
        'outlet_id',
        'order_id',
        'ticket_number',
        'station',
        'type',
        'status',
        'items_payload',
        'printed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'items_payload' => 'array',
            'printed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
