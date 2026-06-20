@props(['categories', 'activeCategory' => null, 'activeCategoryIds' => []])

<ul class="space-y-1 text-sm">
  <li>
    <a href="{{ route('products.index') }}"
       class="block py-1.5 px-2 rounded {{ ! $activeCategory && ! request('category') ? 'bg-primary-50 text-primary font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
      All Categories
    </a>
  </li>
  @foreach($categories as $category)
    @include('storefront.products.partials.category-node', [
      'category' => $category,
      'activeCategory' => $activeCategory,
      'activeCategoryIds' => $activeCategoryIds,
      'depth' => 0,
    ])
  @endforeach
</ul>
