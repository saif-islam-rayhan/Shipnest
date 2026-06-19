<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Welcome back</h1>
        <p class="text-sm text-gray-500 mt-1">Sign in to your account</p>
      </div>

      <div class="card p-8">
        <x-auth.social-buttons />

        <div class="relative my-6">
          <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
          <div class="relative flex justify-center text-sm"><span class="px-2 bg-white text-gray-500">or sign in with email</span></div>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
          @csrf
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus class="input-field @error('email') border-red-500 @enderror">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <div class="flex items-center justify-between mb-1">
              <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
              <a href="{{ route('password.request') }}" class="text-sm text-primary hover:underline">Forgot password?</a>
            </div>
            <input type="password" name="password" id="password" required class="input-field @error('password') border-red-500 @enderror">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div class="flex items-center">
            <input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember')) class="rounded border-gray-300 text-primary focus:ring-primary">
            <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
          </div>
          <button type="submit" class="btn-primary w-full py-2.5">Sign In</button>
        </form>

        <div class="mt-6 text-center">
          <a href="{{ route('login.otp') }}" class="text-sm text-primary font-medium hover:underline">Sign in with phone OTP instead</a>
        </div>

        <p class="mt-6 text-center text-sm text-gray-600">
          Don't have an account? <a href="{{ route('register') }}" class="text-primary font-medium hover:underline">Register</a>
        </p>
        <p class="mt-2 text-center text-sm text-gray-600">
          Want to sell? <a href="{{ route('merchant.register') }}" class="text-primary font-medium hover:underline">Become a merchant</a>
        </p>
      </div>
    </div>
  </div>
</x-layouts.app>
