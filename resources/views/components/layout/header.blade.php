<header class="bg-secondary shadow-md" x-data="{ mobileMenu: false, searchOpen: false }">
  <div class="bg-secondary-900 text-white text-xs py-1.5">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
      <span>Free shipping on orders over {{ config('shipnest.currency_symbol') }}{{ number_format(config('shipnest.free_shipping_threshold')) }}</span>
      <div class="hidden sm:flex gap-4">
        <a href="tel:{{ config('shipnest.support_phone') }}" class="hover:text-primary-300">{{ config('shipnest.support_phone') }}</a>
        <a href="mailto:{{ config('shipnest.support_email') }}" class="hover:text-primary-300">{{ config('shipnest.support_email') }}</a>
      </div>
    </div>
  </div>

  <div class="max-w-7xl mx-auto px-4 py-3">
    <div class="flex items-center gap-4">
      <a href="{{ route('home') }}" class="flex-shrink-0">
        <span class="text-2xl font-bold text-white">
          <span class="text-primary">Ship</span>Nest
        </span>
      </a>

      <form action="{{ route('products.index') }}" method="GET" class="hidden md:flex flex-1 max-w-2xl mx-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search for products, brands and more..."
               class="flex-1 rounded-l-md border-0 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary">
        <button type="submit" class="bg-primary hover:bg-primary-600 text-white px-6 rounded-r-md transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </button>
      </form>

      <div class="flex items-center gap-3 ml-auto">
        @auth
          <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="flex items-center gap-2 text-white hover:text-primary-300 text-sm">
              <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-8 h-8 rounded-full ring-2 ring-primary">
              <span class="hidden lg:inline">{{ auth()->user()->name }}</span>
            </button>
            <div x-show="open" @click.outside="open = false" x-transition
                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
              @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Panel</a>
              @endif
              @if(auth()->user()->isMerchant())
                <a href="{{ route('merchant.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Seller Center</a>
              @endif
              <a href="{{ route('account.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
              <a href="{{ route('account.orders.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Orders</a>
              <a href="{{ route('account.wishlist.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Wishlist</a>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</button>
              </form>
            </div>
          </div>
        @else
          <a href="{{ route('login') }}" class="text-white hover:text-primary-300 text-sm font-medium">Login</a>
          <a href="{{ route('register') }}" class="btn-primary text-sm py-1.5">Register</a>
        @endauth

        <a href="{{ route('cart.index') }}" class="relative text-white hover:text-primary-300">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
          @if(($cartItemCount ?? 0) > 0)
            <span class="absolute -top-2 -right-2 bg-primary text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-medium">
              {{ $cartItemCount > 99 ? '99+' : $cartItemCount }}
            </span>
          @endif
        </a>
      </div>
    </div>
  </div>

  <nav class="bg-primary">
    <div class="max-w-7xl mx-auto px-4">
      <ul class="flex items-center gap-6 py-2 text-sm font-medium text-white overflow-x-auto">
        <li><a href="{{ route('home') }}" class="hover:text-secondary-100 whitespace-nowrap">Home</a></li>
        <li><a href="{{ route('products.index') }}" class="hover:text-secondary-100 whitespace-nowrap">All Products</a></li>
        <li><a href="{{ route('products.index', ['sort' => 'popular']) }}" class="hover:text-secondary-100 whitespace-nowrap">Best Sellers</a></li>
        <li><a href="{{ route('register', ['role' => 'merchant']) }}" class="hover:text-secondary-100 whitespace-nowrap">Sell on ShipNest</a></li>
      </ul>
    </div>
  </nav>
</header>
