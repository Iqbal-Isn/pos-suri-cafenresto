<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    protected $fillable = [
        'outlet_id',
        'name',
        'rate',
        'is_inclusive',
        'service_charge_rate',
        'rounding_multiple',
        'discount_approval_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_inclusive' => 'boolean',
            'service_charge_rate' => 'float',
            'rounding_multiple' => 'integer',
            'discount_approval_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
