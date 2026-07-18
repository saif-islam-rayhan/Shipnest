<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductReview extends Model
{
    use HasFactory;

    public const MAX_IMAGES = 5;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_item_id',
        'rating',
        'title',
        'body',
        'images',
        'status',
        'sentiment',
        'agent_summary',
        'agent_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'images' => 'array',
            'agent_analyzed_at' => 'datetime',
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

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * @return list<string>
     */
    public function getImageUrlsAttribute(): array
    {
        return collect($this->images ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '' && Storage::disk('public')->exists($path))
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, UploadedFile>|null  $files
     * @return list<string>
     */
    public static function storeUploadedImages(?array $files): array
    {
        if (! $files) {
            return [];
        }

        $paths = [];

        foreach (array_slice($files, 0, self::MAX_IMAGES) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $paths[] = $file->store('reviews', 'public');
        }

        return $paths;
    }
}
