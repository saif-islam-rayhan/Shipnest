@props(['paginator'])

@if ($paginator->hasPages())
<nav {{ $attributes->merge(['class' => 'flex items-center justify-center']) }} aria-label="Pagination">
    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="px-3 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-md cursor-not-allowed">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-md hover:bg-gray-50 hover:border-primary hover:text-primary transition">Previous</a>
        @endif

        @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if ($page == $paginator->currentPage())
                <span class="px-3.5 py-2 text-sm font-semibold text-white bg-primary border border-primary rounded-md">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="px-3.5 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-md hover:bg-gray-50 hover:border-primary hover:text-primary transition">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-md hover:bg-gray-50 hover:border-primary hover:text-primary transition">Next</a>
        @else
            <span class="px-3 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-md cursor-not-allowed">Next</span>
        @endif
    </div>
</nav>
@endif
