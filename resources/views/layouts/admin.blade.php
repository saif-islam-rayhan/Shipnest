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
<body class="admin-shell min-h-screen font-sans antialiased"
      x-data="{ sidebarOpen: true, mobileSidebar: false, settingsOpen: {{ request()->routeIs('admin.settings.*') ? 'true' : 'false' }} }">
    @if(session('impersonator_id'))
        <div class="relative z-50 bg-amber-500 py-2 px-4 text-center text-sm text-white">
            Impersonating {{ auth()->user()->name }}.
            <form action="{{ route('impersonate.stop') }}" method="POST" class="inline">@csrf
                <button class="ml-2 font-semibold underline">Stop</button>
            </form>
        </div>
    @endif

    <div class="flex min-h-screen">
        <div x-show="mobileSidebar" x-transition.opacity
             @click="mobileSidebar = false"
             class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>

        @include('layouts.partials.admin-sidebar')

        <div class="flex min-w-0 flex-1 flex-col transition-all duration-300"
             :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-[72px]'">
            @include('layouts.partials.admin-topbar')
            <main class="flex-1 p-4 lg:p-6">
                @if(session('success'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                @endif
                @yield('content')
            </main>
            <footer class="border-t border-gray-200 bg-white px-4 py-3 text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} {{ config('shipnest.name', 'ShipNest') }} Admin
            </footer>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <x-admin-agent-fab />
    @stack('scripts')
</body>
</html>
