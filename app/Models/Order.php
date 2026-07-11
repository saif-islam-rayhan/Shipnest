<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\FormatsCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Order extends Model
{
    use FormatsCurrency, HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'discount',
        'shipping_charge',
        'tax',
        'total',
        'payment_method',
        'payment_status',
        'payment_transaction_id',
        'shipping_address_id',
        'coupon_id',
        'shipping_method',
        'payment_reference',
        'note',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_charge' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'shipping_address_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shop(): HasOneThrough
    {
        return $this->hasOneThrough(
            Merchant::class,
            OrderItem::class,
            'order_id',
            'id',
            'id',
            'merchant_id',
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }

    public function payment(): HasOne
    {
        return $this->latestPayment();
    }

    public function address(): BelongsTo
    {
        return $this->shippingAddress();
    }

    public function getShippingFeeAttribute(): float
    {
        return (float) $this->shipping_charge;
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->whereHas('items', fn ($q) => $q->where('merchant_id', $merchantId));
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->forMerchant($shopId);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeReturned($query)
    {
        return $query->whereHas('returns');
    }

    public function canBeCancelled(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function canRequestReturn(): bool
    {
        if ($this->status !== OrderStatus::Delivered) {
            return false;
        }

        $deliveredAt = $this->delivered_at
            ?? $this->statusHistories()->where('status', OrderStatus::Delivered->value)->latest()->value('created_at');

        if (! $deliveredAt) {
            return false;
        }

        return now()->diffInDays($deliveredAt) <= 7;
    }

    public function getAmountDueOnDeliveryAttribute(): float
    {
        if ($this->payment_method !== PaymentMethod::Cod) {
            return 0;
        }

        return max(0, (float) $this->total);
    }

    public function getFormattedTotalAttribute(): string
    {
        return $this->formatCurrency((float) $this->total);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return $this->formatCurrency((float) $this->subtotal);
    }
}
