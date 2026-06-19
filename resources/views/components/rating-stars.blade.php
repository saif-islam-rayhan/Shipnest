@props(['rating' => 0, 'size' => 'sm'])

@php
    $rating = max(0, min(5, (float) $rating));
    $fullStars = (int) floor($rating);
    $hasHalf = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($hasHalf ? 1 : 0);
    $sizeClass = match($size) {
        'lg' => 'w-5 h-5',
        'md' => 'w-4 h-4',
        default => 'w-3.5 h-3.5',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }} aria-label="Rating: {{ number_format($rating, 1) }} out of 5">
    @for ($i = 0; $i < $fullStars; $i++)
        <svg class="{{ $sizeClass }} text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endfor

    @if ($hasHalf)
        @php $gradientId = 'half-star-'.uniqid(); @endphp
        <svg class="{{ $sizeClass }} text-yellow-400" viewBox="0 0 20 20">
            <defs>
                <linearGradient id="{{ $gradientId }}">
                    <stop offset="50%" stop-color="currentColor"/>
                    <stop offset="50%" stop-color="#D1D5DB"/>
                </linearGradient>
            </defs>
            <path fill="url(#{{ $gradientId }})" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endif

    @for ($i = 0; $i < $emptyStars; $i++)
        <svg class="{{ $sizeClass }} text-gray-300" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endfor
</div>
