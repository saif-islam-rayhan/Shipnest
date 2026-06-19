<x-layouts.app>
  <div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Create Your Shop</h1>
    <p class="text-gray-500 mb-6">Set up your shop to start selling on ShipNest.</p>

    <form action="{{ route('merchant.shop.store') }}" method="POST" enctype="multipart/form-data" class="card p-8 space-y-5">
      @csrf
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Shop Name</label>
        <input type="text" name="name" value="{{ old('name') }}" required class="input-field">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="4" class="input-field">{{ old('description') }}</textarea>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input type="tel" name="phone" value="{{ old('phone') }}" required class="input-field">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input type="email" name="email" value="{{ old('email') }}" required class="input-field">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
        <input type="text" name="address" value="{{ old('address') }}" required class="input-field">
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
          <input type="text" name="city" value="{{ old('city') }}" required class="input-field">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
          <input type="text" name="district" value="{{ old('district') }}" required class="input-field">
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
          <input type="file" name="logo" accept="image/*" class="input-field">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Banner</label>
          <input type="file" name="banner" accept="image/*" class="input-field">
        </div>
      </div>
      <button type="submit" class="btn-primary w-full py-2.5">Create Shop</button>
    </form>
  </div>
</x-layouts.app>
