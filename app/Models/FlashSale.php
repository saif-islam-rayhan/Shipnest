<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(FlashSaleProduct::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')->where('starts_at', '>', now());
    }

    public function scopeEnded($query)
    {
        return $query->where('ends_at', '<', now());
    }

    public function isRunning(): bool
    {
        return $this->status === 'active'
            && $this->starts_at <= now()
            && $this->ends_at >= now();
    }
}
