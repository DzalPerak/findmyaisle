<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'description',
        'barcode',
        'price',
        'brand'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
