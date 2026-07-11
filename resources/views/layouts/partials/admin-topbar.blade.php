@php
    $pendingMerchants = \App\Models\Merchant::query()->where('status', 'pending')->count();
    $pendingProducts = \App\Models\Product::query()->where('approval_status', 'pending')->count();
    $pendingCount = $pendingMerchants + $pendingProducts;
@endphp
<header class="admin-topbar sticky top-0 z-30 flex h-16 items-center gap-3 px-4 lg:px-6">
    <button type="button" class="admin-icon-btn lg:hidden" @click="mobileSidebar = !mobileSidebar" title="Menu">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    <div class="hidden min-w-0 sm:block">
        <p class="truncate text-sm font-semibold text-gray-900">@yield('page-title', 'Admin')</p>
        <p class="truncate text-xs text-gray-400">{{ config('shipnest.name', 'ShipNest') }}</p>
    </div>

    <form action="{{ route('admin.users.index') }}" method="GET" class="mx-auto hidden max-w-md flex-1 md:block">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" placeholder="Search users, orders..."
                   class="input-field w-full py-2 pl-9 text-sm" value="{{ request('search') }}">
        </div>
    </form>

    <div class="ml-auto flex items-center gap-1 sm:gap-2">
        <a href="{{ route('home') }}" target="_blank" class="admin-icon-btn hidden sm:inline-flex" title="View storefront">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </a>
        <a href="{{ route('admin.merchants.index', ['tab' => 'pending']) }}" class="admin-icon-btn relative" title="Pending approvals">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            @if($pendingCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#F57C00] px-1 text-[10px] font-bold text-white">{{ $pendingCount > 9 ? '9+' : $pendingCount }}</span>
            @endif
        </a>
        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-gray-50">
                <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-9 w-9 rounded-full object-cover ring-2 ring-[#F57C00]/40">
                <span class="hidden max-w-[120px] truncate text-sm font-medium text-gray-800 lg:inline">{{ auth()->user()->name }}</span>
                <svg class="hidden h-4 w-4 text-gray-400 lg:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak x-transition
                 class="absolute right-0 z-50 mt-2 w-52 rounded-xl bg-white py-1 shadow-xl ring-1 ring-gray-100">
                <a href="{{ route('admin.2fa.setup') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Two-Factor Auth</a>
                <a href="{{ route('admin.settings.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Settings</a>
                <a href="{{ route('home') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">View Store</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">Logout</button>
                </form>
            </div>
        </div>
    </div>
</header>
