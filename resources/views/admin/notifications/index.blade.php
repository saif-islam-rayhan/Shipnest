@extends('layouts.admin')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <p class="text-sm text-gray-500">Agent review alerts and admin updates</p>
    <form action="{{ route('admin.notifications.read-all') }}" method="POST">
        @csrf
        <button type="submit" class="text-sm text-[#F57C00] hover:underline">Mark all read</button>
    </form>
</div>

<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden divide-y divide-gray-100">
    @forelse($notifications as $notification)
        <a href="{{ route('admin.notifications.read', $notification) }}"
           class="flex gap-3 px-4 py-3 hover:bg-gray-50 {{ $notification->read_at ? 'opacity-70' : '' }}">
            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ ($notification->data['sentiment'] ?? '') === 'negative' ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">
                @if(($notification->data['sentiment'] ?? '') === 'negative')
                    <span class="text-sm">😕</span>
                @else
                    <span class="text-sm">😊</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium text-gray-900">{{ $notification->title }}</p>
                    @unless($notification->read_at)
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#F57C00]"></span>
                    @endunless
                </div>
                <p class="mt-0.5 text-sm text-gray-600 line-clamp-2">{{ $notification->body }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
        </a>
    @empty
        <div class="px-4 py-10 text-center text-sm text-gray-500">No notifications yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
