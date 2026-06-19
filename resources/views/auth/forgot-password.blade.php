<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Forgot Password</h1>
        <p class="text-sm text-gray-500 mt-1">Enter your email to receive a reset link</p>
      </div>

      <div class="card p-8">
        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
          @csrf
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus class="input-field @error('email') border-red-500 @enderror">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <button type="submit" class="btn-primary w-full py-2.5">Send Reset Link</button>
        </form>
        <p class="mt-6 text-center text-sm text-gray-600">
          Remember your password? <a href="{{ route('login') }}" class="text-primary font-medium hover:underline">Sign In</a>
        </p>
      </div>
    </div>
  </div>
</x-layouts.app>
