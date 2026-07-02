@props(['product', 'showActions' => true])

@php
    $rating = $product->reviews_avg_rating ?? 0;
@endphp

<div {{ $attributes->merge(['class' => 'card group overflow-hidden hover:shadow-lg transition-shadow relative']) }}>
    <a href="{{ route('products.show', $product->slug) }}" class="block">
        <div class="relative aspect-square bg-gray-100 overflow-hidden">
            @if($product->primary_image_url)
                <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                     loading="lazy" decoding="async" referrerpolicy="no-referrer"
                     onerror="this.onerror=null;this.src='https://placehold.co/400x400/f3f4f6/9ca3af/png?text=Product';">
            @else
                <div class="w-full h-full flex items-center justify-center text-gray-300">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            @endif

            @if($product->discount_percentage)
                <span class="absolute top-2 left-2 badge bg-primary text-white font-semibold">-{{ $product->discount_percentage }}%</span>
            @endif

            @if($showActions)
                @auth
                    <form action="{{ route('account.wishlist.store', $product) }}" method="POST"
                          class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition"
                          onclick="event.preventDefault(); event.stopPropagation(); this.submit();">
                        @csrf
                        @if($product->relationLoaded('defaultVariant') && $product->defaultVariant)
                            <input type="hidden" name="variant_id" value="{{ $product->defaultVariant->id }}">
                        @endif
                        <button type="submit"
                                class="w-8 h-8 rounded-full bg-white/90 shadow flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-white transition"
                                title="Add to wishlist">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white/90 shadow flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-white transition opacity-0 group-hover:opacity-100"
                       title="Add to wishlist"
                       onclick="event.preventDefault(); event.stopPropagation(); window.location=this.href;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </a>
                @endauth
            @endif
        </div>

        <div class="p-3">
            @if($product->relationLoaded('merchant') && $product->merchant)
                <p class="text-xs text-gray-400 truncate mb-1">{{ $product->merchant->shop_name }}</p>
            @endif

            <h3 class="text-sm text-gray-800 line-clamp-2 min-h-[2.5rem] group-hover:text-primary transition-colors">{{ $product->name }}</h3>

            <div class="flex items-center gap-1.5 mt-1.5">
                <x-rating-stars :rating="$rating" size="sm" />
                @if($rating > 0)
                    <span class="text-xs text-gray-400">({{ number_format($rating, 1) }})</span>
                @endif
            </div>

            <div class="mt-2 flex items-baseline gap-2 flex-wrap">
                <span class="text-lg font-bold text-primary">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</span>
                @if($product->compare_price && $product->compare_price > $product->price)
                    <span class="text-sm text-gray-400 line-through">{{ config('shipnest.currency_symbol') }}{{ number_format($product->compare_price) }}</span>
                @endif
            </div>
        </div>
    </a>

    @if($showActions && $product->stock > 0)
          <form action="{{ route('cart.store') }}" method="POST" class="px-3 pb-3">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            @if($product->relationLoaded('defaultVariant') && $product->defaultVariant)
              <input type="hidden" name="variant_id" value="{{ $product->defaultVariant->id }}">
            @endif
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="w-full btn-primary py-2 text-xs">
                Add to Cart
            </button>
        </form>
    @elseif($showActions)
        <div class="px-3 pb-3">
            <button type="button" disabled class="w-full py-2 text-xs rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">
                Out of Stock
            </button>
        </div>
    @endif
</div>
