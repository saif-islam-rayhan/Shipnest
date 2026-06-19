<nav class="bg-primary hidden lg:block">
    <div class="max-w-7xl mx-auto px-4">
        <ul class="flex items-center gap-1 text-sm font-medium text-white">
            <li>
                <a href="{{ route('home') }}" class="block px-3 py-2.5 hover:bg-primary-600 rounded transition-colors whitespace-nowrap">Home</a>
            </li>
            <li>
                <a href="{{ route('products.index') }}" class="block px-3 py-2.5 hover:bg-primary-600 rounded transition-colors whitespace-nowrap">All Products</a>
            </li>

            @foreach($navCategories ?? [] as $category)
                <li class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                    <a href="{{ route('products.index', ['category' => $category->id]) }}"
                       class="flex items-center gap-1 px-3 py-2.5 hover:bg-primary-600 rounded transition-colors whitespace-nowrap">
                        <span>{{ $category->icon ?? '📦' }}</span>
                        <span>{{ $category->name }}</span>
                        @if($category->children->isNotEmpty())
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        @endif
                    </a>

                    @if($category->children->isNotEmpty())
                        <div x-show="open" x-transition
                             class="absolute left-0 top-full pt-1 z-50 min-w-[280px]"
                             style="display: none;">
                            <div class="bg-white rounded-lg shadow-xl ring-1 ring-gray-100 py-3 px-2">
                                <a href="{{ route('products.index', ['category' => $category->id]) }}"
                                   class="block px-3 py-2 text-sm font-semibold text-primary hover:bg-primary-50 rounded">
                                    View All {{ $category->name }}
                                </a>
                                <div class="grid grid-cols-2 gap-1 mt-2">
                                    @foreach($category->children as $child)
                                        <a href="{{ route('products.index', ['category' => $child->id]) }}"
                                           class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary rounded transition-colors">
                                            <span class="text-base">{{ $child->icon ?? '•' }}</span>
                                            <span class="truncate">{{ $child->name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach

            <li class="ml-auto">
                <a href="{{ route('register', ['role' => 'merchant']) }}"
                   class="block px-3 py-2.5 hover:bg-primary-600 rounded transition-colors whitespace-nowrap font-semibold">
                    Sell on ShipNest
                </a>
            </li>
        </ul>
    </div>
</nav>
