<x-layouts.app>
  @php
    $activeCategoryIds = isset($category) ? app(\App\Services\ProductService::class)->resolveCategoryIds($category) ?? [] : [];
    $viewMode = request('view', 'grid');
    $queryExceptFilters = request()->except(['page', 'min_price', 'max_price', 'brands', 'rating', 'discount', 'sort', 'view']);
  @endphp

  <div class="max-w-7xl mx-auto px-4 py-8" x-data="{
    sidebarOpen: false,
    minPrice: {{ (int) request('min_price', 0) }},
    maxPrice: {{ (int) request('max_price', $priceMax) }},
    priceMax: {{ (int) $priceMax }},
    viewMode: '{{ $viewMode }}'
  }">
    <div class="flex flex-col lg:flex-row gap-8">
      {{-- Mobile filter toggle --}}
      <div class="lg:hidden">
        <button @click="sidebarOpen = !sidebarOpen" type="button" class="btn-primary w-full flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          Filters
        </button>
      </div>

      {{-- Sidebar --}}
      <aside class="lg:w-72 flex-shrink-0"
             :class="sidebarOpen ? 'block' : 'hidden lg:block'">
        <form method="GET" action="{{ $listingRoute }}" class="space-y-4">
          @foreach($queryExceptFilters as $key => $value)
            @if(is_array($value))
              @foreach($value as $v)
                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
              @endforeach
            @else
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
          @endforeach

          <div class="card p-4">
            <h3 class="font-semibold text-gray-900 mb-3">Categories</h3>
            @include('storefront.products.partials.category-tree', [
              'categories' => $categories,
              'activeCategory' => $category ?? null,
              'activeCategoryIds' => $activeCategoryIds,
            ])
          </div>

          <div class="card p-4">
            <h3 class="font-semibold text-gray-900 mb-3">Price Range</h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between text-sm text-gray-600">
                <span>{{ config('shipnest.currency_symbol') }}<span x-text="minPrice.toLocaleString()"></span></span>
                <span>{{ config('shipnest.currency_symbol') }}<span x-text="maxPrice.toLocaleString()"></span></span>
              </div>
              <input type="range" min="0" :max="priceMax" x-model.number="minPrice" class="w-full accent-primary">
              <input type="range" min="0" :max="priceMax" x-model.number="maxPrice" class="w-full accent-primary">
              <input type="hidden" name="min_price" :value="minPrice">
              <input type="hidden" name="max_price" :value="maxPrice">
            </div>
          </div>

          <div class="card p-4">
            <h3 class="font-semibold text-gray-900 mb-3">Brands</h3>
            <div class="space-y-2 max-h-48 overflow-y-auto text-sm">
              @foreach($brands as $brandItem)
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" name="brands[]" value="{{ $brandItem->id }}"
                         @checked(in_array($brandItem->id, (array) request('brands', [])) || (isset($brand) && $brand->id === $brandItem->id && ! request('brands')))
                         class="rounded text-primary focus:ring-primary">
                  <span class="flex-1 truncate">{{ $brandItem->name }}</span>
                  <span class="text-gray-400 text-xs">({{ $brandItem->products_count }})</span>
                </label>
              @endforeach
            </div>
          </div>

          <div class="card p-4">
            <h3 class="font-semibold text-gray-900 mb-3">Rating</h3>
            <div class="flex flex-wrap gap-2">
              @foreach([4, 3, 2, 1] as $stars)
                <label class="cursor-pointer">
                  <input type="radio" name="rating" value="{{ $stars }}" class="sr-only peer" @checked(request('rating') == $stars)>
                  <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border text-sm peer-checked:border-primary peer-checked:bg-primary-50 peer-checked:text-primary">
                    {{ $stars }}+ <span class="text-yellow-400">★</span>
                  </span>
                </label>
              @endforeach
            </div>
          </div>

          <div class="card p-4">
            <h3 class="font-semibold text-gray-900 mb-3">Discount</h3>
            <div class="flex flex-wrap gap-2">
              @foreach([10, 20, 30, 50] as $pct)
                <label class="cursor-pointer">
                  <input type="radio" name="discount" value="{{ $pct }}" class="sr-only peer" @checked(request('discount') == $pct)>
                  <span class="inline-block px-3 py-1.5 rounded-full border text-sm peer-checked:border-primary peer-checked:bg-primary-50 peer-checked:text-primary">{{ $pct }}%+</span>
                </label>
              @endforeach
            </div>
          </div>

          <button type="submit" class="btn-primary w-full">Apply Filters</button>
          <a href="{{ $listingRoute }}" class="block text-center text-sm text-gray-500 hover:text-primary">Clear all filters</a>
        </form>
      </aside>

      {{-- Main content --}}
      <div class="flex-1 min-w-0">
        {{-- Top bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $pageTitle }}</h1>
            <p class="text-sm text-gray-500 mt-1">
              {{ $products->total() }} {{ Str::plural('result', $products->total()) }}
              @if(request('q'))
                for "<span class="font-medium text-gray-700">{{ request('q') }}</span>"
              @endif
            </p>
          </div>
          <div class="flex items-center gap-3">
            <form method="GET" action="{{ $listingRoute }}" class="flex items-center gap-2">
              @foreach(request()->except('sort', 'page', 'view') as $key => $value)
                @if(is_array($value))
                  @foreach($value as $v)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                  @endforeach
                @else
                  <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
              @endforeach
              <input type="hidden" name="view" :value="viewMode">
              <select name="sort" onchange="this.form.submit()" class="input-field text-sm py-2">
                @if(request('q'))
                  <option value="relevance" @selected(request('sort', 'relevance') === 'relevance')>Relevance</option>
                @endif
                <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                <option value="popular" @selected(request('sort') === 'popular')>Best Selling</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                <option value="rating" @selected(request('sort') === 'rating')>Top Rated</option>
              </select>
            </form>
            <div class="flex border rounded-md overflow-hidden">
              <button type="button" @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-primary text-white' : 'bg-white text-gray-600'" class="p-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
              </button>
              <button type="button" @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-primary text-white' : 'bg-white text-gray-600'" class="p-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
              </button>
            </div>
          </div>
        </div>

        {{-- Active filter chips --}}
        @php
          $hasFilters = request()->anyFilled(['q', 'min_price', 'max_price', 'brands', 'rating', 'discount']) || isset($category) || isset($brand);
        @endphp
        @if($hasFilters)
          <div class="flex flex-wrap gap-2 mb-6">
            @if(isset($category))
              <a href="{{ route('products.index', request()->except('page')) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-primary-50 text-primary text-sm">
                {{ $category->name }} ×
              </a>
            @endif
            @if(isset($brand) && ! request('brands'))
              <a href="{{ route('products.index', request()->except('page')) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-primary-50 text-primary text-sm">
                {{ $brand->name }} ×
              </a>
            @endif
            @if(request('q'))
              <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-sm">
                Search: {{ request('q') }} ×
              </a>
            @endif
            @if(request('rating'))
              <a href="{{ request()->fullUrlWithQuery(['rating' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-sm">
                {{ request('rating') }}+ stars ×
              </a>
            @endif
            @if(request('discount'))
              <a href="{{ request()->fullUrlWithQuery(['discount' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-sm">
                {{ request('discount') }}%+ off ×
              </a>
            @endif
            @if(request('min_price') || request('max_price'))
              <a href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => null, 'page' => null]) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-sm">
                Price: {{ config('shipnest.currency_symbol') }}{{ number_format(request('min_price', 0)) }}–{{ number_format(request('max_price', $priceMax)) }} ×
              </a>
            @endif
            @foreach((array) request('brands', []) as $brandId)
              @php $chipBrand = $brands->firstWhere('id', (int) $brandId); @endphp
              @if($chipBrand)
                <a href="{{ request()->fullUrlWithQuery(['brands' => array_values(array_diff((array) request('brands', []), [$brandId])), 'page' => null]) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-sm">
                  {{ $chipBrand->name }} ×
                </a>
              @endif
            @endforeach
          </div>
        @endif

        @if($products->isEmpty())
          <div class="card p-12 text-center">
            <p class="text-gray-500 mb-4">No products found. Try adjusting your filters.</p>
            <a href="{{ route('products.index') }}" class="btn-primary inline-block">Browse All Products</a>
          </div>
        @else
          <div x-show="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($products as $product)
              <x-product-card :product="$product" />
            @endforeach
          </div>
          <div x-show="viewMode === 'list'" x-cloak class="space-y-4">
            @foreach($products as $product)
              @include('storefront.products.partials.list-item', ['product' => $product])
            @endforeach
          </div>
          <div class="mt-8">
            {{ $products->links() }}
          </div>
        @endif
      </div>
    </div>
  </div>
</x-layouts.app>
