<div class="bg-secondary shadow-md"
     x-data="{
         scrolled: false,
         mobileMenu: false,
         searchOpen: false,
         query: '{{ request('q') }}',
         results: [],
         loading: false,
         async search() {
             if (this.query.length < 2) { this.results = []; this.searchOpen = false; return; }
             this.loading = true;
             try {
                 const res = await fetch('{{ url('/api/search') }}?q=' + encodeURIComponent(this.query));
                 this.results = await res.json();
                 this.searchOpen = this.results.length > 0;
             } catch (e) { this.results = []; }
             this.loading = false;
         }
     }"
     @scroll.window="scrolled = window.scrollY > 80"
     :class="scrolled ? 'sticky top-0 z-50 shadow-xl' : 'shadow-md'">

    @include('layouts.partials.topbar')

    <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex items-center gap-3 lg:gap-4">
            <button type="button" @click="mobileMenu = true" class="lg:hidden text-white p-1" aria-label="Open menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <a href="{{ route('home') }}" class="flex-shrink-0">
                <span class="text-2xl font-bold text-white tracking-tight">
                    <span class="text-primary">Ship</span>Nest
                </span>
            </a>

            <div class="hidden md:flex flex-1 max-w-2xl mx-4 relative">
                <form action="{{ route('products.index') }}" method="GET" class="flex w-full" @submit="searchOpen = false">
                    <div class="relative flex-1">
                        <input type="text" name="q" x-model="query" @input.debounce.300ms="search()" @focus="if(results.length) searchOpen = true"
                               @keydown.escape="searchOpen = false"
                               placeholder="Search for products, brands and more..."
                               class="w-full rounded-l-lg border-0 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary pr-10"
                               autocomplete="off">
                        <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                            <svg class="animate-spin w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                    </div>
                    <button type="submit" class="bg-primary hover:bg-primary-600 text-white px-5 rounded-r-lg transition flex items-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>
                </form>

                <div x-show="searchOpen" @click.outside="searchOpen = false" x-transition
                     class="absolute top-full left-0 right-12 mt-1 bg-white rounded-lg shadow-xl border border-gray-100 z-50 max-h-80 overflow-y-auto">
                    <template x-for="item in results" :key="item.id">
                        <a :href="item.url" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 border-b border-gray-50 last:border-0">
                            <img :src="item.image || '/images/placeholder.png'" :alt="item.name" class="w-10 h-10 object-cover rounded bg-gray-100"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%23d1d5db%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221%22 d=%22M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z%22/%3E%3C/svg%3E'">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 truncate" x-text="item.name"></p>
                                <p class="text-xs text-gray-500" x-text="item.merchant"></p>
                            </div>
                            <span class="text-sm font-semibold text-primary" x-text="item.formatted_price"></span>
                        </a>
                    </template>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-4 ml-auto">
                <a href="#" class="relative hidden sm:flex flex-col items-center text-white hover:text-primary-300 transition-colors" title="Wishlist">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    @if(($wishlistCount ?? 0) > 0)
                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-primary text-white text-[10px] font-bold rounded-full flex items-center justify-center">{{ $wishlistCount }}</span>
                    @endif
                    <span class="text-[10px] mt-0.5 hidden lg:block">Wishlist</span>
                </a>

                <a href="{{ route('cart.index') }}" class="relative flex flex-col items-center text-white hover:text-primary-300 transition-colors" title="Cart">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    @if(($cartItemCount ?? 0) > 0)
                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-primary text-white text-[10px] font-bold rounded-full flex items-center justify-center">{{ $cartItemCount }}</span>
                    @endif
                    <span class="text-[10px] mt-0.5 hidden lg:block">Cart</span>
                </a>

                @auth
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 text-white hover:text-primary-300">
                            <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-8 h-8 rounded-full ring-2 ring-primary object-cover">
                            <span class="hidden lg:inline text-sm max-w-[100px] truncate">{{ auth()->user()->name }}</span>
                            <svg class="w-4 h-4 hidden lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition
                             class="absolute right-0 mt-2 w-52 bg-white rounded-lg shadow-xl py-1 z-50 ring-1 ring-gray-100">
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50 hover:text-primary">Admin Panel</a>
                            @endif
                            @if(auth()->user()->isMerchant())
                                <a href="{{ route('merchant.dashboard') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50 hover:text-primary">Seller Center</a>
                            @endif
                            <a href="{{ route('orders.index') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50 hover:text-primary">My Orders</a>
                            <hr class="my-1 border-gray-100">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">Logout</button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <a href="{{ route('login') }}" class="text-white hover:text-primary-300 text-sm font-medium hidden sm:inline">Login</a>
                        <a href="{{ route('register') }}" class="btn-primary text-xs sm:text-sm py-1.5 px-3">Register</a>
                    </div>
                @endauth
            </div>
        </div>

        <form action="{{ route('products.index') }}" method="GET" class="md:hidden mt-3 flex">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products..."
                   class="flex-1 rounded-l-lg border-0 px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
            <button type="submit" class="bg-primary text-white px-4 rounded-r-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>
        </form>
    </div>

    @include('layouts.partials.nav')

    {{-- Mobile drawer --}}
    <div x-show="mobileMenu" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] lg:hidden" style="display: none;">
        <div class="absolute inset-0 bg-black/50" @click="mobileMenu = false"></div>
        <div x-show="mobileMenu" x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
             class="absolute left-0 top-0 bottom-0 w-80 max-w-[85vw] bg-white shadow-2xl overflow-y-auto">
            <div class="bg-secondary px-4 py-4 flex items-center justify-between">
                <span class="text-xl font-bold text-white"><span class="text-primary">Ship</span>Nest</span>
                <button @click="mobileMenu = false" class="text-white p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <nav class="p-4">
                <a href="{{ route('home') }}" class="block py-3 text-gray-800 font-medium border-b border-gray-100">Home</a>
                <a href="{{ route('products.index') }}" class="block py-3 text-gray-800 font-medium border-b border-gray-100">All Products</a>
                @foreach($navCategories ?? [] as $category)
                    <div x-data="{ open: false }" class="border-b border-gray-100">
                        <button @click="open = !open" class="flex items-center justify-between w-full py-3 text-gray-800 font-medium">
                            <span>{{ $category->icon ?? '📦' }} {{ $category->name }}</span>
                            <svg class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" class="pl-4 pb-2">
                            <a href="{{ route('products.index', ['category' => $category->id]) }}" class="block py-2 text-sm text-primary font-medium">View All</a>
                            @foreach($category->children as $child)
                                <a href="{{ route('products.index', ['category' => $child->id]) }}" class="block py-2 text-sm text-gray-600">{{ $child->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <a href="{{ route('register', ['role' => 'merchant']) }}" class="block py-3 text-primary font-medium mt-2">Sell on ShipNest</a>
            </nav>
        </div>
    </div>
</div>
