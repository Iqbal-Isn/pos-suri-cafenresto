<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'outlet_id',
        'name',
        'station',
        'connection_type',
        'address',
        'paper_width',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'paper_width' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
