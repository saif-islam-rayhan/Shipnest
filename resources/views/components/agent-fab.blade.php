@props([
    'context' => 'public',
])

@php
    use App\Support\AgentBranding;

    $isAdmin = $context === 'admin';
    $sendRoute = $isAdmin ? 'admin.agent.send' : 'agent.send';
    $resetRoute = $isAdmin ? 'admin.agent.reset' : 'agent.reset';
    $bootstrapRoute = $isAdmin ? 'admin.agent.bootstrap' : 'agent.bootstrap';
    $title = AgentBranding::name();
    $logoUrl = AgentBranding::logoUrl();
    $subtitle = $isAdmin
        ? 'Product create · Image search · Cart · Market trends'
        : 'Trusted ecommerce · Image search · Cart';
    $placeholder = $isAdmin ? 'Ask or tap mic...' : 'বলুন বা লিখুন — mic চাপুন...';
    $chips = $isAdmin
        ? ['create product', 'trending product ki?', 'watch']
        : ['hi', 'trending product ki?', 'watch', 'earbuds'];
    $welcomeTitle = $isAdmin ? 'How can I help?' : $title.'-এ স্বাগতম!';
    $welcomeText = $isAdmin
        ? 'Product create, catalog search, cart, market research — no login required'
        : 'বিশ্বস্ত ecommerce · Trending products · Cart-এ add (login লাগবে না)';
    $fullScreenUrl = $isAdmin ? route('admin.agent.index') : null;
@endphp

<div
    x-data="{
        open: false,
        loaded: false,
        async toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded) {
                this.loaded = true;
                const widget = this.$el.querySelector('[data-admin-agent-root]');
                if (widget && window.AdminAgentWidget) {
                    await window.AdminAgentWidget.bootstrap(widget);
                }
            }
        }
    }"
    class="admin-agent-fab-root"
>
    <button
        type="button"
        @click="toggle()"
        class="admin-agent-fab-btn"
        :class="{ 'admin-agent-fab-btn--active': open }"
        title="{{ $title }}"
        aria-label="Open {{ $title }}"
    >
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $title }}" class="admin-agent-fab-logo" x-show="!open">
        @else
            <svg x-show="!open" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
            </svg>
        @endif
        <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="admin-agent-panel"
        @keydown.escape.window="open = false"
    >
        <div class="admin-agent-panel-header">
            <div class="flex items-center gap-2.5 min-w-0">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="admin-agent-panel-logo shrink-0" aria-hidden="true">
                @endif
                <div class="min-w-0">
                    <p class="admin-agent-panel-title truncate">{{ $title }}</p>
                    <p class="admin-agent-panel-sub truncate">{{ $subtitle }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                @if($fullScreenUrl)
                    <a href="{{ $fullScreenUrl }}" class="admin-agent-panel-link" title="Full screen">↗</a>
                @endif
                @if($isAdmin && auth()->user()?->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="admin-agent-panel-link text-[10px] w-auto px-1" title="Admin panel">Admin</a>
                @endif
                <button type="button" class="admin-agent-panel-link" data-agent-widget-reset title="New chat">↺</button>
                <button type="button" class="admin-agent-panel-link" @click="open = false" title="Close">×</button>
            </div>
        </div>

        <div
            data-admin-agent-root
            data-send-url="{{ route($sendRoute) }}"
            data-reset-url="{{ route($resetRoute) }}"
            data-bootstrap-url="{{ route($bootstrapRoute) }}"
            data-cart-url="{{ route($isAdmin ? 'admin.agent.cart' : 'agent.cart') }}"
            data-cart-page-url="{{ route('cart.index') }}"
            data-csrf="{{ csrf_token() }}"
            data-admin-mode="{{ $isAdmin ? '1' : '0' }}"
            class="admin-agent-panel-body"
        >
            <div data-agent-feed class="admin-agent-feed">
                <div data-agent-welcome class="admin-agent-welcome">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $title }}" class="admin-agent-welcome-logo mx-auto mb-3">
                    @endif
                    <p class="font-semibold text-gray-900 text-sm mb-1">{{ $welcomeTitle }}</p>
                    <p class="text-xs text-gray-500 mb-3">{{ $welcomeText }}</p>
                    <div class="flex flex-wrap gap-1.5 justify-center">
                        @foreach($chips as $chip)
                            <button type="button" class="admin-agent-chip follow-chip" data-query="{{ $chip }}">{{ $chip }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="admin-agent-input-bar">
                <div data-agent-image-preview class="admin-agent-image-preview hidden"></div>
                <div class="admin-agent-input-row">
                    <button type="button" data-agent-attach class="admin-agent-attach" title="Upload product image">📎</button>
                    <input type="file" data-agent-image-input accept="image/jpeg,image/png,image/jpg,image/webp" multiple hidden>
                    <textarea data-agent-input rows="1" placeholder="{{ $placeholder }}" class="admin-agent-textarea"></textarea>
                    <button type="button" data-agent-mic class="admin-agent-mic" title="Voice input" aria-label="Voice input">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>
                        </svg>
                    </button>
                    <button type="button" data-agent-send class="admin-agent-send" title="Send">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
