@props(['product'])

<a href="{{ route('products.show', $product->slug) }}" class="card group overflow-hidden hover:shadow-md transition-shadow">
  <div class="relative aspect-square bg-gray-100 overflow-hidden">
    @if($product->primary_image_url)
      <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}"
           class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
    @else
      <div class="w-full h-full flex items-center justify-center text-gray-400">
        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
    @endif
    @if($product->discount_percentage)
      <span class="absolute top-2 left-2 badge bg-primary text-white">-{{ $product->discount_percentage }}%</span>
    @endif
    @if($product->is_free_shipping)
      <span class="absolute top-2 right-2 badge bg-green-600 text-white">Free Shipping</span>
    @endif
  </div>
  <div class="p-3">
    <h3 class="text-sm text-gray-800 line-clamp-2 min-h-[2.5rem] group-hover:text-primary transition-colors">{{ $product->name }}</h3>
    <div class="mt-2 flex items-baseline gap-2">
      <span class="text-lg font-bold text-primary">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</span>
      @if($product->compare_price && $product->compare_price > $product->price)
        <span class="text-sm text-gray-400 line-through">{{ config('shipnest.currency_symbol') }}{{ number_format($product->compare_price) }}</span>
      @endif
    </div>
    @if($product->relationLoaded('merchant') && $product->merchant)
      <p class="mt-1 text-xs text-gray-400 truncate">{{ $product->merchant->shop_name }}</p>
    @endif
  </div>
</a>
