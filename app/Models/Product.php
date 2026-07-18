<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\FormatsCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use FormatsCurrency, HasFactory;

    protected $fillable = [
        'merchant_id',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'thumbnail',
        'status',
        'approval_status',
        'is_featured',
        'warranty',
        'tags',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'tags' => 'array',
            'status' => ProductStatus::class,
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function shop(): BelongsTo
    {
        return $this->merchant();
    }

    public function getShopIdAttribute(): ?int
    {
        return $this->merchant_id;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('status', 'active')->oldest();
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Eager-load average rating + count from approved reviews only.
     */
    public function scopeWithApprovedReviewStats($query)
    {
        return $query
            ->withAvg(['reviews' => fn ($q) => $q->approved()], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->approved()]);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function flashSaleProducts(): HasMany
    {
        return $this->hasMany(FlashSaleProduct::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->whereHas('variants', fn ($q) => $q->where('stock', '>', 0)->where('status', 'active'));
    }

    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function getPriceAttribute(): float
    {
        $variant = $this->relationLoaded('defaultVariant')
            ? $this->defaultVariant
            : $this->variants()->where('status', 'active')->orderBy('id')->first();

        return (float) ($variant?->price ?? 0);
    }

    public function getComparePriceAttribute(): ?float
    {
        $variant = $this->relationLoaded('defaultVariant')
            ? $this->defaultVariant
            : $this->variants()->where('status', 'active')->orderBy('id')->first();

        return $variant?->compare_price ? (float) $variant->compare_price : null;
    }

    public function getFormattedPriceAttribute(): string
    {
        return $this->formatCurrency($this->price);
    }

    public function getDiscountPercentAttribute(): ?int
    {
        return $this->calculateDiscountPercent($this->price, $this->compare_price);
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        return $this->discount_percent;
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        if ($this->thumbnail) {
            return $this->resolveImagePath($this->thumbnail);
        }

        $image = $this->relationLoaded('images')
            ? $this->images->first()
            : $this->images()->orderBy('sort_order')->first();

        return $image ? ProductImage::resolvePath($image->image_path) : null;
    }

    private function resolveImagePath(string $path): string
    {
        return ProductImage::resolvePath($path);
    }

    public function getStockAttribute(): int
    {
        if ($this->relationLoaded('defaultVariant') && $this->defaultVariant) {
            return (int) $this->defaultVariant->stock;
        }

        if ($this->relationLoaded('variants')) {
            return (int) $this->variants->where('status', 'active')->sum('stock');
        }

        return (int) $this->variants()->where('status', 'active')->sum('stock');
    }

    public function getSkuAttribute(): ?string
    {
        if ($this->relationLoaded('defaultVariant') && $this->defaultVariant?->sku) {
            return $this->defaultVariant->sku;
        }

        return $this->attributes['sku'] ?? null;
    }

    public function getRatingAttribute(): float
    {
        return (float) ($this->reviews_avg_rating ?? 0);
    }

    public function getTotalReviewsAttribute(): int
    {
        return (int) ($this->reviews_count ?? 0);
    }

    public function getTotalSoldAttribute(): int
    {
        return (int) ($this->order_items_sum_quantity ?? 0);
    }

    public function getIsFreeShippingAttribute(): bool
    {
        return $this->price >= config('shipnest.free_shipping_threshold', 500);
    }
}
