<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-lg">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Become a Merchant</h1>
        <p class="text-sm text-gray-500 mt-1">Start selling on ShipNest — admin approval required</p>
      </div>

      <div class="card p-8">
        <form method="POST" action="{{ route('merchant.register') }}" class="space-y-5">
          @csrf

          <div class="border-b border-gray-200 pb-4 mb-1">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Account Details</h2>
          </div>

          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="input-field @error('name') border-red-500 @enderror">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required class="input-field @error('email') border-red-500 @enderror">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX" class="input-field @error('phone') border-red-500 @enderror">
            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input type="password" name="password" id="password" required class="input-field @error('password') border-red-500 @enderror">
              @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
              <input type="password" name="password_confirmation" id="password_confirmation" required class="input-field">
            </div>
          </div>

          <div class="border-b border-gray-200 pb-4 mb-1 pt-2">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Shop Details</h2>
          </div>

          <div>
            <label for="shop_name" class="block text-sm font-medium text-gray-700 mb-1">Shop Name</label>
            <input type="text" name="shop_name" id="shop_name" value="{{ old('shop_name') }}" required class="input-field @error('shop_name') border-red-500 @enderror">
            @error('shop_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="shop_slug" class="block text-sm font-medium text-gray-700 mb-1">Shop URL Slug</label>
            <div class="flex rounded-lg shadow-sm">
              <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">shipnest.com/shop/</span>
              <input type="text" name="shop_slug" id="shop_slug" value="{{ old('shop_slug') }}" required placeholder="my-shop" class="input-field rounded-l-none @error('shop_slug') border-red-500 @enderror">
            </div>
            @error('shop_slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="district" class="block text-sm font-medium text-gray-700 mb-1">District</label>
            <input type="text" name="district" id="district" value="{{ old('district') }}" required placeholder="e.g. Dhaka" class="input-field @error('district') border-red-500 @enderror">
            @error('district')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Shop Address</label>
            <textarea name="address" id="address" rows="2" required class="input-field @error('address') border-red-500 @enderror">{{ old('address') }}</textarea>
            @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>

          <button type="submit" class="btn-primary w-full py-2.5">Submit Application</button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
          Already have an account? <a href="{{ route('login') }}" class="text-primary font-medium hover:underline">Sign In</a>
        </p>
        <p class="mt-2 text-center text-sm text-gray-600">
          Just shopping? <a href="{{ route('register') }}" class="text-primary font-medium hover:underline">Register as customer</a>
        </p>
      </div>
    </div>
  </div>
</x-layouts.app>
