@props(['items' => []])

@if(count($items))
<nav {{ $attributes->merge(['class' => 'flex items-center text-sm text-gray-500']) }} aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1.5">
        <li>
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">Home</a>
        </li>
        @foreach($items as $item)
            <li class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                @if(!empty($item['url']) && !$loop->last)
                    <a href="{{ $item['url'] }}" class="hover:text-primary transition-colors">{{ $item['label'] }}</a>
                @else
                    <span class="text-gray-900 font-medium">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
