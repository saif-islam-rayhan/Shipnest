@props(['product'])

<a href="{{ route('products.show', $product->slug) }}" class="card group flex flex-col sm:flex-row gap-4 p-4 hover:shadow-md transition-shadow">
  <div class="relative w-full sm:w-48 h-48 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden">
    @if($product->primary_image_url)
      <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}"
           class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
           loading="lazy" decoding="async" referrerpolicy="no-referrer">
    @endif
    @if($product->discount_percentage)
      <span class="absolute top-2 left-2 badge bg-primary text-white">-{{ $product->discount_percentage }}%</span>
    @endif
  </div>
  <div class="flex-1 min-w-0 flex flex-col justify-between">
    <div>
      @if($product->relationLoaded('merchant') && $product->merchant)
        <p class="text-xs text-gray-400 mb-1">{{ $product->merchant->shop_name }}</p>
      @endif
      <h3 class="text-lg font-semibold text-gray-900 group-hover:text-primary transition-colors">{{ $product->name }}</h3>
      @if($product->short_description)
        <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $product->short_description }}</p>
      @endif
      <div class="mt-2 flex items-center gap-2">
        <x-rating-stars :rating="$product->reviews_avg_rating ?? 0" size="sm" />
        @if(($product->reviews_avg_rating ?? 0) > 0)
          <span class="text-xs text-gray-400">({{ number_format($product->reviews_avg_rating, 1) }})</span>
        @endif
      </div>
    </div>
    <div class="mt-4 flex items-center justify-between gap-4">
      <div class="flex items-baseline gap-2">
        <span class="text-xl font-bold text-primary">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</span>
        @if($product->compare_price && $product->compare_price > $product->price)
          <span class="text-sm text-gray-400 line-through">{{ config('shipnest.currency_symbol') }}{{ number_format($product->compare_price) }}</span>
        @endif
      </div>
      @if($product->stock > 0)
        <form action="{{ route('cart.store') }}" method="POST" onclick="event.stopPropagation()">
          @csrf
          <input type="hidden" name="product_id" value="{{ $product->id }}">
          <input type="hidden" name="quantity" value="1">
          <button type="submit" class="btn-primary text-sm py-2 px-4">Add to Cart</button>
        </form>
      @endif
    </div>
  </div>
</a>
