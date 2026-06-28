@php
    $shop = auth()->user()->merchant;
    $pendingOrders = $shop ? \App\Models\Order::query()->forShop($shop->id)->where('status', 'pending')->count() : 0;
@endphp

<header class="bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between sticky top-0 z-30">
    <h1 class="text-lg font-semibold text-gray-900 truncate">@yield('page-title', 'Dashboard')</h1>

    <div class="flex items-center gap-4">
        <a href="{{ route('merchant.orders.index', ['status' => 'pending']) }}" class="relative text-gray-500 hover:text-[#F57C00]" title="Pending orders">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            @if($pendingOrders > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 bg-[#F57C00] text-white text-[10px] font-bold rounded-full flex items-center justify-center">{{ $pendingOrders > 9 ? '9+' : $pendingOrders }}</span>
            @endif
        </a>

        <div class="hidden sm:block text-right">
            <p class="text-sm font-medium text-gray-900">{{ $shop?->shop_name }}</p>
            <p class="text-xs text-gray-500">Merchant</p>
        </div>

        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="flex items-center gap-2">
                <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-9 h-9 rounded-full ring-2 ring-[#F57C00]">
            </button>
            <div x-show="open" @click.outside="open = false" x-transition
                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 z-50 ring-1 ring-gray-100">
                <a href="{{ route('merchant.settings.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Store Settings</a>
                <a href="{{ route('home') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">View Store</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</button>
                </form>
            </div>
        </div>
    </div>
</header>
