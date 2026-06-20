<?php

namespace App\Models;

use App\Models\Concerns\FormatsCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use FormatsCurrency, HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->price * $this->quantity;
    }

    public function getUnitPriceAttribute(): float
    {
        return (float) $this->price;
    }

    public function getTotalPriceAttribute(): float
    {
        return $this->line_total;
    }

    public function getFormattedPriceAttribute(): string
    {
        return $this->formatCurrency((float) $this->price);
    }

    public function getFormattedLineTotalAttribute(): string
    {
        return $this->formatCurrency($this->line_total);
    }
}
