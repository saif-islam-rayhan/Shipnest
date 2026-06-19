<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Application Pending</h1>
        <p class="text-sm text-gray-500 mt-1">Your merchant account is awaiting admin approval</p>
      </div>

      <div class="card p-8 text-center">
        <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>

        @if($merchant)
          <h2 class="text-lg font-semibold text-gray-900">{{ $merchant->shop_name }}</h2>
          <p class="text-sm text-gray-500 mt-1">Status: <span class="font-medium text-yellow-700">Pending Review</span></p>
        @endif

        <p class="text-sm text-gray-600 mt-4 mb-6">
          Thank you for applying to sell on ShipNest. Our team will review your application and notify you by email once approved. This usually takes 1–2 business days.
        </p>

        @if(auth()->user() && !auth()->user()->hasVerifiedEmail())
          <a href="{{ route('verification.notice') }}" class="btn-primary inline-block w-full py-2.5 mb-3">Verify Email</a>
        @endif

        <a href="{{ route('home') }}" class="text-sm text-primary font-medium hover:underline">Continue browsing</a>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
          @csrf
          <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Sign out</button>
        </form>
      </div>
    </div>
  </div>
</x-layouts.app>
