<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color'
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function waypoints(): BelongsToMany
    {
        return $this->belongsToMany(Waypoint::class, 'waypoint_categories')
                    ->wherePivot('active', true)
                    ->withPivot('active')
                    ->withTimestamps();
    }
}
