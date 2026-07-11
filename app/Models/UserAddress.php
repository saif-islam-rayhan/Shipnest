<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'address_line1',
        'city',
        'district',
        'thana',
        'postal_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function getFullNameAttribute(): string
    {
        return $this->recipient_name;
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address_line1,
            $this->thana,
            $this->city,
            $this->district,
            $this->postal_code,
        ]));
    }

    public function toShippingArray(): array
    {
        return [
            'name' => $this->recipient_name,
            'phone' => $this->phone,
            'address_line_1' => $this->address_line1,
            'city' => $this->city,
            'district' => $this->district,
            'thana' => $this->thana,
            'postal_code' => $this->postal_code,
        ];
    }
}
