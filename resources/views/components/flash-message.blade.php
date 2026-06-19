@props(['type' => 'success', 'message' => null, 'dismissible' => true])

@php
    $text = $message ?? match($type) {
        'error' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
        default => session('success'),
    };

    $styles = match($type) {
        'error' => 'bg-red-50 border-red-200 text-red-800',
        'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
        'info' => 'bg-blue-50 border-blue-200 text-blue-800',
        default => 'bg-green-50 border-green-200 text-green-800',
    };
@endphp

@if($text)
<div
    {{ $attributes->merge(['class' => "rounded-lg border p-4 {$styles}"]) }}
    @if($dismissible) x-data="{ show: true }" x-show="show" x-transition @endif
>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium">{{ $text }}</p>
        @if($dismissible)
            <button type="button" @click="show = false" class="flex-shrink-0 opacity-60 hover:opacity-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif
    </div>
</div>
@endif
