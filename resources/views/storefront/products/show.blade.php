<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8"
       x-data="productDetail({{ Js::from($variantsJson) }}, {{ (int) ($variantsJson->first()['id'] ?? 0) }})">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
      <a href="{{ route('home') }}" class="hover:text-primary">Home</a>
      <span class="mx-2">/</span>
      @if($product->category)
        <a href="{{ route('category.show', $product->category->slug) }}" class="hover:text-primary">{{ $product->category->name }}</a>
        <span class="mx-2">/</span>
      @endif
      <span class="text-gray-900">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
      {{-- Gallery --}}
      <div>
        <div class="card overflow-hidden aspect-square relative group"
             @mousemove="zoom($event)" @mouseleave="resetZoom()">
          @if($product->images->isNotEmpty())
            <img :src="images[activeImage]" alt="{{ $product->name }}"
                 class="w-full h-full object-cover transition-transform duration-200 origin-center"
                 :style="zoomStyle">
          @elseif($product->primary_image_url)
            <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}"
                 class="w-full h-full object-cover" loading="lazy" decoding="async" referrerpolicy="no-referrer">
          @else
            <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">No image</div>
          @endif
        </div>
        @if($product->images->count() > 1)
          <div class="flex gap-2 mt-3 overflow-x-auto">
            @foreach($product->images as $index => $image)
              <button type="button" @click="activeImage = {{ $index }}"
                      class="w-16 h-16 flex-shrink-0 rounded border-2 overflow-hidden transition"
                      :class="activeImage === {{ $index }} ? 'border-primary' : 'border-gray-200'">
                <img src="{{ $image->url }}" alt="" class="w-full h-full object-cover"
                     loading="lazy" decoding="async" referrerpolicy="no-referrer">
              </button>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Product info --}}
      <div>
        @if($product->merchant)
          <a href="{{ route('products.index', ['shop' => $product->merchant_id]) }}" class="text-sm text-primary hover:underline font-medium">
            {{ $product->merchant->shop_name }}
          </a>
        @endif

        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mt-2">{{ $product->name }}</h1>

        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
          <x-rating-stars :rating="$product->rating" size="md" />
          @if($product->rating > 0)
            <span class="text-gray-600">{{ number_format($product->rating, 1) }} ({{ $product->total_reviews }} reviews)</span>
          @endif
          <span class="text-gray-400">|</span>
          <span class="text-gray-500">{{ $product->total_sold }} sold</span>
          @if($product->brand)
            <span class="text-gray-400">|</span>
            <a href="{{ route('brand.show', $product->brand->slug) }}" class="text-primary hover:underline">{{ $product->brand->name }}</a>
          @endif
        </div>

        <p class="mt-2 text-sm text-gray-500">SKU: <span x-text="selectedVariant?.sku || '{{ $product->sku }}'"></span></p>

        <div class="mt-4 flex flex-wrap items-baseline gap-3">
          <span class="text-3xl font-bold text-primary">{{ config('shipnest.currency_symbol') }}<span x-text="formatPrice(selectedVariant?.price ?? {{ $product->price }})"></span></span>
          <template x-if="selectedVariant?.compare_price && selectedVariant.compare_price > selectedVariant.price">
            <span class="text-lg text-gray-400 line-through">{{ config('shipnest.currency_symbol') }}<span x-text="formatPrice(selectedVariant.compare_price)"></span></span>
          </template>
          <template x-if="discountPercent > 0">
            <span class="badge bg-primary text-white" x-text="discountPercent + '% off'"></span>
          </template>
        </div>

        {{-- Variant selector --}}
        @if($product->variants->where('status', 'active')->count() > 1)
          <div class="mt-6">
            <p class="text-sm font-medium text-gray-700 mb-2">Select Option</p>
            <div class="flex flex-wrap gap-2">
              <template x-for="variant in variants" :key="variant.id">
                <button type="button" @click="selectVariant(variant.id)"
                        :disabled="variant.stock <= 0"
                        :class="{
                          'border-primary bg-primary-50 text-primary': selectedVariantId === variant.id,
                          'border-gray-200 text-gray-700 hover:border-gray-400': selectedVariantId !== variant.id,
                          'opacity-50 cursor-not-allowed': variant.stock <= 0
                        }"
                        class="px-4 py-2 border rounded-md text-sm font-medium transition">
                  <span x-text="variant.name"></span>
                </button>
              </template>
            </div>
          </div>
        @endif

        {{-- Stock --}}
        <div class="mt-4">
          <template x-if="selectedVariant && selectedVariant.stock > 10">
            <span class="inline-flex items-center gap-1.5 text-green-600 text-sm font-medium">
              <span class="w-2 h-2 rounded-full bg-green-500"></span> In Stock
            </span>
          </template>
          <template x-if="selectedVariant && selectedVariant.stock > 0 && selectedVariant.stock <= 10">
            <span class="inline-flex items-center gap-1.5 text-orange-600 text-sm font-medium">
              <span class="w-2 h-2 rounded-full bg-orange-500"></span> Only <span x-text="selectedVariant.stock"></span> left
            </span>
          </template>
          <template x-if="!selectedVariant || selectedVariant.stock <= 0">
            <span class="inline-flex items-center gap-1.5 text-red-600 text-sm font-medium">
              <span class="w-2 h-2 rounded-full bg-red-500"></span> Out of Stock
            </span>
          </template>
        </div>

        {{-- Add to cart --}}
        <form action="{{ route('cart.store') }}" method="POST" class="mt-6 space-y-4" x-show="selectedVariant && selectedVariant.stock > 0">
          @csrf
          <input type="hidden" name="product_id" value="{{ $product->id }}">
          <input type="hidden" name="variant_id" :value="selectedVariantId">

          <div class="flex flex-wrap gap-3">
            <div class="flex items-center border rounded-md">
              <button type="button" @click="qty = Math.max(1, qty - 1)" class="px-4 py-3 text-gray-600 hover:bg-gray-50">−</button>
              <input type="number" name="quantity" x-model.number="qty" min="1" class="w-14 text-center border-0 focus:ring-0">
              <button type="button" @click="qty = Math.min(selectedVariant?.stock || 1, qty + 1)" class="px-4 py-3 text-gray-600 hover:bg-gray-50">+</button>
            </div>
            <button type="submit" class="btn-primary flex-1 min-w-[140px] py-3 text-base">Add to Cart</button>
            <button type="submit" name="buy_now" value="1" class="flex-1 min-w-[140px] py-3 text-base border-2 border-primary text-primary rounded-md font-medium hover:bg-primary-50 transition">
              Buy Now
            </button>
          </div>
        </form>

        <div class="mt-4 flex gap-3">
          @auth
            <button type="button" class="text-sm text-gray-600 hover:text-primary flex items-center gap-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
              Wishlist
            </button>
          @else
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-primary">♡ Wishlist</a>
          @endauth
          <button type="button" @click="shareProduct()" class="text-sm text-gray-600 hover:text-primary flex items-center gap-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            Share
          </button>
        </div>

        {{-- Delivery --}}
        <div class="mt-6 card p-4 space-y-2 text-sm">
          @if($product->is_free_shipping)
            <p class="text-green-600 font-medium">✓ Free shipping on this item</p>
          @else
            <p class="text-gray-600">Free shipping on orders over {{ config('shipnest.currency_symbol') }}{{ number_format(config('shipnest.free_shipping_threshold', 500)) }}</p>
          @endif
          <p class="text-gray-600">🚚 Estimated delivery: <strong>3–5 business days</strong></p>
          @if($product->warranty)
            <p class="text-gray-600">🛡️ Warranty: {{ $product->warranty }}</p>
          @endif
        </div>

        {{-- Highlights --}}
        @if($product->tags || $product->short_description)
          <div class="mt-6">
            <h3 class="font-semibold text-gray-900 mb-2">Highlights</h3>
            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
              @if(is_array($product->tags))
                @foreach($product->tags as $tag)
                  <li>{{ $tag }}</li>
                @endforeach
              @endif
              @if($product->short_description && ! is_array($product->tags))
                <li>{{ $product->short_description }}</li>
              @endif
            </ul>
          </div>
        @endif
      </div>
    </div>

    {{-- Tabs --}}
    <div class="mt-12" x-data="{ tab: 'description' }">
      <div class="border-b flex gap-6 overflow-x-auto">
        @foreach(['description' => 'Description', 'specifications' => 'Specifications', 'reviews' => 'Reviews', 'qa' => 'Q&A'] as $key => $label)
          <button type="button" @click="tab = '{{ $key }}'"
                  :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                  class="pb-3 border-b-2 font-medium text-sm whitespace-nowrap transition">
            {{ $label }}
            @if($key === 'reviews')
              ({{ $product->total_reviews }})
            @endif
          </button>
        @endforeach
      </div>

      <div class="py-8">
        <div x-show="tab === 'description'" class="prose prose-sm max-w-none text-gray-600">
          {!! nl2br(e($product->description ?? 'No description available.')) !!}
        </div>

        <div x-show="tab === 'specifications'" x-cloak>
          @if($product->attributes->isNotEmpty())
            <table class="w-full text-sm">
              <tbody class="divide-y">
                @foreach($product->attributes as $attr)
                  <tr>
                    <td class="py-3 pr-4 font-medium text-gray-700 w-1/3">{{ $attr->attribute_name }}</td>
                    <td class="py-3 text-gray-600">{{ $attr->attribute_value }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <p class="text-gray-500 text-sm">No specifications listed.</p>
          @endif
        </div>

        <div x-show="tab === 'reviews'" x-cloak>
          @php $totalReviews = max(1, $product->total_reviews); @endphp
          <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div class="text-center md:text-left">
              <p class="text-4xl font-bold text-gray-900">{{ number_format($product->rating, 1) }}</p>
              <x-rating-stars :rating="$product->rating" size="md" class="justify-center md:justify-start mt-2" />
              <p class="text-sm text-gray-500 mt-1">{{ $product->total_reviews }} reviews</p>
            </div>
            <div class="md:col-span-2 space-y-2">
              @foreach($reviewDistribution as $stars => $count)
                <div class="flex items-center gap-3 text-sm">
                  <span class="w-8 text-gray-600">{{ $stars }}★</span>
                  <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-yellow-400 rounded-full" style="width: {{ $product->total_reviews ? ($count / $product->total_reviews * 100) : 0 }}%"></div>
                  </div>
                  <span class="w-8 text-gray-400 text-right">{{ $count }}</span>
                </div>
              @endforeach
            </div>
          </div>

          @if($canReview)
            <form action="{{ route('products.reviews.store', $product) }}" method="POST" class="card p-6 mb-8">
              @csrf
              <h3 class="font-semibold text-gray-900 mb-4">Write a Review</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                  <select name="rating" required class="input-field">
                    @for($i = 5; $i >= 1; $i--)
                      <option value="{{ $i }}">{{ $i }} stars</option>
                    @endfor
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                  <input type="text" name="title" required class="input-field" placeholder="Summary of your experience">
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Review</label>
                  <textarea name="body" rows="4" required class="input-field" placeholder="Share details about the product"></textarea>
                </div>
              </div>
              <button type="submit" class="btn-primary mt-4">Submit Review</button>
            </form>
          @endif

          <div class="space-y-6">
            @forelse($product->reviews as $review)
              <div class="border-b pb-6">
                <div class="flex items-center gap-3 mb-2">
                  <x-rating-stars :rating="$review->rating" size="sm" />
                  <span class="font-medium text-gray-900">{{ $review->title }}</span>
                  @if($review->order_item_id)
                    <span class="badge bg-green-100 text-green-700 text-xs">Verified Purchase</span>
                  @endif
                </div>
                <p class="text-sm text-gray-600">{{ $review->body }}</p>
                <p class="text-xs text-gray-400 mt-2">{{ $review->user->name }} · {{ $review->created_at->format('M d, Y') }}</p>
              </div>
            @empty
              <p class="text-gray-500 text-sm">No reviews yet. Be the first to review!</p>
            @endforelse
          </div>
        </div>

        <div x-show="tab === 'qa'" x-cloak class="text-center py-12 text-gray-500">
          <p class="text-lg font-medium text-gray-700">Questions & Answers</p>
          <p class="text-sm mt-2">No questions yet. Ask the seller about this product.</p>
        </div>
      </div>
    </div>

    @if($relatedProducts->isNotEmpty())
      <section class="mt-12">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Related Products</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          @foreach($relatedProducts as $related)
            <x-product-card :product="$related" />
          @endforeach
        </div>
      </section>
    @endif

    @if($merchantProducts->isNotEmpty())
      <section class="mt-12">
        <h2 class="text-xl font-bold text-gray-900 mb-6">More from {{ $product->merchant->shop_name }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          @foreach($merchantProducts as $merchantProduct)
            <x-product-card :product="$merchantProduct" />
          @endforeach
        </div>
      </section>
    @endif
  </div>

  @push('scripts')
  <script>
    function productDetail(variants, defaultId) {
      const images = @json($product->images->isNotEmpty()
        ? $product->images->map(fn ($img) => $img->url)->values()
        : ($product->primary_image_url ? [$product->primary_image_url] : []));

      return {
        variants,
        selectedVariantId: defaultId || (variants[0]?.id ?? null),
        qty: 1,
        activeImage: 0,
        images,
        zoomStyle: 'transform: scale(1)',
        get selectedVariant() {
          return this.variants.find(v => v.id === this.selectedVariantId) || this.variants[0];
        },
        get discountPercent() {
          const v = this.selectedVariant;
          if (!v?.compare_price || v.compare_price <= v.price) return 0;
          return Math.round((v.compare_price - v.price) / v.compare_price * 100);
        },
        selectVariant(id) {
          this.selectedVariantId = id;
          this.qty = 1;
        },
        formatPrice(price) {
          return Number(price).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },
        zoom(e) {
          const rect = e.currentTarget.getBoundingClientRect();
          const x = ((e.clientX - rect.left) / rect.width) * 100;
          const y = ((e.clientY - rect.top) / rect.height) * 100;
          this.zoomStyle = `transform: scale(1.5); transform-origin: ${x}% ${y}%`;
        },
        resetZoom() {
          this.zoomStyle = 'transform: scale(1)';
        },
        shareProduct() {
          const url = window.location.href;
          if (navigator.share) {
            navigator.share({ title: @json($product->name), url });
          } else {
            navigator.clipboard.writeText(url);
            alert('Link copied to clipboard!');
          }
        }
      };
    }
  </script>
  @endpush
</x-layouts.app>
