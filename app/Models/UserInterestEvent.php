<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInterestEvent extends Model
{
    protected $fillable = [
        'subject_key',
        'user_id',
        'event_type',
        'product_id',
        'category_id',
        'brand_id',
        'search_query',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
