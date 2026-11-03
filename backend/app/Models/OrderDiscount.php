<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDiscount extends Model
{
    protected $fillable = [
        'order_id',
        'name',
        'type',
        'value',
        'amount',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'amount' => 'integer',
        ];
    }
}
