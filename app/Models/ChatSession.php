<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $fillable = [
        'session_key', 'user_id', 'step', 'question', 'category',
        'budget_min', 'budget_max', 'month_from', 'month_to',
        'year_from', 'year_to', 'top_n',         'pending_cart_product_id',
        'draft_product',
        'last_product_id',
    ];

    protected function casts(): array
    {
        return [
            'draft_product' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
