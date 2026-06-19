<x-layouts.app>
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Checkout</h1>

    <form action="{{ route('checkout.store') }}" method="POST" class="space-y-6">
      @csrf

      <div class="card p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Shipping Address</h2>
        @if($addresses->isEmpty())
          <p class="text-sm text-gray-500">No saved addresses. Please add an address in your account settings.</p>
        @else
          <div class="space-y-3">
            @foreach($addresses as $address)
              <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                <input type="radio" name="address_id" value="{{ $address->id }}" class="mt-1 text-primary focus:ring-primary" @checked($address->is_default || $loop->first) required>
                <div>
                  <p class="font-medium text-gray-900">{{ $address->label }} — {{ $address->name }}</p>
                  <p class="text-sm text-gray-600">{{ $address->full_address }}</p>
                  <p class="text-sm text-gray-500">{{ $address->phone }}</p>
                </div>
              </label>
            @endforeach
          </div>
        @endif
      </div>

      <div class="card p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Payment Method</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          @foreach($paymentMethods as $method)
            <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary-50">
              <input type="radio" name="payment_method" value="{{ $method->value }}" class="text-primary focus:ring-primary" @checked($loop->first) required>
              <span class="font-medium text-gray-900">{{ $method->label() }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <div class="card p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Order Summary</h2>
        @foreach($groupedItems as $group)
          <div class="mb-4 pb-4 border-b last:border-0">
            <p class="text-sm font-medium text-primary mb-2">{{ $group['shop']->name }}</p>
            @foreach($group['items'] as $item)
              <div class="flex justify-between text-sm py-1">
                <span class="text-gray-600">{{ $item->product->name }} × {{ $item->quantity }}</span>
                <span>{{ config('shipnest.currency_symbol') }}{{ number_format($item->total_price) }}</span>
              </div>
            @endforeach
          </div>
        @endforeach
        <div class="flex justify-between font-bold text-lg pt-2">
          <span>Total</span>
          <span class="text-primary">{{ config('shipnest.currency_symbol') }}{{ number_format($cart->subtotal) }}</span>
        </div>
      </div>

      <div class="card p-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">Coupon Code (optional)</label>
        <input type="text" name="coupon_code" class="input-field" placeholder="Enter coupon code">
      </div>

      <div class="card p-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">Order Notes (optional)</label>
        <textarea name="notes" rows="3" class="input-field" placeholder="Special instructions for delivery"></textarea>
      </div>

      <button type="submit" class="btn-primary w-full py-3 text-base" @disabled($addresses->isEmpty())>
        Place Order
      </button>
    </form>
  </div>
</x-layouts.app>
