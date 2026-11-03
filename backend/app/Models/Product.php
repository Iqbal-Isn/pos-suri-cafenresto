<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'outlet_id',
        'category_id',
        'sku',
        'name',
        'description',
        'base_price',
        'station',
        'is_available',
        'is_favorite',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'is_available' => 'boolean',
            'is_favorite' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_group');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function resolvePriceForOrderType(string $orderType): int
    {
        $specificPrice = $this->prices->firstWhere('order_type', $orderType);

        return $specificPrice ? (int) $specificPrice->price : (int) $this->base_price;
    }
}
