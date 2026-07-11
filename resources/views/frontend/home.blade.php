@extends('layouts.frontend')

@section('title', 'Home')

@section('content')

{{-- 1. Hero Banner Slider --}}
<section class="bg-white" x-data="{
    current: 0,
    total: {{ $heroSlideCount }},
    autoplay: null,
    init() {
        this.autoplay = setInterval(() => { this.current = (this.current + 1) % this.total; }, 5000);
    },
    destroy() { clearInterval(this.autoplay); }
}">
    <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="relative rounded-xl overflow-hidden shadow-sm aspect-[21/7] min-h-[180px] sm:min-h-[240px] md:min-h-[320px]">
            @if($heroDiscountProducts->isNotEmpty())
                @foreach($heroDiscountProducts as $index => $product)
                    <a href="{{ route('products.show', $product->slug) }}"
                       x-show="current === {{ $index }}"
                       x-transition:enter="transition ease-out duration-500"
                       x-transition:enter-start="opacity-0"
                       x-transition:enter-end="opacity-100"
                       class="absolute inset-0 block">
                        @if($product->primary_image_url)
                            <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}"
                                 class="w-full h-full object-cover"
                                 loading="{{ $index === 0 ? 'eager' : 'lazy' }}">
                        @else
                            <div class="w-full h-full bg-gradient-to-r from-primary to-primary-700"></div>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-r from-black/50 to-transparent flex items-center">
                            <div class="px-8 md:px-12 max-w-xl">
                                @if($product->discount_percentage)
                                    <span class="inline-block mb-3 bg-primary text-white text-sm font-bold px-3 py-1 rounded-full">
                                        UP TO {{ $product->discount_percentage }}% OFF
                                    </span>
                                @endif
                                <h2 class="text-2xl md:text-4xl font-bold text-white drop-shadow-lg line-clamp-2">{{ $product->name }}</h2>
                                <div class="mt-3 flex items-baseline gap-3">
                                    <span class="text-xl md:text-2xl font-bold text-white">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</span>
                                    @if($product->compare_price && $product->compare_price > $product->price)
                                        <span class="text-base md:text-lg text-white/70 line-through">{{ config('shipnest.currency_symbol') }}{{ number_format($product->compare_price) }}</span>
                                    @endif
                                </div>
                                <span class="inline-block mt-4 btn-primary">Shop Now</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            @elseif($heroBanners->isNotEmpty())
                @foreach($heroBanners as $index => $banner)
                    <a href="{{ $banner->link ?: route('products.index') }}"
                       x-show="current === {{ $index }}"
                       x-transition:enter="transition ease-out duration-500"
                       x-transition:enter-start="opacity-0"
                       x-transition:enter-end="opacity-100"
                       class="absolute inset-0 block">
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}"
                             class="w-full h-full object-cover"
                             onerror="this.parentElement.innerHTML='<div class=\'w-full h-full bg-gradient-to-r from-secondary to-primary flex items-center justify-center\'><div class=\'text-center text-white px-6\'><h2 class=\'text-2xl md:text-4xl font-bold\'>{{ addslashes($banner->title) }}</h2></div></div>'">
                        <div class="absolute inset-0 bg-gradient-to-r from-black/40 to-transparent flex items-center">
                            <div class="px-8 md:px-12">
                                <h2 class="text-2xl md:text-4xl font-bold text-white drop-shadow-lg">{{ $banner->title }}</h2>
                                <span class="inline-block mt-4 btn-primary">Shop Now</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            @else
                @foreach([
                    ['title' => 'Mega Sale — Up to 70% Off', 'gradient' => 'from-primary to-primary-700'],
                    ['title' => 'New Arrivals in Electronics', 'gradient' => 'from-secondary to-secondary-700'],
                    ['title' => 'Free Shipping on Orders Over ৳500', 'gradient' => 'from-primary-600 to-secondary'],
                ] as $index => $slide)
                    <div x-show="current === {{ $index }}"
                         x-transition:enter="transition ease-out duration-500"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         class="absolute inset-0 bg-gradient-to-r {{ $slide['gradient'] }} flex items-center">
                        <div class="px-8 md:px-12 max-w-xl">
                            <h2 class="text-2xl md:text-4xl font-bold text-white">{{ $slide['title'] }}</h2>
                            <a href="{{ route('products.index') }}" class="inline-block mt-6 btn-primary bg-white text-primary hover:bg-gray-100">Shop Now</a>
                        </div>
                    </div>
                @endforeach
            @endif

            <button @click="current = (current - 1 + total) % total" class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 hover:bg-white shadow flex items-center justify-center text-gray-700 z-10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button @click="current = (current + 1) % total" class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 hover:bg-white shadow flex items-center justify-center text-gray-700 z-10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                @for($i = 0; $i < $heroSlideCount; $i++)
                    <button @click="current = {{ $i }}"
                            :class="current === {{ $i }} ? 'bg-primary w-6' : 'bg-white/60 w-2'"
                            class="h-2 rounded-full transition-all duration-300"></button>
                @endfor
            </div>
        </div>
    </div>
</section>

{{-- 2. Flash Sale --}}
@if($flashSale && $flashSale->products->isNotEmpty())
<section class="bg-white border-y border-gray-100"
         x-data="{
             endsAt: new Date('{{ $flashSale->ends_at->toIso8601String() }}').getTime(),
             days: 0, hours: 0, minutes: 0, seconds: 0,
             tick() {
                 const diff = Math.max(0, this.endsAt - Date.now());
                 this.days = Math.floor(diff / 86400000);
                 this.hours = Math.floor((diff % 86400000) / 3600000);
                 this.minutes = Math.floor((diff % 3600000) / 60000);
                 this.seconds = Math.floor((diff % 60000) / 1000);
             },
             init() { this.tick(); setInterval(() => this.tick(), 1000); }
         }">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span class="text-2xl">⚡</span> {{ $flashSale->title }}
                </h2>
                <div class="flex items-center gap-1.5 text-sm font-mono font-bold">
                    <span class="bg-gray-900 text-white px-2 py-1 rounded" x-text="String(days).padStart(2,'0')"></span>
                    <span class="text-gray-400">:</span>
                    <span class="bg-gray-900 text-white px-2 py-1 rounded" x-text="String(hours).padStart(2,'0')"></span>
                    <span class="text-gray-400">:</span>
                    <span class="bg-gray-900 text-white px-2 py-1 rounded" x-text="String(minutes).padStart(2,'0')"></span>
                    <span class="text-gray-400">:</span>
                    <span class="bg-primary text-white px-2 py-1 rounded" x-text="String(seconds).padStart(2,'0')"></span>
                </div>
            </div>
            <a href="{{ route('products.index') }}" class="text-primary text-sm font-semibold hover:underline">Shop All Deals →</a>
        </div>

        <div class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory scrollbar-hide -mx-4 px-4">
            @foreach($flashSale->products as $flashProduct)
                @if($flashProduct->product)
                    <div class="flex-shrink-0 w-44 sm:w-52 snap-start">
                        <x-product-card :product="$flashProduct->product" />
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 3. Shop by Category --}}
@if($categories->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-10">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Shop by Category</h2>
    <div class="grid grid-cols-4 sm:grid-cols-4 md:grid-cols-8 gap-4">
        @foreach($categories as $category)
            <a href="{{ route('products.index', ['category' => $category->id]) }}" class="text-center group">
                <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-full bg-primary-50 ring-1 ring-primary-100 flex items-center justify-center group-hover:bg-primary group-hover:ring-primary transition-all duration-300">
                    <span class="text-xl sm:text-2xl group-hover:scale-110 transition-transform">{{ $category->icon ?? '📦' }}</span>
                </div>
                <p class="mt-2 text-xs text-gray-700 group-hover:text-primary line-clamp-2 font-medium">{{ $category->name }}</p>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- 4. Top Brands --}}
@if($brands->isNotEmpty())
<section class="bg-white py-10">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Top Brands</h2>
        <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4">
            @foreach($brands as $brand)
                <a href="{{ route('products.index', ['brand' => $brand->id]) }}"
                   class="flex-shrink-0 w-28 h-20 card flex items-center justify-center p-3 hover:shadow-md hover:border-primary transition group">
                    @if($brand->logo)
                        <img src="{{ asset('storage/'.$brand->logo) }}" alt="{{ $brand->name }}"
                             class="max-h-12 max-w-full object-contain grayscale group-hover:grayscale-0 transition">
                    @else
                        <span class="text-sm font-bold text-gray-600 group-hover:text-primary transition-colors">{{ $brand->name }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 5. Featured Products --}}
@if($featuredProducts->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900">Featured Products</h2>
        <a href="{{ route('products.index') }}" class="text-primary text-sm font-semibold hover:underline">View All →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($featuredProducts as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>
@endif

{{-- 6. New Arrivals --}}
@if($newArrivals->isNotEmpty())
<section class="bg-white py-10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">New Arrivals</h2>
            <a href="{{ route('products.index', ['sort' => 'newest']) }}" class="text-primary text-sm font-semibold hover:underline">View All →</a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($newArrivals as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 7. Featured Merchants --}}
@if($featuredMerchants->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 py-10">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Featured Merchants</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($featuredMerchants as $merchant)
            <x-merchant-card :merchant="$merchant" />
        @endforeach
    </div>
</section>
@endif

{{-- 8. Promotional Banners --}}
<section class="max-w-7xl mx-auto px-4 py-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @if($promoBanners->isNotEmpty())
            @foreach($promoBanners as $banner)
                <a href="{{ $banner->link ?: route('products.index') }}" class="block rounded-xl overflow-hidden shadow-sm hover:shadow-md transition group aspect-[16/7] relative">
                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                         onerror="this.parentElement.classList.add('bg-gradient-to-r','from-secondary','to-primary');this.remove();">
                    <div class="absolute inset-0 bg-black/30 flex items-center px-6">
                        <h3 class="text-lg md:text-xl font-bold text-white">{{ $banner->title }}</h3>
                    </div>
                </a>
            @endforeach
        @else
            <a href="{{ route('products.index') }}" class="rounded-xl overflow-hidden bg-gradient-to-r from-secondary to-secondary-700 aspect-[16/7] flex items-center px-8 hover:shadow-md transition">
                <div>
                    <h3 class="text-xl font-bold text-white">Fashion Week Special</h3>
                    <p class="text-gray-200 text-sm mt-1">Up to 50% off on trending styles</p>
                </div>
            </a>
            <a href="{{ route('products.index') }}" class="rounded-xl overflow-hidden bg-gradient-to-r from-primary to-primary-700 aspect-[16/7] flex items-center px-8 hover:shadow-md transition">
                <div>
                    <h3 class="text-xl font-bold text-white">Electronics Bonanza</h3>
                    <p class="text-gray-200 text-sm mt-1">Latest gadgets at best prices</p>
                </div>
            </a>
        @endif
    </div>
</section>

{{-- 9. Newsletter --}}
<section class="bg-secondary py-12 mt-4">
    <div class="max-w-7xl mx-auto px-4">
        <div class="max-w-xl mx-auto text-center">
            <h2 class="text-2xl font-bold text-white">Subscribe to Our Newsletter</h2>
            <p class="text-gray-300 text-sm mt-2">Get the latest deals, new arrivals, and exclusive offers delivered to your inbox.</p>
            <form action="{{ route('newsletter.subscribe') }}" method="POST" class="mt-6 flex flex-col sm:flex-row gap-3">
                @csrf
                <input type="email" name="email" required placeholder="Enter your email address"
                       class="flex-1 rounded-lg border-0 px-4 py-3 text-sm focus:ring-2 focus:ring-primary"
                       value="{{ old('email') }}">
                <button type="submit" class="btn-primary px-8 py-3 whitespace-nowrap">Subscribe</button>
            </form>
        </div>
    </div>
</section>

@endsection
