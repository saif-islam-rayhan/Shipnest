@extends('layouts.admin')
@section('title','2FA Setup') @section('page-title','Two-Factor Authentication')
@section('content')
<div class="max-w-lg bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
    @if(session('error'))
        <p class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ session('error') }}</p>
    @endif
    <p class="text-sm text-gray-600">Scan this QR code with Google Authenticator, or enter the secret key manually.</p>
    <div class="flex justify-center p-4 bg-gray-50 rounded-lg">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrUrl) }}" alt="2FA QR Code" width="200" height="200">
    </div>
    <p class="text-center font-mono text-sm bg-gray-100 p-2 rounded break-all">{{ $secret }}</p>
    <form action="{{ route('admin.2fa.enable') }}" method="POST" class="space-y-3">@csrf
        <input name="code" placeholder="Enter 6-digit code" class="input-field text-center tracking-widest" maxlength="6" required autofocus>
        <button class="btn-primary w-full">Enable 2FA</button>
    </form>
</div>
@endsection
