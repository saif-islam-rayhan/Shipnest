<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'shop_slug',
        'logo',
        'banner',
        'description',
        'phone',
        'address',
        'district',
        'commission_rate',
        'balance',
        'total_sales',
        'rating',
        'is_verified',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'balance' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'rating' => 'decimal:2',
            'is_verified' => 'boolean',
        ];
    }

    public function getSlugAttribute(): ?string
    {
        return $this->shop_slug;
    }

    public function setSlugAttribute(string $value): void
    {
        $this->shop_slug = $value;
    }

    public function getNameAttribute(): string
    {
        return $this->shop_name;
    }

    public function getFullNameAttribute(): string
    {
        return $this->shop_name;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            OrderItem::class,
            'merchant_id',
            'id',
            'id',
            'order_id',
        )->distinct();
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MerchantWallet::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(MerchantWalletTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_verified', true)->orderByDesc('rating');
    }
}
