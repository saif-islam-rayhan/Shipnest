<x-layouts.app>
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Add New Product</h1>
    <form action="{{ route('merchant.products.store') }}" method="POST" enctype="multipart/form-data" class="card p-8 space-y-5">
      @csrf
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
        <input type="text" name="name" value="{{ old('name') }}" required class="input-field">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
          <input type="text" name="sku" value="{{ old('sku') }}" required class="input-field">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
          <select name="category_id" required class="input-field">
            @foreach($categories as $category)
              <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Price ({{ config('shipnest.currency_symbol') }})</label>
          <input type="number" name="price" value="{{ old('price') }}" step="0.01" min="0" required class="input-field">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Compare Price</label>
          <input type="number" name="compare_price" value="{{ old('compare_price') }}" step="0.01" min="0" class="input-field">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Stock</label>
          <input type="number" name="stock" value="{{ old('stock', 0) }}" min="0" required class="input-field">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
        <textarea name="short_description" rows="2" class="input-field">{{ old('short_description') }}</textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="5" class="input-field">{{ old('description') }}</textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Images</label>
        <input type="file" name="images[]" multiple accept="image/*" class="input-field">
      </div>
      <input type="hidden" name="status" value="active">
      <button type="submit" class="btn-primary w-full py-2.5">Create Product</button>
    </form>
  </div>
</x-layouts.app>
