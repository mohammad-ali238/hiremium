<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A purchasable credit pack. Buying one adds `credits` to the workspace's
 * persistent pack balance.
 */
class TopUpPack extends Model
{
    protected $fillable = ['name', 'credits', 'price', 'lemonsqueezy_variant_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
