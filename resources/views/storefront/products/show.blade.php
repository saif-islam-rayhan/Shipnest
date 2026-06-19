<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-500 mb-6">
      <a href="{{ route('home') }}" class="hover:text-primary">Home</a>
      <span class="mx-2">/</span>
      <a href="{{ route('products.index', ['category' => $product->category_id]) }}" class="hover:text-primary">{{ $product->category->name }}</a>
      <span class="mx-2">/</span>
      <span class="text-gray-900">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <div x-data="{ activeImage: 0 }">
        <div class="card overflow-hidden aspect-square">
          @if($product->images->isNotEmpty())
            <img :src="'{{ asset('storage') }}/' + {{ Js::from($product->images->pluck('path')) }}[activeImage]"
                 alt="{{ $product->name }}" class="w-full h-full object-cover">
          @else
            <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">No image</div>
          @endif
        </div>
        @if($product->images->count() > 1)
          <div class="flex gap-2 mt-3">
            @foreach($product->images as $index => $image)
              <button @click="activeImage = {{ $index }}"
                      class="w-16 h-16 rounded border-2 overflow-hidden"
                      :class="activeImage === {{ $index }} ? 'border-primary' : 'border-gray-200'">
                <img src="{{ asset('storage/'.$image->path) }}" alt="" class="w-full h-full object-cover">
              </button>
            @endforeach
          </div>
        @endif
      </div>

      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
        <div class="mt-2 flex items-center gap-3">
          @if($product->rating > 0)
            <span class="text-yellow-400">★ {{ number_format($product->rating, 1) }}</span>
            <span class="text-sm text-gray-500">({{ $product->total_reviews }} reviews)</span>
          @endif
          <span class="text-sm text-gray-500">{{ $product->total_sold }} sold</span>
        </div>

        <div class="mt-4 flex items-baseline gap-3">
          <span class="text-3xl font-bold text-primary">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</span>
          @if($product->compare_price && $product->compare_price > $product->price)
            <span class="text-lg text-gray-400 line-through">{{ config('shipnest.currency_symbol') }}{{ number_format($product->compare_price) }}</span>
            <span class="badge bg-primary text-white">-{{ $product->discount_percentage }}%</span>
          @endif
        </div>

        <p class="mt-4 text-gray-600">{{ $product->short_description }}</p>

        <div class="mt-4 space-y-2 text-sm">
          <p><span class="text-gray-500">Sold by:</span> <a href="{{ route('products.index', ['shop' => $product->shop_id]) }}" class="text-primary hover:underline">{{ $product->shop->name }}</a></p>
          <p><span class="text-gray-500">SKU:</span> {{ $product->sku }}</p>
          <p><span class="text-gray-500">Stock:</span>
            @if($product->stock > 0)
              <span class="text-green-600 font-medium">{{ $product->stock }} available</span>
            @else
              <span class="text-red-600 font-medium">Out of stock</span>
            @endif
          </p>
        </div>

        @if($product->stock > 0)
          <form action="{{ route('cart.store') }}" method="POST" class="mt-6 flex gap-3" x-data="{ qty: 1 }">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <div class="flex items-center border rounded-md">
              <button type="button" @click="qty = Math.max(1, qty - 1)" class="px-3 py-2 text-gray-600 hover:bg-gray-50">−</button>
              <input type="number" name="quantity" x-model="qty" min="1" max="{{ min($product->stock, 99) }}" class="w-12 text-center border-0 focus:ring-0">
              <button type="button" @click="qty = Math.min({{ min($product->stock, 99) }}, qty + 1)" class="px-3 py-2 text-gray-600 hover:bg-gray-50">+</button>
            </div>
            <button type="submit" class="btn-primary flex-1 py-3">Add to Cart</button>
          </form>
        @endif

        @if($product->description)
          <div class="mt-8 card p-6">
            <h2 class="font-semibold text-gray-900 mb-3">Product Description</h2>
            <div class="prose prose-sm text-gray-600">{!! nl2br(e($product->description)) !!}</div>
          </div>
        @endif
      </div>
    </div>

    @if($relatedProducts->isNotEmpty())
      <section class="mt-12">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Related Products</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          @foreach($relatedProducts as $related)
            <x-product.card :product="$related" />
          @endforeach
        </div>
      </section>
    @endif
  </div>
</x-layouts.app>
