<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - {{ config('shipnest.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-gray-100 font-sans antialiased" x-data="{ sidebarOpen: true }">
    @if(session('impersonator_id'))
        <div class="bg-amber-500 text-white text-center text-sm py-2 px-4">
            Impersonating {{ auth()->user()->name }}.
            <form action="{{ route('impersonate.stop') }}" method="POST" class="inline">@csrf
                <button class="underline font-semibold ml-2">Stop</button>
            </form>
        </div>
    @endif
    <div class="flex min-h-screen">
        @include('layouts.partials.admin-sidebar')
        <div class="flex-1 flex flex-col min-w-0 transition-all" :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-20'">
            @include('layouts.partials.admin-topbar')
            <main class="flex-1 p-4 lg:p-6">
                @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
                @yield('content')
            </main>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    @stack('scripts')
</body>
</html>
