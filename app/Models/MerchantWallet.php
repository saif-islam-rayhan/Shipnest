<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'balance',
        'pending_balance',
        'total_earned',
        'total_withdrawn',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MerchantWalletTransaction::class, 'merchant_id', 'merchant_id');
    }
}
