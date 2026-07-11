<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInterestScore extends Model
{
    protected $fillable = [
        'subject_key',
        'user_id',
        'interest_type',
        'interest_id',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'interest_id' => 'integer',
            'score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
