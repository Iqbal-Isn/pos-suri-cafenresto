<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptTemplate extends Model
{
    protected $fillable = [
        'outlet_id',
        'header_text',
        'footer_text',
        'logo_url',
        'show_wifi',
        'wifi_ssid',
        'wifi_password',
    ];

    protected function casts(): array
    {
        return [
            'show_wifi' => 'boolean',
        ];
    }
}
