<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Reset Password</h1>
        <p class="text-sm text-gray-500 mt-1">Enter your new password</p>
      </div>

      <div class="card p-8">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">

          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required class="input-field @error('email') border-red-500 @enderror">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input type="password" name="password" id="password" required class="input-field @error('password') border-red-500 @enderror">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required class="input-field">
          </div>
          <button type="submit" class="btn-primary w-full py-2.5">Reset Password</button>
        </form>
      </div>
    </div>
  </div>
</x-layouts.app>
