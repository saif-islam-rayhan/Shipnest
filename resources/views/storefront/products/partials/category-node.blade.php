@php
  $isActive = ($activeCategory && $activeCategory->id === $category->id)
    || in_array($category->id, $activeCategoryIds, true)
    || request('category') == $category->id;
  $hasChildren = $category->children->isNotEmpty();
  $isExpanded = $isActive || ($activeCategory && in_array($category->id, $activeCategoryIds, true));
@endphp

<li x-data="{ open: {{ $isExpanded ? 'true' : 'false' }} }">
  <div class="flex items-center gap-1" style="padding-left: {{ $depth * 12 }}px">
    @if($hasChildren)
      <button type="button" @click="open = !open" class="p-1 text-gray-400 hover:text-gray-600">
        <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-90'" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
        </svg>
      </button>
    @else
      <span class="w-5"></span>
    @endif
    <a href="{{ route('category.show', $category->slug) }}"
       class="flex-1 py-1.5 px-2 rounded truncate {{ $isActive ? 'bg-primary-50 text-primary font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
      {{ $category->name }}
    </a>
  </div>
  @if($hasChildren)
    <ul x-show="open" x-collapse class="mt-1 space-y-1">
      @foreach($category->children as $child)
        @include('storefront.products.partials.category-node', [
          'category' => $child,
          'activeCategory' => $activeCategory,
          'activeCategoryIds' => $activeCategoryIds,
          'depth' => $depth + 1,
        ])
      @endforeach
    </ul>
  @endif
</li>
