<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'question',
        'answer',
        'answered_by',
        'answered_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function scopeVisible($query)
    {
        return $query->whereIn('status', ['pending', 'answered']);
    }

    public function scopeAnswered($query)
    {
        return $query->where('status', 'answered')->whereNotNull('answer');
    }

    public function isAnswered(): bool
    {
        return filled($this->answer) && $this->status === 'answered';
    }
}
