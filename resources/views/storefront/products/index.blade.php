<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
      <aside class="lg:w-64 flex-shrink-0">
        <div class="card p-4">
          <h3 class="font-semibold text-gray-900 mb-3">Categories</h3>
          <ul class="space-y-1 text-sm">
            <li>
              <a href="{{ route('products.index') }}" class="block py-1.5 px-2 rounded {{ !request('category') ? 'bg-primary-50 text-primary font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                All Categories
              </a>
            </li>
            @foreach($categories as $category)
              <li>
                <a href="{{ route('products.index', ['category' => $category->id]) }}"
                   class="block py-1.5 px-2 rounded {{ request('category') == $category->id ? 'bg-primary-50 text-primary font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                  {{ $category->name }}
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      </aside>

      <div class="flex-1">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">
              @if(request('q'))
                Results for "{{ request('q') }}"
              @else
                All Products
              @endif
            </h1>
            <p class="text-sm text-gray-500 mt-1">{{ $products->total() }} products found</p>
          </div>
          <form method="GET" class="flex gap-2">
            @foreach(request()->except('sort', 'page') as $key => $value)
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <select name="sort" onchange="this.form.submit()" class="input-field text-sm">
              <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
              <option value="popular" @selected(request('sort') === 'popular')>Best Selling</option>
              <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
              <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
              <option value="rating" @selected(request('sort') === 'rating')>Top Rated</option>
            </select>
          </form>
        </div>

        @if($products->isEmpty())
          <div class="card p-12 text-center">
            <p class="text-gray-500">No products found. Try adjusting your filters.</p>
          </div>
        @else
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($products as $product)
              <x-product.card :product="$product" />
            @endforeach
          </div>
          <div class="mt-8">
            {{ $products->withQueryString()->links() }}
          </div>
        @endif
      </div>
    </div>
  </div>
</x-layouts.app>
