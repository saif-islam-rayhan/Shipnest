<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('shipnest.name')) - {{ config('shipnest.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
    @include('layouts.partials.header')

    <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
        @if(session('success'))
            <x-flash-message type="success" class="mb-4" />
        @endif
        @if(session('error'))
            <x-flash-message type="error" class="mb-4" />
        @endif
    </div>

    <main class="flex-1">
        @yield('content')
    </main>

    @include('layouts.partials.footer')

    @stack('scripts')
</body>
</html>
