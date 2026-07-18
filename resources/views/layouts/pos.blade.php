<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'POS') - {{ config('shipnest.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <style>
        [x-cloak] { display: none !important; }
        html, body { height: 100%; overflow: hidden; }
        .pos-shell {
            font-family: Inter, system-ui, sans-serif;
            height: 100dvh;
            max-height: 100dvh;
            overflow: hidden;
            --pos-blue: #2563eb;
            --pos-blue-dark: #1d4ed8;
            --pos-footer: #0b2340;
        }
        .pos-scroll { scrollbar-width: thin; }
        .pos-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .pos-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .pos-cat-btn {
            white-space: nowrap;
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 0.4rem 0.9rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #334155;
            line-height: 1.25;
        }
        .pos-cat-btn:hover { border-color: #93c5fd; color: #1d4ed8; }
        .pos-cat-btn.is-active {
            background: var(--pos-blue);
            border-color: var(--pos-blue);
            color: #fff;
        }
        .pos-product-card {
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            background: #fff;
            overflow: hidden;
            min-width: 0;
            transition: border-color .15s, box-shadow .15s;
        }
        .pos-product-card:hover {
            border-color: #93c5fd;
            box-shadow: 0 1px 4px rgba(37, 99, 235, 0.1);
        }
        .pos-product-card .pos-thumb {
            height: 64px;
            background: #e8edf3;
            position: relative;
            flex-shrink: 0;
        }
        .pos-product-card .pos-thumb-letter {
            font-size: 1.5rem;
            font-weight: 700;
            color: #cbd5e1;
        }
        .pos-add-btn {
            width: 100%;
            background: var(--pos-blue);
            color: #fff;
            font-weight: 600;
            font-size: 0.6875rem;
            padding: 0.25rem 0.35rem;
            border-radius: 0.25rem;
            line-height: 1.2;
        }
        .pos-add-btn:hover { background: var(--pos-blue-dark); }
        .pos-pay-btn {
            width: 100%;
            background: var(--pos-blue);
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28);
        }
        .pos-pay-btn:hover:not(:disabled) { background: var(--pos-blue-dark); }
        .pos-pay-btn:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }
        .pos-footer {
            background: var(--pos-footer);
            color: #cbd5e1;
        }
        .pos-footer kbd {
            display: inline-block;
            background: rgba(255,255,255,0.14);
            border-radius: 0.25rem;
            padding: 0.12rem 0.4rem;
            font-family: ui-monospace, monospace;
            font-size: 0.68rem;
            color: #fff;
            margin-right: 0.3rem;
        }
        .pos-view-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            color: #64748b;
            background: #fff;
        }
        .pos-view-btn.is-active {
            background: var(--pos-blue);
            color: #fff;
        }
        .pos-view-btn:not(.is-active):hover { background: #f8fafc; }
        @media print {
            html, body, .pos-shell { height: auto !important; overflow: visible !important; }
            .pos-no-print > *:not(#pos-receipt-wrap) { display: none !important; }
            #pos-receipt-wrap { display: block !important; }
        }
    </style>
</head>
<body class="pos-shell bg-[#e8eef5] text-slate-900 antialiased">
    @yield('content')
    @stack('scripts')
</body>
</html>
