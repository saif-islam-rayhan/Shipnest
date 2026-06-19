<x-layouts.app>
  <section class="bg-gradient-to-r from-secondary to-secondary-800 text-white">
    <div class="max-w-7xl mx-auto px-4 py-16 md:py-24">
      <div class="max-w-2xl">
        <h1 class="text-4xl md:text-5xl font-bold leading-tight">
          Shop Smart, <span class="text-primary">Ship Fast</span>
        </h1>
        <p class="mt-4 text-lg text-gray-200">Discover millions of products from trusted sellers across Bangladesh. Best prices, fast delivery, secure payments.</p>
        <div class="mt-8 flex gap-4">
          <a href="{{ route('products.index') }}" class="btn-primary px-8 py-3 text-base">Shop Now</a>
          <a href="{{ route('register') }}" class="btn-outline border-white text-white hover:bg-white/10 px-8 py-3 text-base">Start Selling</a>
        </div>
      </div>
    </div>
  </section>

  @if($categories->isNotEmpty())
  <section class="max-w-7xl mx-auto px-4 py-10">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Shop by Category</h2>
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4">
      @foreach($categories as $category)
        <a href="{{ route('products.index', ['category' => $category->id]) }}" class="text-center group">
          <div class="w-16 h-16 mx-auto rounded-full bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition">
            <span class="text-2xl">{{ $category->icon ?? '📦' }}</span>
          </div>
          <p class="mt-2 text-xs text-gray-700 group-hover:text-primary line-clamp-2">{{ $category->name }}</p>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  @if($featuredProducts->isNotEmpty())
  <section class="max-w-7xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-gray-900">Featured Products</h2>
      <a href="{{ route('products.index') }}" class="text-primary text-sm font-medium hover:underline">View All →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
      @foreach($featuredProducts as $product)
        <x-product.card :product="$product" />
      @endforeach
    </div>
  </section>
  @endif

  @if($featuredMerchants->isNotEmpty())
  <section class="max-w-7xl mx-auto px-4 py-10">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Top Sellers</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
      @foreach($featuredMerchants as $merchant)
        <a href="{{ route('products.index', ['shop' => $merchant->id]) }}" class="card p-4 text-center hover:shadow-md transition">
          @if($merchant->logo)
            <img src="{{ asset('storage/'.$merchant->logo) }}" alt="{{ $merchant->shop_name }}" class="w-16 h-16 mx-auto rounded-full object-cover">
          @else
            <div class="w-16 h-16 mx-auto rounded-full bg-primary-100 flex items-center justify-center text-primary font-bold text-xl">
              {{ strtoupper(substr($merchant->shop_name, 0, 1)) }}
            </div>
          @endif
          <h3 class="mt-3 font-medium text-gray-900">{{ $merchant->shop_name }}</h3>
          <p class="text-xs text-gray-500">{{ $merchant->products()->count() }} products</p>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  <section class="bg-primary-50 mt-8">
    <div class="max-w-7xl mx-auto px-4 py-12 grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
      <div>
        <div class="text-3xl mb-2">🚚</div>
        <h3 class="font-semibold text-gray-900">Nationwide Delivery</h3>
        <p class="text-sm text-gray-600 mt-1">Fast delivery across all 64 districts</p>
      </div>
      <div>
        <div class="text-3xl mb-2">🔒</div>
        <h3 class="font-semibold text-gray-900">Secure Payments</h3>
        <p class="text-sm text-gray-600 mt-1">bKash, Nagad, SSLCommerz & COD</p>
      </div>
      <div>
        <div class="text-3xl mb-2">↩️</div>
        <h3 class="font-semibold text-gray-900">Easy Returns</h3>
        <p class="text-sm text-gray-600 mt-1">7-day return policy on eligible items</p>
      </div>
    </div>
  </section>
</x-layouts.app>
