<x-layouts.app>
  <div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <a href="{{ route('home') }}" class="text-3xl font-bold">
          <span class="text-primary">Ship</span><span class="text-secondary">Nest</span>
        </a>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Verify Phone Number</h1>
        <p class="text-sm text-gray-500 mt-1">Enter the OTP sent to <strong>{{ $phone }}</strong></p>
      </div>

      <div class="card p-8">
        @if(session('debug_otp'))
          <div class="mb-4 rounded-md bg-yellow-50 p-3 border border-yellow-200">
            <p class="text-sm text-yellow-800">Dev mode OTP: <strong>{{ session('debug_otp') }}</strong></p>
          </div>
        @endif

        <form method="POST" action="{{ route('otp.verify') }}" class="space-y-5">
          @csrf
          <input type="hidden" name="type" value="{{ $type }}">
          <input type="hidden" name="phone" value="{{ $phone }}">

          <div>
            <label for="otp" class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
            <input type="text" name="otp" id="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus
                   placeholder="6-digit code" class="input-field text-center text-2xl tracking-widest @error('otp') border-red-500 @enderror">
            @error('otp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>

          <button type="submit" class="btn-primary w-full py-2.5">Verify OTP</button>
        </form>

        <form method="POST" action="{{ route('otp.send') }}" class="mt-4">
          @csrf
          <input type="hidden" name="type" value="{{ $type }}">
          <input type="hidden" name="phone" value="{{ $phone }}">
          <button type="submit" class="w-full text-sm text-primary font-medium hover:underline">Resend OTP</button>
        </form>
      </div>
    </div>
  </div>
</x-layouts.app>
