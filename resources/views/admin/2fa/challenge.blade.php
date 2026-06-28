<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin 2FA — {{ config('shipnest.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-[#1A237E] flex items-center justify-center p-4 font-sans">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-sm">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Verify Identity</h1>
        <p class="text-sm text-gray-600 mb-6">Enter the code from your authenticator app.</p>
        @if(session('error'))<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
        <form action="{{ route('admin.2fa.verify') }}" method="POST" class="space-y-4">@csrf
            <input name="code" placeholder="000000" class="input-field text-center text-2xl tracking-[0.5em] font-mono" maxlength="6" required autofocus>
            <button class="btn-primary w-full">Verify</button>
        </form>
        <form action="{{ route('logout') }}" method="POST" class="mt-4 text-center">@csrf<button class="text-sm text-gray-500 hover:underline">Sign out</button></form>
    </div>
</body>
</html>
