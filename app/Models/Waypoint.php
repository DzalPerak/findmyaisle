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
        'description'
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'waypoint_categories');
    }
}
