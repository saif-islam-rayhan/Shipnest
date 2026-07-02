@php
    $initialMessages = $messages->map(fn ($m) => [
        'id' => $m->id,
        'role' => $m->role,
        'content' => $m->content,
        'content_html' => \Illuminate\Support\Str::markdown($m->content),
        'meta' => $m->meta ?? [],
        'summary' => $m->meta['summary'] ?? null,
        'products' => $m->meta['products'] ?? [],
        'products_all' => $m->meta['products_all'] ?? [],
        'products_preview_count' => $m->meta['products_preview_count'] ?? 4,
        'trending_products' => $m->meta['trending_products'] ?? [],
        'trending_products_all' => $m->meta['trending_products_all'] ?? [],
        'trending_total_count' => $m->meta['trending_total_count'] ?? null,
        'trending_preview_count' => $m->meta['trending_preview_count'] ?? 4,
        'follow_ups' => $m->meta['follow_ups'] ?? [],
        'thought_process' => $m->meta['thought_process'] ?? [],
        'sources' => $m->meta['sources'] ?? [],
        'type' => $m->meta['type'] ?? 'text',
        'total_count' => $m->meta['total_count'] ?? null,
        'query' => $m->meta['query'] ?? null,
    ])->values();
@endphp
@extends('layouts.admin')
@section('title', 'AI Mode')
@section('page-title', 'AI Mode')

@push('styles')
<style>
    .agent-shell { height: calc(100vh - 7rem); min-height: 620px; }
    .agent-feed { scroll-behavior: smooth; }

    /* ── Alibaba AI Mode layout ── */
    .ai-turn { margin-bottom: 2.5rem; }
    .ai-turn-grid {
        display: grid;
        grid-template-columns: 88px 1fr;
        gap: 1.5rem;
        align-items: start;
    }
    @media (min-width: 1024px) { .ai-turn-grid { grid-template-columns: 120px 1fr; gap: 2rem; } }
    .ai-query-side {
        font-size: 0.875rem;
        color: #6b7280;
        padding-top: 0.25rem;
        word-break: break-word;
        line-height: 1.4;
    }
    .ai-heading {
        font-size: 1.125rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 1rem;
        letter-spacing: -0.01em;
    }
    .ai-subheading {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #374151;
        margin-bottom: 0.75rem;
        letter-spacing: -0.01em;
    }
    .ai-product-section { margin-top: 1.5rem; }
    .ai-product-section:first-child { margin-top: 0; }
    .see-more-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 12px;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        color: #1a73e8;
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        cursor: pointer;
        transition: background .15s, border-color .15s, color .15s;
    }
    .see-more-btn:hover {
        background: #eff6ff;
        border-color: #93c5fd;
        color: #F57C00;
    }

    /* ── Horizontal product carousel ── */
    .carousel-wrap { position: relative; }
    .product-carousel {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        padding-bottom: 4px;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .product-carousel::-webkit-scrollbar { display: none; }
    .carousel-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-70%);
        z-index: 10;
        width: 36px; height: 36px;
        border-radius: 50%;
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(0,0,0,.1);
        color: #374151;
        font-size: 1.25rem;
        line-height: 1;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: box-shadow .15s, background .15s;
    }
    .carousel-nav:hover { background: #f9fafb; box-shadow: 0 4px 12px rgba(0,0,0,.12); }
    .carousel-nav.prev { left: -12px; }
    .carousel-nav.next { right: -12px; }
    .carousel-nav.hidden { display: none; }

    /* ── Product card (Alibaba style) ── */
    .ai-card {
        flex: 0 0 200px;
        scroll-snap-align: start;
        cursor: default;
    }
    @media (min-width: 768px) { .ai-card { flex: 0 0 220px; } }
    .ai-card-img-wrap {
        position: relative;
        aspect-ratio: 1;
        background: #f3f4f6;
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid transparent;
        transition: border-color .15s;
    }
    .ai-card.selected .ai-card-img-wrap { border-color: #1a73e8; }
    .ai-card-img-wrap img {
        width: 100%; height: 100%;
        object-fit: contain;
        padding: 10px;
        cursor: pointer;
    }
    .ai-card-select {
        position: absolute;
        bottom: 10px; left: 50%;
        transform: translateX(-50%) translateY(6px);
        opacity: 0;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 6px 20px;
        font-size: 13px;
        font-weight: 500;
        color: #111827;
        box-shadow: 0 2px 8px rgba(0,0,0,.1);
        transition: opacity .15s, transform .15s, border-color .15s, background .15s;
        cursor: pointer;
        white-space: nowrap;
        z-index: 2;
    }
    .ai-card-select:hover { border-color: #1a73e8; }
    .ai-card.selected .ai-card-select {
        background: #1a73e8;
        border-color: #1a73e8;
        color: #fff;
    }
    .ai-card:hover .ai-card-select,
    .ai-card.selected .ai-card-select {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    .ai-card-lens {
        position: absolute;
        bottom: 8px; left: 8px;
        width: 28px; height: 28px;
        background: rgba(255,255,255,.9);
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        opacity: 0;
        transition: opacity .15s, background .15s;
        cursor: pointer;
        z-index: 2;
        padding: 0;
    }
    .ai-card-lens:hover { background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
    .ai-card:hover .ai-card-lens,
    .ai-card.selected .ai-card-lens { opacity: 1; }
    .ai-match-line {
        font-size: 11px;
        color: #1a73e8;
        margin-top: 8px;
        line-height: 1.3;
    }
    .ai-card-title {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        margin-top: 4px;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .ai-card-price {
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        margin-top: 4px;
    }
    .ai-card-cart {
        display: block;
        width: 100%;
        margin-top: 8px;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #fff;
        background: #F57C00;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background .15s;
    }
    .ai-card-cart:hover { background: #E65100; }
    .ai-card-cart:disabled { opacity: .5; cursor: not-allowed; }

    /* ── Trending product list (name only, no catalog cards) ── */
    .trending-list { list-style: none; padding: 0; margin: 0; }
    .trending-item {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 6px;
        padding: 10px 14px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
    }
    .trending-num { font-weight: 700; color: #F57C00; min-width: 1.5rem; }
    .trending-name { font-weight: 600; color: #111827; }
    .trending-price { color: #6b7280; font-size: 13px; }

    /* ── Bottom input (Alibaba pill) ── */
    .agent-input-outer { max-width: 48rem; margin: 0 auto; }
    .agent-input-wrap {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 0;
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
        overflow: hidden;
        transition: border-color .15s, box-shadow .15s;
    }
    .agent-input-wrap.has-selection {
        border-color: #fca5a5;
        box-shadow: 0 0 0 1px rgba(239,68,68,.15), 0 2px 8px rgba(0,0,0,.06);
    }
    .input-product-chip {
        display: flex;
        gap: 12px;
        padding: 12px 16px 10px;
        border-bottom: 1px solid #f3f4f6;
        align-items: flex-start;
    }
    .input-product-chip.hidden { display: none; }
    .chip-thumb {
        width: 48px; height: 48px;
        border-radius: 8px;
        object-fit: contain;
        background: #f3f4f6;
        flex-shrink: 0;
    }
    .chip-body { flex: 1; min-width: 0; }
    .chip-title {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }
    .chip-actions {
        display: flex;
        gap: 16px;
        margin-top: 6px;
        flex-wrap: wrap;
    }
    .chip-action {
        font-size: 13px;
        font-weight: 500;
        color: #111827;
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        text-decoration: none;
        transition: color .15s;
    }
    .chip-action:hover { color: #F57C00; }
    .chip-remove {
        flex-shrink: 0;
        width: 24px; height: 24px;
        border: none; background: none;
        color: #9ca3af;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        border-radius: 4px;
        margin-top: -2px;
    }
    .chip-remove:hover { color: #374151; background: #f3f4f6; }
    .input-row {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 6px 6px 6px 12px;
    }
    .input-row textarea {
        flex: 1;
        border: 0; outline: none; resize: none;
        font-size: 14px;
        padding: 10px 0;
        min-height: 42px;
        max-height: 100px;
        background: transparent;
        color: #111827;
    }
    .input-row textarea::placeholder { color: #9ca3af; }
    .ai-text-body {
        font-size: 14px;
        color: #374151;
        line-height: 1.6;
    }
    .ai-text-body strong { color: #111827; }
    .refine-list { margin-top: 1rem; }
    .refine-link {
        color: #1a73e8;
        cursor: pointer;
        font-size: 13px;
        background: none; border: none; padding: 0;
        text-align: left;
    }
    .refine-link:hover { text-decoration: underline; color: #F57C00; }

    /* ── Text / market responses ── */
    .agent-send-btn {
        width: 40px; height: 40px;
        border-radius: 50%;
        background: #111827;
        color: #fff;
        border: none;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        cursor: pointer;
        transition: background .15s;
    }
    .agent-send-btn:hover { background: #F57C00; }
    .agent-send-btn:disabled { opacity: .4; cursor: not-allowed; }
    .attach-btn {
        color: #9ca3af;
        padding: 8px;
        flex-shrink: 0;
        cursor: default;
    }

    /* ── Typing indicator ── */
    .typing-dot { animation: blink 1.2s infinite; }
    .typing-dot:nth-child(2) { animation-delay: .2s; }
    .typing-dot:nth-child(3) { animation-delay: .4s; }
    @keyframes blink { 0%,80%,100%{opacity:.2} 40%{opacity:1} }

    .thought-box summary { list-style: none; font-size: 12px; color: #9ca3af; cursor: pointer; }
    .thought-box summary::-webkit-details-marker { display: none; }

    /* ── Contact supplier / Send inquiry modal (Alibaba style) ── */
    .inquiry-overlay {
        position: fixed; inset: 0; z-index: 60;
        background: rgba(0,0,0,.45);
        display: flex; align-items: center; justify-content: center;
        padding: 16px;
    }
    .inquiry-overlay.hidden { display: none; }
    .inquiry-panel {
        width: 100%; max-width: 640px;
        max-height: calc(100vh - 32px);
        overflow-y: auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 20px 60px rgba(0,0,0,.18);
        padding: 28px 32px 32px;
    }
    .inquiry-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 4px;
    }
    .inquiry-supplier {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f3f4f6;
    }
    .inquiry-product {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    .inquiry-product-img {
        width: 72px; height: 72px;
        border-radius: 8px;
        object-fit: contain;
        background: #f3f4f6;
        flex-shrink: 0;
        padding: 4px;
    }
    .inquiry-product-name {
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        line-height: 1.4;
        margin-bottom: 10px;
    }
    .inquiry-qty-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #374151;
    }
    .inquiry-qty-row input {
        width: 64px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 6px 8px;
        font-size: 13px;
        text-align: center;
    }
    .inquiry-qty-row select {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 6px 8px;
        font-size: 13px;
        background: #fff;
    }
    .inquiry-attrs {
        margin-bottom: 20px;
        border: 1px solid #f3f4f6;
        border-radius: 8px;
        overflow: hidden;
    }
    .inquiry-attrs summary {
        list-style: none;
        padding: 12px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        cursor: pointer;
        background: #fafafa;
    }
    .inquiry-attrs summary::-webkit-details-marker { display: none; }
    .inquiry-attrs-body {
        padding: 14px;
        display: grid;
        gap: 12px;
    }
    .inquiry-attr label {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .inquiry-attr select {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 8px 10px;
        font-size: 13px;
        background: #fff;
    }
    .inquiry-field label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 6px;
    }
    .inquiry-field label .req { color: #ef4444; }
    .inquiry-field .hint {
        font-size: 12px;
        color: #9ca3af;
        margin-bottom: 8px;
        line-height: 1.4;
    }
    .inquiry-field textarea {
        width: 100%;
        min-height: 120px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 12px;
        font-size: 13px;
        resize: vertical;
        outline: none;
        transition: border-color .15s;
    }
    .inquiry-field textarea::placeholder { color: #9ca3af; }
    .inquiry-field textarea.error { border-color: #ef4444; box-shadow: 0 0 0 1px #ef4444; }
    .inquiry-submit {
        display: block;
        width: 100%;
        margin-top: 24px;
        padding: 14px;
        background: #F57C00;
        color: #fff;
        font-size: 15px;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background .15s;
    }
    .inquiry-submit:hover { background: #E65100; }
    .inquiry-close {
        position: absolute;
        top: 12px; right: 12px;
        width: 32px; height: 32px;
        border: none; background: none;
        color: #9ca3af;
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
        border-radius: 6px;
    }
    .inquiry-close:hover { color: #374151; background: #f3f4f6; }
    .inquiry-panel-wrap { position: relative; width: 100%; max-width: 640px; }
    .inquiry-success {
        text-align: center;
        padding: 32px 16px;
    }
    .inquiry-success p { font-size: 14px; color: #374151; margin-top: 8px; }
</style>
@endpush

@section('content')
<div class="agent-shell flex flex-col" id="agent-app"
     data-send-url="{{ route('admin.agent.send') }}"
     data-cart-url="{{ route('admin.agent.cart') }}"
     data-cart-page-url="{{ route('cart.index') }}"
     data-checkout-page-url="{{ route('checkout.index') }}"
     data-reset-url="{{ route('admin.agent.reset') }}"
     data-csrf="{{ csrf_token() }}">

    <div class="flex items-center justify-between mb-3 shrink-0 px-1">
        <div class="flex items-center gap-3">
            <span class="text-lg font-bold text-gray-900">AI Mode</span>
            <span class="text-xs text-gray-400 hidden sm:inline">Products · Market · ShipNest catalog</span>
        </div>
        <button type="button" id="agent-reset-btn"
            class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
            New chat
        </button>
    </div>

    <div class="flex-1 flex flex-col overflow-hidden rounded-2xl bg-white border border-gray-200">
        <div id="agent-feed" class="agent-feed flex-1 overflow-y-auto px-4 lg:px-10 py-8">
            @if($initialMessages->isEmpty())
                <div id="agent-welcome" class="max-w-xl mx-auto text-center py-16">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">What are you looking for?</h2>
                    <p class="text-sm text-gray-500 mb-8">Ask anything — product search, market trends, or general questions</p>
                    <div class="flex flex-wrap justify-center gap-2" id="welcome-chips">
                        @foreach(['What is the capital of France?', 'trending product ki?', 'watch', 'earbuds'] as $chip)
                            <button type="button" class="follow-chip text-sm px-4 py-2 rounded-full border border-gray-200 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50 transition"
                                data-query="{{ $chip }}">{{ $chip }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="shrink-0 px-4 lg:px-10 pb-5 pt-2 border-t border-gray-100">
            <div class="agent-input-outer">
                <div class="agent-input-wrap" id="agent-input-wrap">
                    <div id="input-product-chip" class="input-product-chip hidden"></div>
                    <div class="input-row">
                        <span class="attach-btn" title="Attach">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </span>
                        <textarea id="agent-input" rows="1" placeholder="ask follow-up..."></textarea>
                        <button type="button" id="agent-send-btn" class="agent-send-btn" title="Send">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <form id="agent-form" class="hidden"></form>
        </div>
    </div>
</div>

<div id="inquiry-modal" class="inquiry-overlay hidden" aria-hidden="true">
    <div class="inquiry-panel-wrap">
        <button type="button" class="inquiry-close" id="inquiry-close" title="Close">×</button>
        <div class="inquiry-panel" id="inquiry-panel">
            <h2 class="inquiry-title">Contact supplier</h2>
            <p class="inquiry-supplier" id="inquiry-supplier"></p>
            <div class="inquiry-product">
                <img id="inquiry-img" class="inquiry-product-img" src="" alt="">
                <div>
                    <p class="inquiry-product-name" id="inquiry-name"></p>
                    <div class="inquiry-qty-row">
                        <span>Quantity:</span>
                        <input type="number" id="inquiry-qty" value="1" min="1" max="99999">
                        <select id="inquiry-unit">
                            <option value="Piece/s">Piece/s</option>
                            <option value="Bag/s">Bag/s</option>
                            <option value="Set/s">Set/s</option>
                            <option value="Pair/s">Pair/s</option>
                        </select>
                    </div>
                </div>
            </div>
            <details class="inquiry-attrs" id="inquiry-attrs">
                <summary>▸ Product attributes</summary>
                <div class="inquiry-attrs-body">
                    <div class="inquiry-attr">
                        <label for="inquiry-attr-1">Case Material</label>
                        <select id="inquiry-attr-1"><option value="">Please select</option><option>Stainless Steel</option><option>Alloy</option><option>Leather</option></select>
                    </div>
                    <div class="inquiry-attr">
                        <label for="inquiry-attr-2">Feature</label>
                        <select id="inquiry-attr-2"><option value="">Please select</option><option>Water Resistant</option><option>Chronograph</option><option>Date Display</option></select>
                    </div>
                    <div class="inquiry-attr">
                        <label for="inquiry-attr-3">Movement Brand</label>
                        <select id="inquiry-attr-3"><option value="">Please select</option><option>Quartz</option><option>Automatic</option><option>Mechanical</option></select>
                    </div>
                </div>
            </details>
            <div class="inquiry-field">
                <label><span class="req">*</span> Detailed requirements:</label>
                <p class="hint">Enter product details such as color, size, materials etc. and other specification requirements to receive an accurate quote.</p>
                <textarea id="inquiry-requirements" placeholder="Please type in"></textarea>
            </div>
            <button type="button" class="inquiry-submit" id="inquiry-submit">Send inquiry now</button>
        </div>
    </div>
</div>

<script>window.__AGENT_INITIAL__ = @json($initialMessages);</script>
@endsection

@push('scripts')
<script>
(function () {
    const app = document.getElementById('agent-app');
    const feed = document.getElementById('agent-feed');
    const form = document.getElementById('agent-form');
    const input = document.getElementById('agent-input');
    const sendBtn = document.getElementById('agent-send-btn');
    const resetBtn = document.getElementById('agent-reset-btn');
    const welcome = document.getElementById('agent-welcome');
    const inputWrap = document.getElementById('agent-input-wrap');
    const inputChip = document.getElementById('input-product-chip');
    const sendUrl = app.dataset.sendUrl;
    const agentCartUrl = app.dataset.cartUrl;
    const cartPageUrl = app.dataset.cartPageUrl;
    const checkoutPageUrl = app.dataset.checkoutPageUrl;
    const resetUrl = app.dataset.resetUrl;
    const csrf = app.dataset.csrf;
    let turnId = 0;
    let selectedProduct = null;
    let lastPlatformProducts = [];

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function placeholderImg(name) {
        return 'https://placehold.co/400x400/f3f4f6/6b7280/png?text=' + encodeURIComponent((name || 'Product').substring(0, 16));
    }

    function capitalize(s) {
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    function matchLabel(p) {
        if (p.match_label) return p.match_label;
        if (p.section === 'trending') return 'Trending on ShipNest';
        if (p.source === 'platform') return 'Available on ShipNest';
        const met = p.requirements_met ?? 1;
        const total = p.requirements_total ?? 1;
        return `Matches all ${met}/${total} requirements`;
    }

    function renderTrendingList(products) {
        const items = products.map((p, i) => {
            const price = p.estimated_price || p.price_label
                ? `<span class="trending-price">${esc(p.estimated_price || p.price_label)}</span>`
                : '';
            const score = p.trend_score != null
                ? `<span class="text-xs text-gray-400 ml-1">${Math.round(p.trend_score * 100)}%</span>`
                : '';
            const reason = p.reason
                ? `<p class="text-xs text-gray-500 mt-0.5 ml-6">${esc(p.reason)}</p>`
                : '';
            const link = p.external_url
                ? `<a href="${esc(p.external_url)}" target="_blank" rel="noopener" class="trending-link text-xs text-[#F57C00] ml-2">source ↗</a>`
                : '';
            return `<li class="trending-item">
                <span class="trending-num">${i + 1}.</span>
                <span class="trending-name">${esc(p.product_name || p.name)}</span>
                ${score}${price}${link}
                ${reason}
            </li>`;
        }).join('');

        return `<ol class="trending-list space-y-2 my-4">${items}</ol>`;
    }

    function renderProductCard(p, idx, tid) {
        const imgUrl = p.image || placeholderImg(p.name);
        const price = p.price_label ? `<p class="ai-card-price">${esc(p.price_label)}</p>` : '';
        const productId = p.id || p.product_id;
        const cartBtn = productId
            ? `<button type="button" class="ai-card-cart" data-card-cart data-product-id="${productId}">Add to cart</button>`
            : '';
        return `<div class="ai-card" data-card-idx="${idx}" data-turn="${tid}">
            <div class="ai-card-img-wrap">
                <img src="${esc(imgUrl)}" alt="${esc(p.name)}" loading="lazy" data-card-img
                    onerror="this.onerror=null;this.src='${placeholderImg(p.name)}'">
                <button type="button" class="ai-card-lens" data-card-search aria-label="View product" title="View product">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </button>
                <button type="button" class="ai-card-select" data-card-select>Select</button>
            </div>
            <p class="ai-match-line">${esc(matchLabel(p))}</p>
            <p class="ai-card-title">${esc(p.name)}</p>
            ${price}
            ${cartBtn}
        </div>`;
    }

    function renderInputChip(p) {
        const imgUrl = p.image || placeholderImg(p.name);
        const viewUrl = p.url || '#';
        const title = p.name.length > 42 ? p.name.substring(0, 42) + '...' : p.name;

        inputChip.innerHTML = `
            <img class="chip-thumb" src="${esc(imgUrl)}" alt=""
                onerror="this.onerror=null;this.src='${placeholderImg(p.name)}'">
            <div class="chip-body">
                <p class="chip-title">${esc(title)}</p>
                <div class="chip-actions">
                    <a href="${esc(viewUrl)}" class="chip-action" target="_blank" rel="noopener">View product →</a>
                    ${(p.id || p.product_id) ? '<button type="button" class="chip-action" data-chip-cart>Add to cart →</button>' : ''}
                    <button type="button" class="chip-action" data-send-inquiry>Send inquiry →</button>
                </div>
            </div>
            <button type="button" class="chip-remove" data-chip-remove title="Remove">×</button>`;
        inputChip.classList.remove('hidden');
        inputWrap.classList.add('has-selection');
        inputChip.querySelector('[data-chip-remove]')?.addEventListener('click', clearInputChip);
        inputChip.querySelector('[data-chip-cart]')?.addEventListener('click', () => addProductToCart(p));
        inputChip.querySelector('[data-send-inquiry]')?.addEventListener('click', () => openInquiryModal(p));
        input.focus();
    }

    async function addProductToCart(product, btn) {
        const productId = product?.id || product?.product_id;
        if (!productId) return;

        const name = product.name || 'this product';
        if (!confirm(`"${name}" cart-এ add করতে চান?`)) return;

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Adding...';
        }

        try {
            const res = await fetch(agentCartUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ product_id: productId, quantity: 1 }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Cart add failed');
            }
            if (btn) {
                btn.textContent = '✓ Added';
                setTimeout(() => { btn.textContent = 'Add to cart'; btn.disabled = false; }, 2000);
            }
        } catch (e) {
            alert(e.message || 'Cart-এ add করা যায়নি।');
            if (btn) {
                btn.disabled = false;
                btn.textContent = btn.classList?.contains('ai-card-cart') ? 'Add to cart' : 'Add to cart →';
            }
        }
    }

    const inquiryModal = document.getElementById('inquiry-modal');
    const inquiryPanel = document.getElementById('inquiry-panel');
    const inquiryClose = document.getElementById('inquiry-close');
    let inquiryProduct = null;

    function alibabaInquiryUrl(url) {
        if (!url || !/alibaba\.com/i.test(url)) return null;
        const patterns = [/_(\d{8,})\.html/i, /\/product\/(\d{8,})/i, /chkProductIds=(\d{8,})/i];
        for (const re of patterns) {
            const m = url.match(re);
            if (m) return `https://message.alibaba.com/msgsend/contact.htm?action=contact_action&chkProductIds=${m[1]}`;
        }
        return null;
    }

    function openInquiryModal(product) {
        const externalUrl = product.inquiry_url || alibabaInquiryUrl(product.url);
        if (externalUrl) {
            window.open(externalUrl, '_blank', 'noopener');
            return;
        }

        inquiryProduct = product;
        const supplier = product.supplier || product.merchant || product.site || 'Supplier';
        const panel = document.getElementById('inquiry-panel');
        if (panel?.querySelector('.inquiry-success')) {
            panel.innerHTML = panel.dataset.template;
            bindInquiryForm();
        }

        document.getElementById('inquiry-supplier').textContent = supplier;
        const img = document.getElementById('inquiry-img');
        img.src = product.image || placeholderImg(product.name);
        img.onerror = () => { img.src = placeholderImg(product.name); };
        document.getElementById('inquiry-name').textContent = product.name;
        document.getElementById('inquiry-qty').value = '1';
        document.getElementById('inquiry-unit').value = 'Piece/s';
        const req = document.getElementById('inquiry-requirements');
        req.value = '';
        req.classList.remove('error');
        document.getElementById('inquiry-attrs').open = false;
        ['inquiry-attr-1', 'inquiry-attr-2', 'inquiry-attr-3'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.selectedIndex = 0;
        });

        inquiryModal.classList.remove('hidden');
        inquiryModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setTimeout(() => req.focus(), 100);
    }

    function closeInquiryModal() {
        inquiryModal.classList.add('hidden');
        inquiryModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        inquiryProduct = null;
    }

    function bindInquiryForm() {
        document.getElementById('inquiry-submit')?.addEventListener('click', submitInquiry);
        document.getElementById('inquiry-requirements')?.addEventListener('input', e => {
            e.target.classList.remove('error');
        });
    }

    function submitInquiry() {
        const req = document.getElementById('inquiry-requirements');
        const text = (req?.value || '').trim();
        if (!text) {
            req?.classList.add('error');
            req?.focus();
            return;
        }

        const qty = document.getElementById('inquiry-qty')?.value || '1';
        const unit = document.getElementById('inquiry-unit')?.value || 'Piece/s';
        const supplier = inquiryProduct?.supplier || inquiryProduct?.merchant || inquiryProduct?.site || 'supplier';

        inquiryPanel.innerHTML = `
            <div class="inquiry-success">
                <svg class="w-12 h-12 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h2 class="inquiry-title mt-3">Inquiry sent</h2>
                <p>Your message to <strong>${esc(supplier)}</strong> has been recorded.<br>Quantity: ${esc(qty)} ${esc(unit)}</p>
            </div>`;

        setTimeout(() => {
            closeInquiryModal();
            inquiryPanel.innerHTML = inquiryPanel.dataset.template;
            bindInquiryForm();
        }, 2200);
    }

    inquiryClose?.addEventListener('click', closeInquiryModal);
    inquiryModal?.addEventListener('click', e => {
        if (e.target === inquiryModal) closeInquiryModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !inquiryModal?.classList.contains('hidden')) closeInquiryModal();
    });
    if (inquiryPanel) {
        inquiryPanel.dataset.template = inquiryPanel.innerHTML;
    }
    bindInquiryForm();

    function openProduct(product) {
        if (product?.url) {
            window.open(product.url, '_blank', 'noopener');
        }
    }

    function selectProduct(product, cardEl, allCards) {
        selectedProduct = product;
        allCards?.forEach(c => {
            c.classList.remove('selected');
            const btn = c.querySelector('[data-card-select]');
            if (btn) btn.textContent = 'Select';
        });
        cardEl?.classList.add('selected');
        const selectBtn = cardEl?.querySelector('[data-card-select]');
        if (selectBtn) selectBtn.textContent = '✓ Selected';
        renderInputChip(product);
    }

    function clearInputChip() {
        selectedProduct = null;
        inputChip.innerHTML = '';
        inputChip.classList.add('hidden');
        inputWrap.classList.remove('has-selection');
        document.querySelectorAll('.ai-card.selected').forEach(c => {
            c.classList.remove('selected');
            const btn = c.querySelector('[data-card-select]');
            if (btn) btn.textContent = 'Select';
        });
    }

    const PREVIEW_COUNT = 4;
    const sectionProductsStore = new Map();

    function renderCarouselHtml(products, tid, sectionKey) {
        const cards = products.map((p, i) => renderProductCard(p, i, `${tid}-${sectionKey}`)).join('');

        return `<div class="carousel-wrap" data-carousel-wrap data-section="${sectionKey}">
            <button type="button" class="carousel-nav prev hidden" data-carousel-prev aria-label="Previous">‹</button>
            <div class="product-carousel" data-carousel data-section="${sectionKey}">${cards}</div>
            <button type="button" class="carousel-nav next" data-carousel-next aria-label="Next">›</button>
        </div>`;
    }

    function renderProductSection(title, allProducts, previewCount, tid, sectionKey) {
        if (!allProducts?.length) return '';

        const preview = allProducts.slice(0, previewCount);
        const remaining = allProducts.length - preview.length;

        let html = `<div class="ai-product-section" data-product-section="${sectionKey}">`;
        html += `<h3 class="ai-subheading">${esc(title)}</h3>`;
        html += renderCarouselHtml(preview, tid, sectionKey);
        if (remaining > 0) {
            html += `<button type="button" class="see-more-btn" data-see-more data-section="${sectionKey}" data-turn="${tid}">
                See more (${remaining} more)
            </button>`;
        }
        html += `</div>`;
        return html;
    }

    function renderTurn(query, msg) {
        const tid = ++turnId;
        const q = query || msg.query || '';
        const hasProducts = msg.products?.length > 0;
        const hasTrending = msg.trending_products?.length > 0;

        let main = '';

        if (hasProducts && msg.type === 'trending') {
            main += `<h2 class="ai-heading">${esc(capitalize(q))}</h2>`;
            if (msg.summary) {
                main += `<p class="ai-text-body text-sm text-gray-600 mb-3">${msg.summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</p>`;
            }
            main += renderTrendingList(msg.products);
        } else if (hasProducts && msg.type === 'platform') {
            const previewCount = msg.products_preview_count || PREVIEW_COUNT;
            const searchAll = msg.products_all?.length ? msg.products_all : msg.products;
            const trendingAll = msg.trending_products_all?.length ? msg.trending_products_all : (msg.trending_products || []);
            const trendingPreview = msg.trending_preview_count || PREVIEW_COUNT;

            main += `<h2 class="ai-heading">${esc(capitalize(q))}</h2>`;
            if (msg.summary) {
                main += `<p class="ai-text-body text-sm text-gray-600 mb-4">${msg.summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</p>`;
            }
            main += renderProductSection(
                `Results on ShipNest (${msg.total_count || searchAll.length})`,
                searchAll,
                previewCount,
                tid,
                'search',
            );
            if (trendingAll.length > 0) {
                main += renderProductSection(
                    `Related trending on ShipNest (${msg.trending_total_count || trendingAll.length})`,
                    trendingAll,
                    trendingPreview,
                    tid,
                    'trending',
                );
            }
        } else if (hasProducts) {
            main += `<h2 class="ai-heading">${esc(capitalize(q))}</h2>`;
            main += renderCarouselHtml(msg.products, tid, 'search');
        }

        if (!hasProducts && (msg.content_html || msg.content)) {
            const html = msg.content_html || esc(msg.content).replace(/\n/g, '<br>');
            main += `<div class="ai-text-body prose prose-sm max-w-none">${html}</div>`;
        } else if (msg.summary && hasProducts && msg.type !== 'platform' && msg.type !== 'trending') {
            main += `<div class="ai-text-body mt-4">${msg.summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</div>`;
        }

        if (msg.thought_process?.length) {
            main += `<details class="thought-box mt-3">
                <summary>▸ Thought process</summary>
                <ol class="mt-1 space-y-0.5 list-decimal list-inside text-xs text-gray-400">${msg.thought_process.map(s => `<li>${esc(s)}</li>`).join('')}</ol>
            </details>`;
        }

        if (msg.follow_ups?.length) {
            main += `<div class="refine-list"><ul class="space-y-1.5">`;
            msg.follow_ups.forEach(fq => {
                main += `<li><button type="button" class="refine-link follow-chip" data-query="${esc(fq)}">${esc(fq)}</button></li>`;
            });
            main += `</ul></div>`;
        }

        if (msg.sources?.length) {
            main += `<details class="mt-3 text-xs text-gray-400"><summary class="cursor-pointer">Sources (${msg.sources.length})</summary><ul class="mt-1 space-y-0.5">`;
            msg.sources.slice(0, 6).forEach(s => {
                main += `<li><a href="${esc(s.url)}" target="_blank" class="hover:text-[#F57C00]">[${esc(s.site)}] ${esc(s.title)}</a></li>`;
            });
            main += `</ul></details>`;
        }

        return `<div class="ai-turn" data-turn-id="${tid}">
            <div class="ai-turn-grid">
                <div class="ai-query-side">${esc(q)}</div>
                <div class="ai-results">${main}</div>
            </div>
        </div>`;
    }

    function bindCardEvents(card, product, turn) {
        const allCards = turn.querySelectorAll('.ai-card');

        card.querySelector('[data-card-select]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            selectProduct(product, card, allCards);
        });

        card.querySelector('[data-card-search]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            openProduct(product);
        });

        card.querySelector('[data-card-img]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            openProduct(product);
        });

        card.querySelector('[data-card-cart]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            addProductToCart(product, e.currentTarget);
        });
    }

    function bindCarouselWrap(wrap, products, turn) {
        const carousel = wrap.querySelector('[data-carousel]');
        const prev = wrap.querySelector('[data-carousel-prev]');
        const next = wrap.querySelector('[data-carousel-next]');
        if (!carousel) return;

        const scrollAmt = 232;
        function updateNav() {
            if (!prev || !next) return;
            prev.classList.toggle('hidden', carousel.scrollLeft <= 4);
            next.classList.toggle('hidden', carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 4);
        }
        prev?.addEventListener('click', () => { carousel.scrollBy({ left: -scrollAmt, behavior: 'smooth' }); });
        next?.addEventListener('click', () => { carousel.scrollBy({ left: scrollAmt, behavior: 'smooth' }); });
        carousel.addEventListener('scroll', updateNav);
        setTimeout(updateNav, 100);

        const cards = wrap.querySelectorAll('.ai-card');
        cards.forEach((card, i) => {
            const product = products[i];
            if (!product) return;
            bindCardEvents(card, product, turn);
        });
    }

    function bindProductSections(turn, tid, msg) {
        const searchAll = msg.products_all?.length ? msg.products_all : (msg.products || []);
        const trendingAll = msg.trending_products_all?.length ? msg.trending_products_all : (msg.trending_products || []);

        if (searchAll.length) {
            sectionProductsStore.set(`${tid}-search`, searchAll);
            const searchWrap = turn.querySelector('[data-carousel-wrap][data-section="search"]');
            if (searchWrap) {
                bindCarouselWrap(searchWrap, searchAll.slice(0, msg.products_preview_count || PREVIEW_COUNT), turn);
            }
        }

        if (trendingAll.length) {
            sectionProductsStore.set(`${tid}-trending`, trendingAll);
            const trendingWrap = turn.querySelector('[data-carousel-wrap][data-section="trending"]');
            if (trendingWrap) {
                bindCarouselWrap(trendingWrap, trendingAll.slice(0, msg.trending_preview_count || PREVIEW_COUNT), turn);
            }
        }

        turn.querySelectorAll('[data-see-more]').forEach(btn => {
            btn.addEventListener('click', () => {
                const section = btn.dataset.section;
                const storeKey = `${tid}-${section}`;
                const all = sectionProductsStore.get(storeKey) || [];
                const previewCount = section === 'trending'
                    ? (msg.trending_preview_count || PREVIEW_COUNT)
                    : (msg.products_preview_count || PREVIEW_COUNT);
                const remaining = all.slice(previewCount);
                const carousel = turn.querySelector(`[data-carousel][data-section="${section}"]`);
                const wrap = turn.querySelector(`[data-carousel-wrap][data-section="${section}"]`);
                if (!carousel || !remaining.length) {
                    btn.remove();
                    return;
                }

                const startIdx = carousel.querySelectorAll('.ai-card').length;
                remaining.forEach((product, i) => {
                    const holder = document.createElement('div');
                    holder.innerHTML = renderProductCard(product, startIdx + i, `${tid}-${section}`);
                    const card = holder.firstElementChild;
                    carousel.appendChild(card);
                    bindCardEvents(card, product, turn);
                });

                btn.remove();
                const next = wrap?.querySelector('[data-carousel-next]');
                next?.classList.remove('hidden');
                carousel.dispatchEvent(new Event('scroll'));
            });
        });
    }

    function appendTurn(query, msg) {
        if (welcome) welcome.remove();
        const products = msg.products || [];
        const el = document.createElement('div');
        el.innerHTML = renderTurn(query, msg);
        const turn = el.firstElementChild;
        feed.appendChild(turn);
        const tid = turn.dataset.turnId;

        if (msg.type === 'trending') {
            clearInputChip();
        }
        if (msg.type === 'platform' && (products.length > 0 || msg.trending_products?.length > 0)) {
            const searchAll = msg.products_all?.length ? msg.products_all : products;
            const trendingAll = msg.trending_products_all?.length ? msg.trending_products_all : (msg.trending_products || []);
            lastPlatformProducts = [...searchAll, ...trendingAll];
            clearInputChip();
        }
        if (msg.type === 'platform') {
            bindProductSections(turn, tid, msg);
        } else if (msg.type !== 'trending' && products.length > 0) {
            const wrap = turn.querySelector('[data-carousel-wrap]');
            if (wrap) bindCarouselWrap(wrap, products, turn);
        }
        bindFollowChips(turn);
        if (msg.type === 'cart_success' && msg.cart_url) {
            clearInputChip();
        }
        scrollBottom();
    }

    function bindFollowChips(el) {
        el.querySelectorAll('.follow-chip').forEach(btn => {
            btn.addEventListener('click', () => {
                const q = btn.dataset.query;
                if (q && /^view cart$/i.test(q.trim())) {
                    window.open(cartPageUrl, '_blank', 'noopener');
                    return;
                }
                if (q && /^checkout$/i.test(q.trim())) {
                    window.open(checkoutPageUrl, '_blank', 'noopener');
                    return;
                }
                sendQuery(q);
            });
        });
    }

    function scrollBottom() { feed.scrollTop = feed.scrollHeight; }

    function showTyping(query) {
        const el = document.createElement('div');
        el.id = 'agent-typing';
        el.className = 'ai-turn';
        el.innerHTML = `<div class="ai-turn-grid">
            <div class="ai-query-side">${esc(query)}</div>
            <div class="flex items-center gap-2 text-gray-400 text-sm py-4">
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span>Searching...</span>
            </div>
        </div>`;
        feed.appendChild(el);
        scrollBottom();
    }

    function hideTyping() { document.getElementById('agent-typing')?.remove(); }

    async function sendQuery(text) {
        const typed = (text || input.value).trim();
        let message = typed;
        if (!message && selectedProduct) {
            message = `Tell me more about ${selectedProduct.name}`;
        }
        if (!message) return;

        const payload = { message };
        if (selectedProduct) {
            payload.selected_product = {
                id: selectedProduct.id || selectedProduct.product_id || null,
                product_id: selectedProduct.id || selectedProduct.product_id || null,
                name: selectedProduct.name,
                url: selectedProduct.url || null,
                price: selectedProduct.price_label || null,
            };
        }
        if (lastPlatformProducts.length > 0) {
            payload.context_products = lastPlatformProducts.map(p => ({
                id: p.id || p.product_id || null,
                product_id: p.id || p.product_id || null,
                name: p.name,
            }));
        }

        input.value = '';
        sendBtn.disabled = true;
        showTyping(message);

        try {
            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });
            hideTyping();
            if (!res.ok) throw new Error('fail');
            const data = await res.json();
            if (!data.query) data.query = message;
            if (!data.type && data.meta?.type) data.type = data.meta.type;
            if (!data.cart_url && data.meta?.cart_url) data.cart_url = data.meta.cart_url;
            if (!data.checkout_url && data.meta?.checkout_url) data.checkout_url = data.meta.checkout_url;
            appendTurn(message, data);
        } catch (e) {
            hideTyping();
            appendTurn(message, { summary: 'Request failed. Please try again.', type: 'error' });
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    form.addEventListener('submit', e => { e.preventDefault(); sendQuery(); });
    sendBtn.addEventListener('click', e => { e.preventDefault(); sendQuery(); });
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendQuery(); }
    });

    resetBtn.addEventListener('click', async () => {
        if (!confirm('Start new chat?')) return;
        clearInputChip();
        await fetch(resetUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        });
        location.reload();
    });

    document.querySelectorAll('#welcome-chips .follow-chip').forEach(btn => {
        btn.addEventListener('click', () => sendQuery(btn.dataset.query));
    });

    let prevUser = '';
  let pendingUser = '';
    (window.__AGENT_INITIAL__ || []).forEach(msg => {
        if (msg.role === 'user') {
            pendingUser = msg.content;
            prevUser = msg.content;
        }
        if (msg.role === 'assistant') {
            const q = msg.query || pendingUser || prevUser;
            appendTurn(q, msg);
            pendingUser = '';
        }
    });
    scrollBottom();
})();
</script>
@endpush
