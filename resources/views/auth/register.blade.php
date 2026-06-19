<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Create an account</h1>
        <p class="text-sm text-gray-500 mt-1">Join millions of shoppers on ShipNest</p>
      </div>

      <div class="card p-8">
        <form method="POST" action="{{ route('register') }}" class="space-y-5">
          @csrf
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
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" id="password" required class="input-field @error('password') border-red-500 @enderror">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required class="input-field">
          </div>
          <button type="submit" class="btn-primary w-full py-2.5">Create Account</button>
        </form>
        <p class="mt-6 text-center text-sm text-gray-600">
          Already have an account? <a href="{{ route('login') }}" class="text-primary font-medium hover:underline">Sign In</a>
        </p>
        <p class="mt-2 text-center text-sm text-gray-600">
          Want to sell? <a href="{{ route('merchant.register') }}" class="text-primary font-medium hover:underline">Register as merchant</a>
        </p>
      </div>
    </div>
  </div>
</x-layouts.app>
