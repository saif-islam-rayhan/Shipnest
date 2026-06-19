@props(['merchant'])

<a href="{{ route('products.index', ['shop' => $merchant->id]) }}"
   {{ $attributes->merge(['class' => 'card group p-5 hover:shadow-md transition-shadow block']) }}>
    <div class="flex items-center gap-4">
        @if($merchant->logo)
            <img src="{{ asset('storage/'.$merchant->logo) }}" alt="{{ $merchant->shop_name }}"
                 class="w-16 h-16 rounded-full object-cover ring-2 ring-primary-100 group-hover:ring-primary transition">
        @else
            <div class="w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center text-primary font-bold text-xl ring-2 ring-primary-100 group-hover:ring-primary transition">
                {{ strtoupper(substr($merchant->shop_name, 0, 1)) }}
            </div>
        @endif

        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-900 truncate group-hover:text-primary transition-colors">{{ $merchant->shop_name }}</h3>
            <div class="flex items-center gap-2 mt-1">
                <x-rating-stars :rating="$merchant->rating ?? 0" size="sm" />
                <span class="text-xs text-gray-500">{{ number_format($merchant->rating ?? 0, 1) }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                {{ $merchant->products_count ?? $merchant->products()->count() }} products
                @if($merchant->is_verified)
                    <span class="inline-flex items-center ml-1 text-green-600">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 2.452l-1.41 1.41-3.18-3.18 1.41-1.41 1.77 1.77 3.54-3.54 1.41 1.41-4.95 4.95z" clip-rule="evenodd"/>
                        </svg>
                        Verified
                    </span>
                @endif
            </p>
        </div>
    </div>
</a>
