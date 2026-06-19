<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Verify Your Email</h1>
        <p class="text-sm text-gray-500 mt-1">We sent a verification link to <strong>{{ auth()->user()->email }}</strong></p>
      </div>

      <div class="card p-8 text-center">
        <div class="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>

        <p class="text-sm text-gray-600 mb-6">
          Please check your inbox and click the verification link. If you didn't receive the email, you can request another.
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
          @csrf
          <button type="submit" class="btn-primary w-full py-2.5">Resend Verification Email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
          @csrf
          <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Sign out</button>
        </form>
      </div>
    </div>
  </div>
</x-layouts.app>
