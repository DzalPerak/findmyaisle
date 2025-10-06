<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Waypoint extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'x',
        'y',
        'description',
        'is_start_point',
        'is_end_point'
    ];

    protected $casts = [
        'is_start_point' => 'boolean',
        'is_end_point' => 'boolean',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'waypoint_categories')
                    ->wherePivot('active', true)
                    ->withPivot('active')
                    ->withTimestamps();
    }

    /**
     * Get all categories (including inactive ones) for management purposes
     */
    public function allCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'waypoint_categories')
                    ->withPivot('active')
                    ->withTimestamps();
    }

    /**
     * Set this waypoint as the start point for its shop
     * Unsets any existing start point for the same shop
     */
    public function setAsStartPoint(): bool
    {
        // First, unset any existing start point for this shop
        static::where('shop_id', $this->shop_id)
            ->where('id', '!=', $this->id)
            ->update(['is_start_point' => false]);

        // Set this waypoint as the start point
        return $this->update(['is_start_point' => true]);
    }

    /**
     * Set this waypoint as the end point for its shop
     * Unsets any existing end point for the same shop
     */
    public function setAsEndPoint(): bool
    {
        // First, unset any existing end point for this shop
        static::where('shop_id', $this->shop_id)
            ->where('id', '!=', $this->id)
            ->update(['is_end_point' => false]);

        // Set this waypoint as the end point
        return $this->update(['is_end_point' => true]);
    }

    /**
     * Get the start point waypoint for a shop
     */
    public static function getStartPointForShop(int $shopId): ?self
    {
        return static::where('shop_id', $shopId)
            ->where('is_start_point', true)
            ->first();
    }

    /**
     * Get the end point waypoint for a shop
     */
    public static function getEndPointForShop(int $shopId): ?self
    {
        return static::where('shop_id', $shopId)
            ->where('is_end_point', true)
            ->first();
    }
}
