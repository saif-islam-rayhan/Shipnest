@php
    use App\Support\AgentBranding;

    $isActive = function (string $route) {
        if (request()->routeIs($route)) {
            return true;
        }
        if (str_ends_with($route, '.index')) {
            return request()->routeIs(str_replace('.index', '.*', $route));
        }

        return false;
    };

    $isItemActive = function (array $item) use ($isActive) {
        if (! $isActive($item['route'])) {
            return false;
        }

        if (! empty($item['query'])) {
            foreach ($item['query'] as $key => $value) {
                if ((string) request()->query($key) !== (string) $value) {
                    return false;
                }
            }

            return true;
        }

        if ($item['route'] === 'admin.users.index' && request()->filled('role')) {
            return false;
        }

        return true;
    };

    $isChildActive = function (array $child) {
        if (! request()->routeIs($child['route'])) {
            return false;
        }

        if (isset($child['tab'])) {
            return request()->query('tab', 'general') === $child['tab'];
        }

        foreach ($child['query'] ?? [] as $key => $value) {
            if ((string) request()->query($key) !== (string) $value) {
                return false;
            }
        }

        return true;
    };

    $settingsTabs = config('admin_settings.tabs', []);
    $settingsChildren = collect($settingsTabs)->map(
        fn (array $meta, string $key) => [
            'route' => 'admin.settings.edit',
            'tab' => $key,
            'label' => $meta['label'],
            'query' => ['tab' => $key],
        ],
    )->values()->all();

    $sections = [
        [
            'label' => null,
            'items' => [
                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ],
        ],
        [
            'label' => 'Catalog',
            'items' => [
                ['route' => 'admin.products.index', 'label' => 'Products', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ['route' => 'admin.categories.index', 'label' => 'Categories', 'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
                ['route' => 'admin.brands.index', 'label' => 'Brands', 'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
                ['route' => 'admin.promotions.index', 'label' => 'Promotions', 'icon' => 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7'],
            ],
        ],
        [
            'label' => 'Sales',
            'items' => [
                ['route' => 'admin.pos.index', 'label' => 'POS', 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ['route' => 'admin.orders.index', 'label' => 'Orders', 'icon' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
                ['route' => 'admin.payments.index', 'label' => 'Payments', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
                ['route' => 'admin.reviews.index', 'label' => 'Reviews', 'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
                ['route' => 'admin.finance.index', 'label' => 'Finance', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ],
        ],
        [
            'label' => 'Users',
            'items' => [
                ['route' => 'admin.users.index', 'label' => 'Customers', 'query' => ['role' => 'customer'], 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1m0 0h6v-1a6 6 0 00-9-5.197'],
                ['route' => 'admin.merchants.index', 'label' => 'Merchants', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
            ],
        ],
        [
            'label' => 'Authentication',
            'items' => [
                ['route' => 'admin.2fa.setup', 'label' => 'Two-Factor Auth', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                ['route' => 'admin.users.index', 'label' => 'Admin Users', 'query' => ['role' => 'admin'], 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                ['route' => 'admin.ai-design.index', 'label' => 'AI Mode', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
                ['route' => 'admin.agent.index', 'label' => AgentBranding::name(), 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
                ['route' => 'admin.pages.index', 'label' => 'CMS Pages', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['route' => 'admin.settings.edit', 'label' => 'Settings', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'children' => $settingsChildren],
            ],
        ],
    ];
@endphp

<aside class="admin-sidebar fixed inset-y-0 left-0 z-50 flex -translate-x-full flex-col text-white transition-all duration-300 lg:translate-x-0"
       :class="{
           'w-64': sidebarOpen || mobileSidebar,
           'w-[72px]': !sidebarOpen && !mobileSidebar,
           'translate-x-0': mobileSidebar
       }">

    <div class="flex h-16 items-center justify-between border-b border-white/10 px-4">
        <a href="{{ route('admin.dashboard') }}"
           @click.prevent="if (!sidebarOpen && !mobileSidebar && window.innerWidth >= 1024) { sidebarOpen = true } else { window.location = '{{ route('admin.dashboard') }}' }"
           class="flex min-w-0 items-center gap-2.5"
           :class="!sidebarOpen && !mobileSidebar ? 'mx-auto' : ''">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#F57C00] text-sm font-bold">S</span>
            <div class="min-w-0" x-show="sidebarOpen || mobileSidebar" x-cloak>
                <p class="truncate text-sm font-bold"><span class="text-[#F57C00]">Ship</span>Nest</p>
                <p class="truncate text-[10px] text-slate-400">Admin Panel</p>
            </div>
        </a>
        <button type="button"
                x-show="sidebarOpen || mobileSidebar"
                x-cloak
                @click="window.innerWidth >= 1024 ? sidebarOpen = !sidebarOpen : mobileSidebar = !mobileSidebar"
                class="admin-icon-btn text-slate-400 hover:bg-white/10 hover:text-white">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <nav class="flex-1 space-y-4 overflow-y-auto p-3">
        @foreach($sections as $section)
            <div>
                @if($section['label'])
                    <p class="admin-nav-section-label" x-show="sidebarOpen || mobileSidebar" x-cloak>{{ $section['label'] }}</p>
                @endif
                <div class="space-y-0.5">
                    @foreach($section['items'] as $item)
                        @php
                            $hasChildren = ! empty($item['children']);
                            $active = $hasChildren
                                ? request()->routeIs('admin.settings.*')
                                : $isItemActive($item);
                            $href = $item['url'] ?? route($item['route'], $item['query'] ?? []);
                        @endphp

                        @if($hasChildren)
                            <div class="space-y-0.5">
                                <button type="button"
                                        @click="if (!sidebarOpen && !mobileSidebar) { window.location = '{{ route('admin.settings.edit', ['tab' => 'general']) }}'; return; } settingsOpen = !settingsOpen"
                                        title="{{ $item['label'] }}"
                                        class="admin-nav-link w-full {{ $active ? 'admin-nav-link-active' : '' }}">
                                    <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-[#F57C00]' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $item['icon'] }}"/>
                                    </svg>
                                    <span x-show="sidebarOpen || mobileSidebar" x-cloak class="truncate flex-1 text-left">{{ $item['label'] }}</span>
                                    <svg x-show="sidebarOpen || mobileSidebar" x-cloak
                                         class="h-4 w-4 shrink-0 text-slate-500 transition-transform"
                                         :class="settingsOpen ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <div x-show="settingsOpen && (sidebarOpen || mobileSidebar)"
                                     x-cloak
                                     class="admin-nav-children">
                                    @foreach($item['children'] as $child)
                                        @php
                                            $childActive = $isChildActive($child);
                                            $childHref = route($child['route'], $child['query'] ?? []);
                                        @endphp
                                        <a href="{{ $childHref }}"
                                           @click="mobileSidebar = false"
                                           class="admin-nav-sublink {{ $childActive ? 'is-active' : '' }}">
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                        <a href="{{ $href }}"
                           @click="mobileSidebar = false"
                           title="{{ $item['label'] }}"
                           class="admin-nav-link {{ $active ? 'admin-nav-link-active' : '' }}">
                            <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-[#F57C00]' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $item['icon'] }}"/>
                            </svg>
                            <span x-show="sidebarOpen || mobileSidebar" x-cloak class="truncate">{{ $item['label'] }}</span>
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>
