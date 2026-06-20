<?php

namespace App\Models;

use App\Models\Concerns\FormatsCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use FormatsCurrency, HasFactory;

    protected $fillable = [
        'order_id',
        'merchant_id',
        'product_id',
        'variant_id',
        'product_name',
        'variant_name',
        'sku',
        'quantity',
        'unit_price',
        'discount',
        'total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return $this->formatCurrency((float) $this->unit_price);
    }

    public function getFormattedTotalAttribute(): string
    {
        return $this->formatCurrency((float) $this->total);
    }

    public function getTotalPriceAttribute(): float
    {
        return (float) $this->total;
    }

    public function getProductImageAttribute(): ?string
    {
        if ($this->relationLoaded('product') && $this->product) {
            return $this->product->thumbnail ?: $this->product->images->first()?->image_path;
        }

        return null;
    }

    public function getDiscountPercentAttribute(): ?int
    {
        $comparePrice = (float) $this->unit_price + (float) $this->discount;

        return $this->calculateDiscountPercent((float) $this->unit_price, $comparePrice > 0 ? $comparePrice : null);
    }
}
