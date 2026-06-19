<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">My Products</h1>
      <a href="{{ route('merchant.products.create') }}" class="btn-primary">Add Product</a>
    </div>
    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">Product</th>
            <th class="px-4 py-3 text-left">SKU</th>
            <th class="px-4 py-3 text-left">Price</th>
            <th class="px-4 py-3 text-left">Stock</th>
            <th class="px-4 py-3 text-left">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($products as $product)
            <tr>
              <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
              <td class="px-4 py-3 text-gray-500">{{ $product->sku }}</td>
              <td class="px-4 py-3">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</td>
              <td class="px-4 py-3">{{ $product->stock }}</td>
              <td class="px-4 py-3"><span class="badge bg-gray-100 text-gray-800">{{ $product->status->label() }}</span></td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No products yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
  </div>
</x-layouts.app>
