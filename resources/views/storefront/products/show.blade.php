<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-3"
       x-data="productDetail({{ Js::from($variantsJson) }}, {{ (int) ($variantsJson->first()['id'] ?? 0) }})">

    {{-- Breadcrumb --}}
    <nav class="text-xs sm:text-sm text-gray-500 mb-2 truncate">
      <a href="{{ route('home') }}" class="hover:text-primary">Home</a>
      <span class="mx-1.5">/</span>
      @if($product->category)
        <a href="{{ route('category.show', $product->category->slug) }}" class="hover:text-primary">{{ $product->category->name }}</a>
        <span class="mx-1.5">/</span>
      @endif
      <span class="text-gray-900">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 lg:gap-8">
      {{-- Gallery (fixed size so it stays small even if CSS cache lags) --}}
      <div class="w-full max-w-sm lg:max-w-[340px]">
        <div class="card overflow-hidden relative group bg-gray-50"
             style="height: 340px; max-height: 340px;">
          @if($product->images->isNotEmpty())
            <img :src="images[activeImage]" alt="{{ $product->name }}"
                 class="w-full h-full object-contain p-1">
          @elseif($product->primary_image_url)
            <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}"
                 class="w-full h-full object-contain p-1" loading="lazy" decoding="async" referrerpolicy="no-referrer">
          @else
            <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">No image</div>
          @endif
        </div>
        @if($product->images->count() > 1)
          <div class="flex gap-2 mt-3 overflow-x-auto">
            @foreach($product->images as $index => $image)
              <button type="button" @click="activeImage = {{ $index }}"
                      class="w-14 h-14 flex-shrink-0 rounded border-2 overflow-hidden transition"
                      :class="activeImage === {{ $index }} ? 'border-primary' : 'border-gray-200'">
                <img src="{{ $image->url }}" alt="" class="w-full h-full object-cover"
                     loading="lazy" decoding="async" referrerpolicy="no-referrer">
              </button>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Product info (tighter so tabs stay above the fold) --}}
      <div class="min-w-0">
        @if($product->merchant)
          <a href="{{ route('products.index', ['shop' => $product->merchant_id]) }}" class="text-sm text-primary hover:underline font-medium">
            {{ $product->merchant->shop_name }}
          </a>
        @endif

        <h1 class="text-xl lg:text-2xl font-bold text-gray-900 mt-0.5 leading-snug">{{ $product->name }}</h1>

        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
          <x-rating-stars :rating="$product->rating" size="sm" />
          @if($product->rating > 0)
            <span class="text-gray-600">{{ number_format($product->rating, 1) }} ({{ $product->total_reviews }})</span>
          @endif
          <span class="text-gray-400">|</span>
          <span class="text-gray-500">{{ $product->total_sold }} sold</span>
          @if($product->brand)
            <span class="text-gray-400">|</span>
            <a href="{{ route('brand.show', $product->brand->slug) }}" class="text-primary hover:underline">{{ $product->brand->name }}</a>
          @endif
        </div>

        <p class="mt-1 text-xs text-gray-500">SKU: <span x-text="selectedVariant?.sku || '{{ $product->sku }}'"></span></p>

        <div class="mt-2 flex flex-wrap items-baseline gap-2">
          <span class="text-2xl font-bold text-primary">{{ config('shipnest.currency_symbol') }}<span x-text="formatPrice(selectedVariant?.price ?? {{ $product->price }})"></span></span>
          <template x-if="selectedVariant?.compare_price && selectedVariant.compare_price > selectedVariant.price">
            <span class="text-base text-gray-400 line-through">{{ config('shipnest.currency_symbol') }}<span x-text="formatPrice(selectedVariant.compare_price)"></span></span>
          </template>
          <template x-if="discountPercent > 0">
            <span class="badge bg-primary text-white text-xs" x-text="discountPercent + '% off'"></span>
          </template>
        </div>

        @if($product->variants->where('status', 'active')->count() > 1)
          <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="variant in variants" :key="variant.id">
              <button type="button" @click="selectVariant(variant.id)"
                      :disabled="variant.stock <= 0"
                      :class="{
                        'border-primary bg-primary-50 text-primary': selectedVariantId === variant.id,
                        'border-gray-200 text-gray-700 hover:border-gray-400': selectedVariantId !== variant.id,
                        'opacity-50 cursor-not-allowed': variant.stock <= 0
                      }"
                      class="px-3 py-1 border rounded-md text-sm font-medium transition">
                <span x-text="variant.name"></span>
              </button>
            </template>
          </div>
        @endif

        <div class="mt-2">
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

        <form action="{{ route('cart.store') }}" method="POST" class="mt-2.5" x-show="selectedVariant && selectedVariant.stock > 0">
          @csrf
          <input type="hidden" name="product_id" value="{{ $product->id }}">
          <input type="hidden" name="variant_id" :value="selectedVariantId">

          <div class="flex flex-wrap gap-2">
            <div class="flex items-center border rounded-md h-10">
              <button type="button" @click="qty = Math.max(1, qty - 1)" class="px-3 h-full text-gray-600 hover:bg-gray-50">−</button>
              <input type="number" name="quantity" x-model.number="qty" min="1" class="w-12 text-center border-0 focus:ring-0 text-sm">
              <button type="button" @click="qty = Math.min(selectedVariant?.stock || 1, qty + 1)" class="px-3 h-full text-gray-600 hover:bg-gray-50">+</button>
            </div>
            <button type="submit" class="btn-primary flex-1 min-w-[120px] h-10 text-sm">Add to Cart</button>
            <button type="submit" name="buy_now" value="1" class="flex-1 min-w-[120px] h-10 text-sm border-2 border-primary text-primary rounded-md font-medium hover:bg-primary-50 transition">
              Buy Now
            </button>
          </div>
        </form>

        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-600">
          @auth
            <button type="button" class="hover:text-primary flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
              Wishlist
            </button>
          @else
            <a href="{{ route('login') }}" class="hover:text-primary">♡ Wishlist</a>
          @endauth
          <button type="button" @click="shareProduct()" class="hover:text-primary flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.368-2.684z"/></svg>
            Share
          </button>
          <span class="text-gray-300">|</span>
          @if($product->is_free_shipping)
            <span class="text-green-600 font-medium text-xs">Free shipping</span>
          @endif
          <span class="text-xs text-gray-500">Delivery <strong class="text-gray-700">3–5 days</strong></span>
        </div>
      </div>
    </div>

    {{-- Tabs — visible without scrolling --}}
    <div class="mt-3" x-data="{ tab: (window.location.hash === '#product-qa' || @json($errors->has('question') || $errors->has('answer'))) ? 'qa' : 'description' }" id="product-tabs">
      <div class="border-b flex overflow-x-auto" style="gap: 1.75rem;">
        @foreach(['description' => 'Description', 'specifications' => 'Specifications', 'reviews' => 'Reviews', 'qa' => 'Q&A'] as $key => $label)
          <button type="button" @click="tab = '{{ $key }}'"
                  :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                  class="pb-2 border-b-2 font-medium text-sm whitespace-nowrap transition shrink-0"
                  style="margin-right: 0.25rem;">
            {{ $label }}
            @if($key === 'reviews')
              ({{ $product->total_reviews }})
            @endif
            @if($key === 'qa')
              ({{ $product->questions_count ?? $product->questions->count() }})
            @endif
            
          </button>
        @endforeach
      </div>

      <div class="py-4">
        <div x-show="tab === 'description'" class="prose prose-sm max-w-none text-gray-600">
          @if($product->tags || $product->short_description)
            <div class="mb-3 not-prose">
              <p class="text-sm font-semibold text-gray-900 mb-1">Highlights</p>
              <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                @if(is_array($product->tags))
                  @foreach($product->tags as $tag)
                    <li>{{ $tag }}</li>
                  @endforeach
                @elseif($product->short_description)
                  <li>{{ $product->short_description }}</li>
                @endif
              </ul>
            </div>
          @endif
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
            <form action="{{ route('products.reviews.store', $product) }}" method="POST" enctype="multipart/form-data" class="card p-6 mb-8">
              @csrf
              <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h3 class="font-semibold text-gray-900">Write a Review</h3>
                <div data-review-ai data-generate-url="{{ route('products.reviews.generate', $product) }}" class="flex items-center gap-2">
                  <span data-ai-status class="text-xs text-gray-400"></span>
                  <button type="button" data-ai-generate class="text-xs font-medium text-primary hover:underline">
                    Generate with AI
                  </button>
                </div>
              </div>
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
                  <input type="text" name="title" required class="input-field" placeholder="Summary of your experience" value="{{ old('title') }}">
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Review</label>
                  <textarea name="body" rows="4" required class="input-field" placeholder="Share details about the product">{{ old('body') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Photos <span class="text-gray-400 font-normal">(optional, up to 5)</span></label>
                  <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="input-field">
                  <p class="text-xs text-gray-400 mt-1">JPEG, PNG or WebP — max 2MB each</p>
                  @error('images')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                  @enderror
                  @error('images.*')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                  @enderror
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
                @if($review->image_urls)
                  <div class="flex flex-wrap gap-2 mt-3">
                    @foreach($review->image_urls as $url)
                      <a href="{{ $url }}" target="_blank" rel="noopener" class="block w-20 h-20 rounded-lg overflow-hidden bg-gray-100 ring-1 ring-gray-200">
                        <img src="{{ $url }}" alt="Review photo" class="w-full h-full object-cover">
                      </a>
                    @endforeach
                  </div>
                @endif
                <p class="text-xs text-gray-400 mt-2">{{ $review->user->name }} · {{ $review->created_at->format('M d, Y') }}</p>
              </div>
            @empty
              <p class="text-gray-500 text-sm">No reviews yet. Be the first to review!</p>
            @endforelse
          </div>
        </div>

        <div id="product-qa" x-show="tab === 'qa'" x-cloak>
          <div class="max-w-3xl">
            @auth
              <form action="{{ route('products.questions.store', $product) }}" method="POST" class="card p-4 mb-5">
                @csrf
                <h3 class="font-semibold text-gray-900 text-sm mb-2">Ask a question</h3>
                <textarea name="question" rows="3" required minlength="5" maxlength="1000"
                          class="input-field" placeholder="Ask the seller about this product...">{{ old('question') }}</textarea>
                @error('question')
                  <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-primary mt-3 h-9 text-sm">Submit Question</button>
              </form>
            @else
              <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 mb-5 text-sm text-gray-600">
                <a href="{{ route('login') }}" class="text-primary font-medium hover:underline">Login</a> to ask a question about this product.
              </div>
            @endauth

            <div class="space-y-4">
              @forelse($product->questions as $qa)
                <div class="border border-gray-100 rounded-lg p-4">
                  <p class="text-sm font-medium text-gray-900">Q: {{ $qa->question }}</p>
                  <p class="text-xs text-gray-400 mt-1">{{ $qa->user->name ?? 'Customer' }} · {{ $qa->created_at->format('M d, Y') }}</p>

                  @if($qa->isAnswered())
                    <div class="mt-3 pl-3 border-l-2 border-primary/40">
                      <p class="text-sm text-gray-700"><span class="font-semibold text-primary">A:</span> {{ $qa->answer }}</p>
                      <p class="text-xs text-gray-400 mt-1">
                        {{ $qa->answeredByUser->name ?? 'Seller' }}
                        @if($qa->answered_at)
                          · {{ $qa->answered_at->format('M d, Y') }}
                        @endif
                      </p>
                    </div>
                  @elseif($canAnswerQuestions)
                    <form action="{{ route('products.questions.answer', [$product, $qa]) }}" method="POST" class="mt-3 space-y-2">
                      @csrf
                      <textarea name="answer" rows="2" required minlength="2" maxlength="2000"
                                class="input-field" placeholder="Write your answer..."></textarea>
                      <button type="submit" class="btn-primary h-8 text-xs px-3">Post Answer</button>
                    </form>
                  @else
                    <p class="mt-2 text-xs text-amber-600">Waiting for seller reply…</p>
                  @endif
                </div>
              @empty
                <p class="text-gray-500 text-sm">No questions yet. Be the first to ask!</p>
              @endforelse
            </div>
          </div>
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
