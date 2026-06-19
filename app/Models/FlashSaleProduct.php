<?php

namespace App\Models;

use App\Models\Concerns\FormatsCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleProduct extends Model
{
    use FormatsCurrency, HasFactory;

    protected $fillable = [
        'flash_sale_id',
        'product_id',
        'variant_id',
        'discount_type',
        'discount_value',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function getFormattedDiscountAttribute(): string
    {
        if ($this->discount_type === 'percentage') {
            return $this->discount_value.'%';
        }

        return $this->formatCurrency((float) $this->discount_value);
    }
}
