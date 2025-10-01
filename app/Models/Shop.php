<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'location',
        'latitude',
        'longitude',
        'dxf_file_path',
        'layout_file_path',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Get the shop layouts for the shop.
     */
    public function shopLayouts(): HasMany
    {
        return $this->hasMany(ShopLayout::class);
    }
}
