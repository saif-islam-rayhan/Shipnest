<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image',
        'link',
        'type',
        'position',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeHome($query)
    {
        return $query->where('type', 'home');
    }

    public function scopePosition($query, string $position)
    {
        return $query->where('position', $position);
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/'.$this->image);
    }
}
