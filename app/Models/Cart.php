<?php

namespace App\Models;

use App\Models\Concerns\FormatsCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use FormatsCurrency, HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'coupon_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->items->sum(fn (CartItem $item) => $item->price * $item->quantity);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return $this->formatCurrency($this->subtotal);
    }

    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
