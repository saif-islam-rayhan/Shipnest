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
        'draft_product' => $m->meta['draft_product'] ?? null,
        'product' => $m->meta['product'] ?? null,
    ])->values();
@endphp
@php
    use App\Support\AgentBranding;

    $agentName = AgentBranding::name();
    $agentLogoUrl = AgentBranding::logoUrl();
@endphp
@extends('layouts.admin')
@section('title', $agentName)
@section('page-title', $agentName)

@push('styles')
<style>
    /* ── Full-height layout (input always visible) ── */
    body:has(.agent-shell) .flex.min-h-screen > div:last-child {
        height: 100dvh;
        min-height: 0;
        overflow: hidden;
    }
    body:has(.agent-shell) main {
        flex: 1 1 0;
        min-height: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .agent-shell { flex: 1; min-height: 0; height: auto; }
    .agent-feed { scroll-behavior: smooth; min-height: 0; }

    /* ── Chat bubble layout ── */
    .chat-turn { margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 0.625rem; }
    .chat-row { display: flex; width: 100%; }
    .chat-row.user { justify-content: flex-end; }
    .chat-row.agent { justify-content: flex-start; }
    .chat-bubble {
        max-width: 78%;
        padding: 10px 14px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.5;
        word-break: break-word;
    }
    .chat-bubble.user {
        background: #F57C00;
        color: #fff;
        border-bottom-right-radius: 4px;
    }
    .chat-bubble.agent {
        background: #f3f4f6;
        color: #111827;
        border-bottom-left-radius: 4px;
    }
    .chat-bubble.agent.wide {
        max-width: 100%;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        padding: 14px 16px;
    }
    .chat-bubble.agent.typing {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #6b7280;
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
        border: 1px solid #fdba74;
        border-radius: 20px;
        padding: 0;
        display: flex;
        flex-direction: column;
        box-shadow: 0 0 0 3px rgba(245,124,0,.08), 0 1px 4px rgba(0,0,0,.04);
        overflow: hidden;
        transition: border-color .15s, box-shadow .15s;
    }
    .agent-mode-chips {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 10px 2px 0;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }
    .agent-mode-chip {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #374151;
        font-size: 12px;
        font-weight: 500;
        line-height: 1.2;
        padding: 7px 12px;
        border-radius: 999px;
        cursor: pointer;
        white-space: nowrap;
        transition: border-color .15s, background .15s, color .15s;
    }
    .agent-mode-chip:hover { border-color: #d1d5db; background: #f9fafb; }
    .agent-mode-chip.is-active {
        border-color: #F57C00;
        background: #fff7ed;
        color: #c2410c;
    }
    .agent-mode-chip .mode-spark { color: #eab308; font-size: 11px; }
    .studio-design-img {
        display: block;
        width: 100%;
        max-width: 420px;
        max-height: 420px;
        object-fit: cover;
        border-radius: 12px;
        margin-top: 10px;
        border: 1px solid #e5e7eb;
        background: #fff;
    }
    .studio-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .studio-actions a {
        display: inline-flex;
        align-items: center;
        font-size: 12px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 8px;
        text-decoration: none;
    }
    .studio-actions .btn-primary { background: #F57C00; color: #fff; }
    .studio-actions .btn-ghost { background: #fff; color: #374151; border: 1px solid #e5e7eb; }
    .studio-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 8px;
        margin-top: 12px;
    }
    .studio-product-card {
        display: flex;
        gap: 8px;
        padding: 8px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
    }
    .studio-product-card img {
        width: 48px; height: 48px; border-radius: 6px; object-fit: cover; background: #f3f4f6; flex-shrink: 0;
    }
    .studio-product-card .sp-name { font-size: 12px; font-weight: 600; color: #111827; line-height: 1.3; }
    .studio-product-card .sp-price { font-size: 11px; color: #F57C00; margin-top: 2px; }
    .studio-product-card .sp-meta { font-size: 11px; color: #6b7280; margin-top: 2px; }
    .studio-product-card a { font-size: 11px; color: #1A237E; font-weight: 500; margin-top: 4px; display: inline-block; }
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
    .agent-mic-btn {
        width: 40px; height: 40px;
        border-radius: 10px;
        color: #9ca3af;
        border: none;
        background: none;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        cursor: pointer;
        transition: color .15s, background .15s;
    }
    .agent-mic-btn:hover { color: #F57C00; background: #fff7ed; }
    .agent-mic-btn.is-listening {
        color: #fff; background: #ef4444;
        animation: agent-mic-pulse 1.2s ease-in-out infinite;
    }
    .agent-mic-btn:disabled { opacity: .4; cursor: not-allowed; }
    @keyframes agent-mic-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, .45); }
        50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
    }
    .attach-btn {
        color: #9ca3af;
        padding: 8px;
        flex-shrink: 0;
        cursor: pointer;
        border: none;
        background: none;
        border-radius: 8px;
        transition: color .15s, background .15s;
    }
    .attach-btn:hover { color: #F57C00; background: #fff7ed; }
    .image-preview-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 12px 0;
    }
    .image-preview-chip {
        position: relative;
        width: 56px;
        height: 56px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }
    .image-preview-chip img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .image-preview-chip button {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 18px;
        height: 18px;
        border: none;
        border-radius: 999px;
        background: rgba(0,0,0,.55);
        color: #fff;
        font-size: 12px;
        line-height: 1;
        cursor: pointer;
    }

    .user-chat-images {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }
    .user-chat-images img {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,.35);
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
     data-ai-design-url="{{ route('admin.ai-design.generate') }}"
     data-create-product-url="{{ route('admin.products.create') }}"
     data-cart-url="{{ route('admin.agent.cart') }}"
     data-cart-page-url="{{ route('cart.index') }}"
     data-checkout-page-url="{{ route('checkout.index') }}"
     data-reset-url="{{ route('admin.agent.reset') }}"
     data-csrf="{{ csrf_token() }}"
     data-admin-mode="1">

    <div class="flex items-center justify-between mb-3 shrink-0 px-1">
        <div class="flex items-center gap-3 min-w-0">
            @if($agentLogoUrl)
                <img src="{{ $agentLogoUrl }}" alt="" class="h-9 w-9 rounded-full object-cover ring-1 ring-gray-200 shrink-0">
            @endif
            <span class="text-lg font-bold text-gray-900 truncate">{{ $agentName }}</span>
            <span class="text-xs text-gray-400 hidden sm:inline">AI Mode · Design · Search · Trends</span>
        </div>
        <button type="button" id="agent-reset-btn"
            class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
            New chat
        </button>
    </div>

    <div class="flex-1 flex flex-col overflow-hidden rounded-2xl bg-white border border-gray-200">
        <div id="agent-feed" class="agent-feed flex-1 overflow-y-auto px-4 lg:px-10 py-8">
            <template id="agent-welcome-template">
                <div id="agent-welcome" class="max-w-xl mx-auto text-center py-16">
                    @if($agentLogoUrl)
                        <img src="{{ $agentLogoUrl }}" alt="{{ $agentName }}" class="h-14 w-14 rounded-full object-cover mx-auto mb-4 ring-1 ring-gray-200">
                    @endif
                    <h2 class="text-xl font-bold text-gray-900 mb-2">{{ $agentName }}</h2>
                    <p class="text-sm text-gray-500 mb-8">Design, search, bestsellers, market potential & trends — admin panel</p>
                    <div class="flex flex-wrap justify-center gap-2" id="welcome-chips">
                        @foreach([
                            ['mode' => 'design', 'label' => 'Design with AI', 'prompt' => 'Design a ceramic Labubu mug with soft pastel colors'],
                            ['mode' => 'search', 'label' => 'Product search', 'prompt' => 'smart watch'],
                            ['mode' => 'bestsellers', 'label' => 'Analyze bestsellers', 'prompt' => ''],
                            ['mode' => 'market', 'label' => 'Evaluate market potential', 'prompt' => 'wireless earbuds for students'],
                            ['mode' => 'trends', 'label' => 'Discover trends', 'prompt' => ''],
                        ] as $chip)
                            <button type="button" class="follow-chip mode-welcome-chip text-sm px-4 py-2 rounded-full border border-gray-200 bg-white text-gray-700 hover:border-orange-300 hover:bg-orange-50 transition"
                                data-mode="{{ $chip['mode'] }}" data-prompt="{{ $chip['prompt'] }}">{{ $chip['label'] }}</button>
                        @endforeach
                    </div>
                </div>
            </template>
            @if($initialMessages->isEmpty())
                <div id="agent-welcome" class="max-w-xl mx-auto text-center py-16">
                    @if($agentLogoUrl)
                        <img src="{{ $agentLogoUrl }}" alt="{{ $agentName }}" class="h-14 w-14 rounded-full object-cover mx-auto mb-4 ring-1 ring-gray-200">
                    @endif
                    <h2 class="text-xl font-bold text-gray-900 mb-2">{{ $agentName }}</h2>
                    <p class="text-sm text-gray-500 mb-8">Design, search, bestsellers, market potential & trends — admin panel</p>
                    <div class="flex flex-wrap justify-center gap-2" id="welcome-chips">
                        @foreach([
                            ['mode' => 'design', 'label' => 'Design with AI', 'prompt' => 'Design a ceramic Labubu mug with soft pastel colors'],
                            ['mode' => 'search', 'label' => 'Product search', 'prompt' => 'smart watch'],
                            ['mode' => 'bestsellers', 'label' => 'Analyze bestsellers', 'prompt' => ''],
                            ['mode' => 'market', 'label' => 'Evaluate market potential', 'prompt' => 'wireless earbuds for students'],
                            ['mode' => 'trends', 'label' => 'Discover trends', 'prompt' => ''],
                        ] as $chip)
                            <button type="button" class="follow-chip mode-welcome-chip text-sm px-4 py-2 rounded-full border border-gray-200 bg-white text-gray-700 hover:border-orange-300 hover:bg-orange-50 transition"
                                data-mode="{{ $chip['mode'] }}" data-prompt="{{ $chip['prompt'] }}">{{ $chip['label'] }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="agent-input-bar shrink-0 px-4 lg:px-10 pb-5 pt-2 border-t border-gray-100 bg-white">
            <div class="agent-input-outer">
                <div class="agent-input-wrap" id="agent-input-wrap">
                    <div id="input-product-chip" class="input-product-chip hidden"></div>
                    <div id="image-preview-row" class="image-preview-row hidden"></div>
                    <div class="input-row">
                        <button type="button" class="attach-btn" id="agent-attach-btn" title="Upload product image">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </button>
                        <input type="file" id="agent-image-input" accept="image/jpeg,image/png,image/jpg,image/webp" multiple hidden>
                        <textarea id="agent-input" rows="1" placeholder="Describe your needs..."></textarea>
                        <button type="button" id="agent-mic-btn" class="agent-mic-btn" title="Voice input" aria-label="Voice input">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>
                            </svg>
                        </button>
                        <button type="button" id="agent-send-btn" class="agent-send-btn" title="Send">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="agent-mode-chips" id="agent-mode-chips" role="tablist" aria-label="AI categories">
                    <button type="button" class="agent-mode-chip is-active" data-mode="design" role="tab" aria-selected="true"><span class="mode-spark">✦</span> Design with AI</button>
                    <button type="button" class="agent-mode-chip" data-mode="search" role="tab">Product search</button>
                    <button type="button" class="agent-mode-chip" data-mode="bestsellers" role="tab">Analyze bestsellers</button>
                    <button type="button" class="agent-mode-chip" data-mode="market" role="tab">Evaluate market potential</button>
                    <button type="button" class="agent-mode-chip" data-mode="trends" role="tab">Discover trends</button>
                    <button type="button" class="agent-mode-chip" data-mode="chat" role="tab">Chat / create</button>
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
    let welcome = document.getElementById('agent-welcome');
    const welcomeTemplateEl = document.getElementById('agent-welcome-template');
    const welcomeTemplate = welcome?.outerHTML || welcomeTemplateEl?.innerHTML || '';
    const inputWrap = document.getElementById('agent-input-wrap');
    const inputChip = document.getElementById('input-product-chip');
    const imageInput = document.getElementById('agent-image-input');
    const attachBtn = document.getElementById('agent-attach-btn');
    const imagePreviewRow = document.getElementById('image-preview-row');
    const sendUrl = app.dataset.sendUrl;
    const aiDesignUrl = app.dataset.aiDesignUrl || '';
    const createProductUrl = app.dataset.createProductUrl || '';
    const agentCartUrl = app.dataset.cartUrl;
    const cartPageUrl = app.dataset.cartPageUrl;
    const checkoutPageUrl = app.dataset.checkoutPageUrl;
    const resetUrl = app.dataset.resetUrl;
    const csrf = app.dataset.csrf;
    const adminMode = app.dataset.adminMode === '1';
    let turnId = 0;
    let selectedProduct = null;
    let lastPlatformProducts = [];
    let pendingImages = [];
    let lastCreatedProductId = null;
    let studioMode = 'design';

    const STUDIO_MODES = new Set(['design', 'search', 'bestsellers', 'market', 'trends']);
    const MODE_PLACEHOLDERS = {
        design: 'Describe your needs… e.g. Design Labubu mug',
        search: 'Search products… e.g. smart watch under 2000',
        bestsellers: 'Optional filter… or just send to see bestsellers',
        market: 'Evaluate a niche… e.g. wireless earbuds for students',
        trends: 'Discover trends… e.g. fashion june 2026',
        chat: 'ask follow-up, create product, or tap mic...',
    };
    const MODE_TYPING = {
        design: 'Designing your product…',
        search: 'Searching ShipNest catalog…',
        bestsellers: 'Analyzing bestsellers…',
        market: 'Evaluating market potential…',
        trends: 'Discovering trends…',
    };

    function setStudioMode(mode) {
        studioMode = mode || 'design';
        document.querySelectorAll('#agent-mode-chips .agent-mode-chip').forEach((btn) => {
            const active = btn.dataset.mode === studioMode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (input && !document.getElementById('agent-mic-btn')?.classList.contains('is-listening')) {
            input.placeholder = MODE_PLACEHOLDERS[studioMode] || MODE_PLACEHOLDERS.chat;
        }
    }

    document.getElementById('agent-mode-chips')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.agent-mode-chip');
        if (!btn) return;
        setStudioMode(btn.dataset.mode);
        input?.focus();
    });
    setStudioMode('design');

    function renderImagePreviews() {
        if (!imagePreviewRow) return;
        if (!pendingImages.length) {
            imagePreviewRow.classList.add('hidden');
            imagePreviewRow.innerHTML = '';
            return;
        }
        imagePreviewRow.classList.remove('hidden');
        imagePreviewRow.innerHTML = pendingImages.map((file, idx) => `
            <div class="image-preview-chip">
                <img src="${URL.createObjectURL(file)}" alt="${esc(file.name)}">
                <button type="button" data-remove-image="${idx}" title="Remove">×</button>
            </div>
        `).join('');
        imagePreviewRow.querySelectorAll('[data-remove-image]').forEach(btn => {
            btn.addEventListener('click', () => {
                pendingImages.splice(Number(btn.dataset.removeImage), 1);
                renderImagePreviews();
            });
        });
    }

    attachBtn?.addEventListener('click', () => imageInput?.click());
    imageInput?.addEventListener('change', () => {
        const files = Array.from(imageInput.files || []);
        if (!files.length) return;
        pendingImages = pendingImages.concat(files).slice(0, 5);
        imageInput.value = '';
        renderImagePreviews();
    });

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function renderUserBubbleContent(message, imageFiles) {
        const files = imageFiles || [];
        let html = '';
        if (files.length) {
            html += '<div class="user-chat-images">';
            files.forEach((file) => {
                html += `<img src="${URL.createObjectURL(file)}" alt="${esc(file.name)}">`;
            });
            html += '</div>';
        }
        const text = (message || '').trim();
        if (text) {
            html += esc(text);
        } else if (files.length) {
            html += `📷 ${files.length} image(s)`;
        }
        return html;
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
        const cartBtn = (!adminMode && productId)
            ? `<button type="button" class="ai-card-cart" data-card-cart data-product-id="${productId}">Add to cart</button>`
            : (productId && p.admin_url)
                ? `<a href="${esc(p.admin_url)}" class="ai-card-cart text-center no-underline" style="display:block;line-height:1.4">Edit in Admin</a>`
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
                    ${(!adminMode && (p.id || p.product_id)) ? '<button type="button" class="chip-action" data-chip-cart>Add to cart →</button>' : ''}
                    ${adminMode && p.admin_url ? `<a href="${esc(p.admin_url)}" class="chip-action">Edit in Admin →</a>` : ''}
                    ${!adminMode ? '<button type="button" class="chip-action" data-send-inquiry>Send inquiry →</button>' : ''}
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

    function renderAgentTextBlock(msg, className = 'ai-text-body prose prose-sm max-w-none mb-4') {
        if (!msg.greeting && !msg.show_content) {
            return '';
        }
        if (!msg.content_html && !msg.content) {
            return '';
        }

        const html = msg.content_html || esc(msg.content).replace(/\n/g, '<br>');
        return `<div class="${className}">${html}</div>`;
    }

    function isPlatformTrendingCatalog(msg, trendingAll) {
        return msg.type === 'platform'
            && !(trendingAll?.length)
            && (msg.catalog_mode === 'trending' || msg.query === 'trending product');
    }

    function renderTurn(query, msg, userImages) {
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
        } else if (msg.type === 'platform' && (hasProducts || hasTrending)) {
            const previewCount = msg.products_preview_count || PREVIEW_COUNT;
            const searchAll = msg.products_all?.length ? msg.products_all : (msg.products || []);
            const trendingAll = msg.trending_products_all?.length ? msg.trending_products_all : (msg.trending_products || []);
            const trendingPreview = msg.trending_preview_count || PREVIEW_COUNT;
            const isTrendingCatalog = isPlatformTrendingCatalog(msg, trendingAll);

            if (msg.greeting || msg.show_content || msg.catalog_mode === 'image_search') {
                main += renderAgentTextBlock(msg);
            } else if (!isTrendingCatalog && (hasProducts || hasTrending)) {
                main += `<h2 class="ai-heading">${esc(capitalize(q))}</h2>`;
            }

            if (msg.summary && (!msg.greeting || !msg.show_content) && msg.catalog_mode !== 'image_search') {
                main += `<p class="ai-text-body text-sm text-gray-600 mb-4">${msg.summary.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</p>`;
            }

            if (hasProducts) {
                const searchTitle = isTrendingCatalog
                    ? `🔥 Trending Products (${msg.total_count || searchAll.length})`
                    : `Results on ShipNest (${msg.total_count || searchAll.length})`;

                main += renderProductSection(
                    searchTitle,
                    searchAll,
                    previewCount,
                    tid,
                    'search',
                );
            }

            if (trendingAll.length > 0) {
                const trendingTitle = hasProducts
                    ? `Related trending on ShipNest (${msg.trending_total_count || trendingAll.length})`
                    : (msg.catalog_mode === 'image_search'
                        ? `Similar products (${msg.trending_total_count || trendingAll.length})`
                        : `Related products (${msg.trending_total_count || trendingAll.length})`);

                main += renderProductSection(
                    trendingTitle,
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

        if (!hasProducts && !hasTrending && (msg.content_html || msg.content)) {
            const html = msg.content_html || esc(msg.content).replace(/\n/g, '<br>');
            main += `<div class="ai-text-body prose prose-sm max-w-none">${html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</div>`;
            if (msg.type === 'product_create_success' && msg.product?.admin_url) {
                main += `<a href="${esc(msg.product.admin_url)}" class="inline-flex mt-4 px-4 py-2 rounded-lg bg-[#F57C00] text-white text-sm font-semibold hover:bg-[#E65100]">Open in Admin →</a>`;
            }
            if (msg.type === 'product_image_updated' && msg.product?.admin_url) {
                main += `<a href="${esc(msg.product.admin_url)}" class="inline-flex mt-4 px-4 py-2 rounded-lg bg-[#F57C00] text-white text-sm font-semibold hover:bg-[#E65100]">Open in Admin →</a>`;
            }
            if (msg.type === 'cart_contents') {
                if (msg.checkout_url) {
                    main += `<a href="${esc(msg.checkout_url)}" class="inline-flex mt-4 px-4 py-2 rounded-lg bg-[#F57C00] text-white text-sm font-semibold hover:bg-[#E65100]">Checkout →</a>`;
                }
                if (msg.cart_url) {
                    main += `<a href="${esc(msg.cart_url)}" class="inline-flex mt-4 ml-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-800 text-sm font-semibold hover:bg-gray-50">Open Cart →</a>`;
                }
            }
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

        const agentWide = hasProducts || hasTrending;
        const bubbleClass = agentWide ? 'chat-bubble agent wide' : 'chat-bubble agent';

        return `<div class="chat-turn" data-turn-id="${tid}">
            <div class="chat-row user">
                <div class="chat-bubble user">${renderUserBubbleContent(q, userImages)}</div>
            </div>
            <div class="chat-row agent">
                <div class="${bubbleClass}">${main}</div>
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

    function appendTurn(query, msg, userImages) {
        if (welcome) welcome.remove();
        const products = msg.products || [];
        const el = document.createElement('div');
        el.innerHTML = renderTurn(query, msg, userImages);
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
                if (q && /^checkout$/i.test(q.trim())) {
                    window.open(checkoutPageUrl, '_blank', 'noopener');
                    return;
                }
                if (btn.dataset.mode) {
                    setStudioMode(btn.dataset.mode);
                    const prompt = btn.dataset.prompt || '';
                    if (prompt || ['bestsellers', 'trends'].includes(btn.dataset.mode)) {
                        sendQuery(prompt);
                    }
                    return;
                }
                setStudioMode('chat');
                sendQuery(q);
            });
        });
    }

    function scrollBottom() { feed.scrollTop = feed.scrollHeight; }

    function showTyping(query, userImages, label) {
        const typingLabel = label
            || ((userImages?.length || String(query).startsWith('📷'))
                ? 'Analyzing image...'
                : 'Searching...');
        const el = document.createElement('div');
        el.id = 'agent-typing';
        el.className = 'chat-turn';
        el.innerHTML = `<div class="chat-row user">
            <div class="chat-bubble user">${renderUserBubbleContent(query, userImages)}</div>
        </div>
        <div class="chat-row agent">
            <div class="chat-bubble agent typing">
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span class="typing-dot w-2 h-2 bg-gray-400 rounded-full"></span>
                <span>${typingLabel}</span>
            </div>
        </div>`;
        feed.appendChild(el);
        scrollBottom();
    }

    function hideTyping() { document.getElementById('agent-typing')?.remove(); }

    function appendStudioTurn(query, data) {
        document.getElementById('agent-welcome')?.remove();
        const tid = ++turnId;
        const text = esc(data.description || 'Done.').replace(/\n/g, '<br>');
        let body = `<div class="ai-text-body prose prose-sm max-w-none mb-2">${text}</div>`;

        if (data.image_url) {
            const createUrl = createProductUrl
                ? `${createProductUrl}${createProductUrl.includes('?') ? '&' : '?'}design_image=${encodeURIComponent(data.image_url)}`
                : data.image_url;
            body += `<img class="studio-design-img" src="${esc(data.image_url)}" alt="AI design">`;
            body += `<div class="studio-actions">
                <a class="btn-primary" href="${esc(createUrl)}">Use on new product</a>
                <a class="btn-ghost" href="${esc(data.image_url)}" target="_blank" rel="noopener">Open image</a>
            </div>`;
        }

        const products = Array.isArray(data.products) ? data.products : [];
        if (products.length) {
            body += '<div class="studio-product-grid">';
            products.forEach((p) => {
                body += `<div class="studio-product-card">`;
                if (p.image) body += `<img src="${esc(p.image)}" alt="">`;
                body += `<div>
                    <div class="sp-name">${esc(p.name || '')}</div>
                    ${p.price_label ? `<div class="sp-price">${esc(p.price_label)}</div>` : ''}
                    ${p.meta ? `<div class="sp-meta">${esc(p.meta)}</div>` : ''}
                    ${p.url ? `<a href="${esc(p.url)}" target="_blank" rel="noopener">View</a>` : ''}
                </div></div>`;
            });
            body += '</div>';
        }

        const el = document.createElement('div');
        el.className = 'chat-turn';
        el.dataset.turn = String(tid);
        el.innerHTML = `<div class="chat-row user">
            <div class="chat-bubble user">${esc(query)}</div>
        </div>
        <div class="chat-row agent">
            <div class="chat-bubble agent">${body}</div>
        </div>`;
        feed.appendChild(el);
        scrollBottom();
    }

    async function sendStudioQuery(text) {
        if (!aiDesignUrl) {
            appendTurn(text || 'AI Mode', { summary: 'AI Mode endpoint missing.', type: 'error' });
            return;
        }

        const typed = (text || input.value).trim();
        if (!typed && !['bestsellers', 'trends'].includes(studioMode)) {
            return;
        }

        const display = typed || ({
            bestsellers: 'Show bestsellers',
            trends: 'Discover trending products',
        })[studioMode] || typed;

        input.value = '';
        sendBtn.disabled = true;
        document.getElementById('agent-welcome')?.remove();
        showTyping(display, [], MODE_TYPING[studioMode] || 'Working…');

        try {
            const res = await fetch(aiDesignUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ prompt: typed, mode: studioMode }),
            });
            const data = await res.json().catch(() => ({}));
            hideTyping();
            if (!res.ok) {
                throw new Error(data.message || 'Request failed');
            }
            appendStudioTurn(display, data);
        } catch (e) {
            hideTyping();
            appendTurn(display, {
                summary: e.message || 'Request failed. Please try again.',
                type: 'error',
            });
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    async function sendQuery(text) {
        if (STUDIO_MODES.has(studioMode) && !pendingImages.length) {
            return sendStudioQuery(text);
        }

        const typed = (text || input.value).trim();
        let message = typed;
        if (!message && selectedProduct) {
            message = `Tell me more about ${selectedProduct.name}`;
        }
        if (!message && !pendingImages.length) return;

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

        const imagesToSend = pendingImages.slice();
        const displayQuery = message || (imagesToSend.length ? `📷 ${imagesToSend.length} image(s)` : '');

        input.value = '';
        sendBtn.disabled = true;
        showTyping(message || displayQuery, imagesToSend);

        try {
            let res;
            if (imagesToSend.length > 0) {
                const form = new FormData();
                form.append('message', message);
                const attachIntent = /\b(add image|upload image|attach image|image add|image upload|photo add|ছবি যোগ|ছবি দাও|ইমেজ যোগ|image দাও)\b/i.test(message);
                if (lastCreatedProductId && attachIntent) {
                    form.append('product_id', String(lastCreatedProductId));
                }
                imagesToSend.forEach(file => form.append('images[]', file));
                if (payload.selected_product) {
                    Object.entries(payload.selected_product).forEach(([k, v]) => {
                        if (v != null) form.append(`selected_product[${k}]`, String(v));
                    });
                }
                if (payload.context_products) {
                    payload.context_products.forEach((p, i) => {
                        Object.entries(p).forEach(([k, v]) => {
                            if (v != null) form.append(`context_products[${i}][${k}]`, String(v));
                        });
                    });
                }
                res = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: form,
                });
            } else {
                res = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload),
                });
            }
            hideTyping();
            if (!res.ok) throw new Error('fail');
            const data = await res.json();
            if (!data.query) data.query = displayQuery;
            if (!data.type && data.meta?.type) data.type = data.meta.type;
            if (!data.cart_url && data.meta?.cart_url) data.cart_url = data.meta.cart_url;
            if (!data.checkout_url && data.meta?.checkout_url) data.checkout_url = data.meta.checkout_url;
            if (data.type === 'product_create_success' && data.product?.id) {
                lastCreatedProductId = data.product.id;
            }
            if (data.type === 'product_image_updated' && data.product?.id) {
                lastCreatedProductId = data.product.id;
            }
            pendingImages = [];
            renderImagePreviews();
            appendTurn(message || displayQuery, data, imagesToSend);
        } catch (e) {
            hideTyping();
            appendTurn(message || displayQuery, { summary: 'Request failed. Please try again.', type: 'error' }, imagesToSend);
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

    const micBtn = document.getElementById('agent-mic-btn');
    const SpeechRecognitionAPI = window.SpeechRecognition || window.webkitSpeechRecognition || null;
    let agentRecognition = null;
    let agentListening = false;

    function stopAgentVoice() {
        if (agentRecognition) {
            try {
                agentRecognition.onresult = null;
                agentRecognition.onerror = null;
                agentRecognition.onend = null;
                agentRecognition.stop();
            } catch (_) {}
            agentRecognition = null;
        }
        agentListening = false;
        micBtn?.classList.remove('is-listening');
        if (micBtn) {
            micBtn.title = 'Voice input';
            micBtn.setAttribute('aria-pressed', 'false');
        }
        if (input) input.placeholder = MODE_PLACEHOLDERS[studioMode] || MODE_PLACEHOLDERS.chat;
    }

    function toggleAgentVoice() {
        if (!micBtn) return;
        if (agentListening) {
            stopAgentVoice();
            return;
        }
        if (!SpeechRecognitionAPI) {
            alert('Voice input এই browser-এ সাপোর্ট করে না। Chrome বা Edge ব্যবহার করুন।');
            return;
        }

        const recognition = new SpeechRecognitionAPI();
        recognition.lang = 'bn-BD';
        recognition.interimResults = true;
        recognition.continuous = false;
        recognition.maxAlternatives = 1;
        let finalTranscript = '';

        recognition.onstart = () => {
            agentListening = true;
            micBtn.classList.add('is-listening');
            micBtn.title = 'Listening… tap to stop';
            micBtn.setAttribute('aria-pressed', 'true');
            input.placeholder = 'শুনছি… কথা বলুন';
        };
        recognition.onresult = (event) => {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) finalTranscript += transcript;
                else interim += transcript;
            }
            input.value = (finalTranscript || interim).trim();
        };
        recognition.onerror = (event) => {
            stopAgentVoice();
            if (event.error === 'not-allowed') {
                alert('Microphone permission দিন — browser address bar-এ mic allow করুন।');
            } else if (event.error !== 'aborted' && event.error !== 'no-speech') {
                alert('Voice input ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
            }
        };
        recognition.onend = () => {
            const text = (finalTranscript || input.value || '').trim();
            stopAgentVoice();
            if (text) {
                input.value = text;
                sendQuery(text);
            }
        };

        agentRecognition = recognition;
        try {
            recognition.start();
        } catch (_) {
            stopAgentVoice();
            alert('Voice input শুরু করা যায়নি। আবার চেষ্টা করুন।');
        }
    }

    micBtn?.addEventListener('click', toggleAgentVoice);
    if (micBtn && !SpeechRecognitionAPI) {
        micBtn.disabled = true;
        micBtn.title = 'Voice not supported in this browser';
    }

    function bindWelcomeChips() {
        document.querySelectorAll('#agent-welcome .follow-chip').forEach(btn => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.mode;
                const prompt = btn.dataset.prompt || '';
                if (mode) {
                    setStudioMode(mode);
                    if (prompt) {
                        input.value = prompt;
                    }
                    if (['bestsellers', 'trends'].includes(mode) || prompt) {
                        sendQuery(prompt);
                    } else {
                        input.focus();
                    }
                    return;
                }
                if (btn.dataset.query) {
                    setStudioMode('chat');
                    sendQuery(btn.dataset.query);
                }
            });
        });
    }

    function restoreWelcome() {
        if (!welcomeTemplate) return;
        feed.innerHTML = '';
        feed.insertAdjacentHTML('beforeend', welcomeTemplate);
        welcome = document.getElementById('agent-welcome');
        bindWelcomeChips();
    }

    function resetChatUI() {
        stopAgentVoice();
        turnId = 0;
        selectedProduct = null;
        lastPlatformProducts = [];
        sectionProductsStore.clear();
        pendingImages = [];
        lastCreatedProductId = null;
        window.__AGENT_INITIAL__ = [];
        clearInputChip();
        input.value = '';
        renderImagePreviews();
        restoreWelcome();
        scrollBottom();
    }

    async function resetAgentChat() {
        resetBtn.disabled = true;
        try {
            const res = await fetch(resetUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) {
                throw new Error('reset failed');
            }
            resetChatUI();
        } catch {
            appendTurn('system', { content: 'Chat reset ব্যর্থ। আবার চেষ্টা করুন।', type: 'error' });
        } finally {
            resetBtn.disabled = false;
            input.focus();
        }
    }

    resetBtn.addEventListener('click', () => resetAgentChat());

    bindWelcomeChips();

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
