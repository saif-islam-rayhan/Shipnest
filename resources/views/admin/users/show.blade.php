@extends('layouts.admin')
@section('title',$user->name) @section('page-title','User Profile')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <img src="{{ $user->avatar_url }}" class="w-20 h-20 rounded-full mb-4">
        <h2 class="text-xl font-bold">{{ $user->name }}</h2>
        <p class="text-gray-500">{{ $user->email }}</p>
        <p class="text-sm mt-2">Role: {{ $user->roles->first()?->name }}</p>
        <p class="text-sm">Status: {{ $user->status }}</p>
        <div class="mt-4 flex gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="btn-primary text-sm">Edit</a>
            @unless($user->isAdmin())<form action="{{ route('admin.users.impersonate', $user) }}" method="POST">@csrf<button class="btn-outline text-sm">Impersonate</button></form>@endunless
        </div>
    </div>
    <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <h3 class="font-semibold mb-3">Recent Orders ({{ $user->orders->count() }})</h3>
        @forelse($user->orders->take(5) as $order)
            <div class="py-2 border-b text-sm flex justify-between"><span>#{{ $order->order_number }}</span><span>{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</span></div>
        @empty<p class="text-gray-500 text-sm">No orders.</p>@endforelse
    </div>
</div>
@endsection
