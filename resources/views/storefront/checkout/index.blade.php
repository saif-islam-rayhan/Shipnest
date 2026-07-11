<x-layouts.app>
  @php
    $checkoutConfig = [
        'step' => 1,
        'useNewAddress' => $addresses->isEmpty(),
        'shippingMethod' => old('shipping_method', 'standard'),
        'paymentMethod' => old('payment_method', 'cod'),
        'paymentReference' => old('payment_reference', ''),
        'subtotal' => $orderTotals['subtotal'],
        'discount' => $orderTotals['discount'],
        'shipping' => $orderTotals['shipping'],
        'total' => $orderTotals['total'],
        'freeShippingEnabled' => (bool) config('shipping.free_shipping_enabled', false),
        'freeShippingThreshold' => (float) config('shipnest.free_shipping_threshold', 500),
        'currencySymbol' => config('shipnest.currency_symbol', '৳'),
        'gatewayRedirect' => $gatewayRedirect,
    ];
  @endphp

  <div class="max-w-3xl mx-auto px-4 py-4 sm:py-6" x-data='checkoutWizard(@json($checkoutConfig))'>
    <h1 class="text-xl font-bold text-gray-900 mb-3">{{ __('messages.checkout') }}</h1>

    {{-- Progress steps --}}
    <nav class="flex items-center justify-between mb-4 sm:mb-5">
      @foreach(['Address', 'Shipping', 'Payment', 'Review'] as $i => $label)
        <div class="flex items-center" :class="{ 'flex-1': {{ $i }} < 3 }">
          <div class="flex items-center gap-1.5">
            <span class="w-6 h-6 sm:w-7 sm:h-7 rounded-full flex items-center justify-center text-xs font-semibold"
              :class="step >= {{ $i + 1 }} ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600'">{{ $i + 1 }}</span>
            <span class="text-xs font-medium hidden sm:inline" :class="step >= {{ $i + 1 }} ? 'text-primary' : 'text-gray-500'">{{ $label }}</span>
          </div>
          @if($i < 3)
            <div class="flex-1 h-0.5 mx-1.5 sm:mx-3" :class="step > {{ $i + 1 }} ? 'bg-primary' : 'bg-gray-200'"></div>
          @endif
        </div>
      @endforeach
    </nav>

    <form action="{{ route('checkout.store') }}" method="POST" novalidate @submit="return validateBeforeSubmit()">
      @csrf
      <input type="hidden" name="payment_method" :value="paymentMethod">
      <input type="hidden" name="shipping_method" :value="shippingMethod">

      {{-- Step 1: Address --}}
      <div x-show="step === 1" x-transition>
        <div class="card p-4 sm:p-5">
          <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('messages.delivery_address') }}</h2>

          @if($addresses->isNotEmpty())
            <div class="space-y-2 mb-3">
              @foreach($addresses as $address)
                <label class="flex items-start gap-2.5 p-2.5 border rounded-md cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                  <input type="radio" name="address_id" value="{{ $address->id }}" class="mt-0.5 text-primary focus:ring-primary"
                    @checked($address->is_default || $loop->first)
                    @click="useNewAddress = false"
                    :disabled="useNewAddress">
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $address->label }} — {{ $address->recipient_name }}</p>
                    <p class="text-xs text-gray-600 leading-snug">{{ $address->full_address }}</p>
                    <p class="text-xs text-gray-500">{{ $address->phone }}</p>
                  </div>
                </label>
              @endforeach
            </div>
            <button type="button" @click="useNewAddress = !useNewAddress; if (useNewAddress) { setTimeout(() => window.dispatchEvent(new Event('map-picker-resize')), 300) }" class="text-xs text-primary hover:underline mb-3">
              + {{ __('messages.add_new_address') }}
            </button>
          @endif

          <div x-show="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-gray-700 mb-1">Full Name</label>
              <input type="text" name="new_address[recipient_name]" value="{{ old('new_address.recipient_name', auth()->user()->name) }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
              <input type="text" name="new_address[phone]" value="{{ old('new_address.phone', auth()->user()->phone) }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Postal Code</label>
              <input type="text" name="new_address[postal_code]" value="{{ old('new_address.postal_code') }}" class="input-field">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-gray-700 mb-1">Address</label>
              <input type="text" name="new_address[address_line1]" value="{{ old('new_address.address_line1') }}" class="input-field" placeholder="House, road, area" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">District</label>
              <input type="text" name="new_address[district]" value="{{ old('new_address.district', 'Dhaka') }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Thana</label>
              <input type="text" name="new_address[thana]" value="{{ old('new_address.thana') }}" class="input-field">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">City</label>
              <input type="text" name="new_address[city]" value="{{ old('new_address.city', 'Dhaka') }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <x-map-address-picker
              prefix="new_address"
              :latitude="old('new_address.latitude')"
              :longitude="old('new_address.longitude')"
            />
            <div class="sm:col-span-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="new_address[is_default]" value="1" class="rounded text-primary focus:ring-primary">
                <span class="text-xs text-gray-700">Set as default address</span>
              </label>
            </div>
          </div>
        </div>

        <div class="sticky bottom-0 z-20 mt-4 -mx-4 px-4 py-3 border-t bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90">
          <div class="max-w-3xl mx-auto">
            <button type="button" @click="step = 2; window.scrollTo({ top: 0, behavior: 'smooth' })" class="btn-primary w-full h-10 text-sm">{{ __('messages.continue_shipping') }}</button>
          </div>
        </div>
      </div>

      {{-- Step 2: Shipping --}}
      <div x-show="step === 2" x-cloak>
        <div class="card p-4 sm:p-5">
          <h2 class="text-sm font-semibold text-gray-900 mb-3">Shipping Method</h2>
          <div class="divide-y rounded-md border border-gray-200 overflow-hidden">
            @foreach($shippingMethods as $key => $method)
              <label class="flex items-center justify-between gap-3 px-3 py-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:bg-primary-50">
                <div class="flex items-center gap-2.5 min-w-0">
                  <input type="radio" value="{{ $key }}" class="text-primary focus:ring-primary flex-shrink-0"
                    x-model="shippingMethod" @change="updateShipping('{{ $key }}', {{ $method['rate'] }})"
                    @checked($key === 'standard')>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 leading-tight">{{ $method['name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $method['courier'] }} · {{ $method['days'] }}</p>
                  </div>
                </div>
                <span class="text-sm font-semibold text-gray-900 flex-shrink-0">{{ config('shipnest.currency_symbol') }}{{ number_format($method['rate']) }}</span>
              </label>
            @endforeach
          </div>
          <p x-show="freeShippingEnabled && subtotal >= freeShippingThreshold" class="text-xs text-green-600 mt-2">Free shipping applied — order exceeds {{ config('shipnest.currency_symbol') }}{{ number_format(config('shipnest.free_shipping_threshold', 500)) }}</p>
        </div>

        <div class="sticky bottom-0 z-20 mt-4 -mx-4 px-4 py-3 border-t bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90">
          <div class="max-w-3xl mx-auto flex gap-2">
            <button type="button" @click="step = 1; window.scrollTo({ top: 0, behavior: 'smooth' })" class="btn-secondary flex-1 h-10 text-sm">Back</button>
            <button type="button" @click="step = 3; window.scrollTo({ top: 0, behavior: 'smooth' })" class="btn-primary flex-[1.4] h-10 text-sm">Continue to Payment</button>
          </div>
        </div>
      </div>

      {{-- Step 3: Payment --}}
      <div x-show="step === 3" x-cloak>
        <div class="card p-4 sm:p-5">
          <h2 class="text-sm font-semibold text-gray-900 mb-3">Payment Method</h2>
          <div class="divide-y rounded-md border border-gray-200 overflow-hidden">
            @foreach($paymentMethods as $method)
              <label class="flex items-center gap-2.5 px-3 py-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:bg-primary-50">
                <input type="radio" value="{{ $method->value }}" class="text-primary focus:ring-primary"
                  x-model="paymentMethod">
                <span class="text-sm font-medium text-gray-900">{{ $method->label() }}</span>
              </label>
            @endforeach
          </div>

          <div class="mt-3 px-3 py-2 bg-primary-50 rounded-md text-xs" x-show="paymentMethod !== 'cod'">
            <p class="font-semibold text-gray-900">Amount to pay: <span x-text="currencySymbol + formatMoney(total)"></span></p>
            <p class="text-gray-600">Includes shipping: <span x-text="currencySymbol + formatMoney(shipping)"></span></p>
          </div>

          <div x-show="paymentMethod === 'sslcommerz'" class="mt-3 px-3 py-2 bg-blue-50 rounded-md text-xs text-blue-800">
            <template x-if="gatewayRedirect.sslcommerz">
              <p>You will be redirected to SSLCommerz to pay securely with card or internet banking.</p>
            </template>
            <template x-if="!gatewayRedirect.sslcommerz">
              <p class="text-red-700">SSLCommerz is not configured. Choose another payment method.</p>
            </template>
          </div>

          <div x-show="paymentMethod === 'stripe'" class="mt-3 px-3 py-2 bg-indigo-50 rounded-md text-xs text-indigo-900">
            <template x-if="gatewayRedirect.stripe">
              <p>You will be redirected to Stripe to pay securely with international cards (Visa, Mastercard, etc.).</p>
            </template>
            <template x-if="!gatewayRedirect.stripe">
              <p class="text-red-700">Stripe is not enabled. Contact support or choose another method.</p>
            </template>
          </div>

          <div x-show="paymentMethod === 'bkash'" class="mt-3 px-3 py-2 bg-pink-50 rounded-md space-y-1">
            <template x-if="gatewayRedirect.bkash">
              <p class="text-xs text-pink-900">You will be redirected to bKash to pay <strong x-text="currencySymbol + formatMoney(total)"></strong>.</p>
              <p class="text-[11px] text-pink-800">Or pay manually to <strong>{{ $merchantNumbers['bkash'] ?? 'N/A' }}</strong> and enter transaction ID below.</p>
            </template>
            <template x-if="!gatewayRedirect.bkash">
              <p class="text-xs text-pink-900">Send <strong x-text="currencySymbol + formatMoney(total)"></strong> to bKash: <strong>{{ $merchantNumbers['bkash'] ?? 'N/A' }}</strong></p>
              <p class="text-[11px] text-pink-800">Full amount includes product price + shipping charge.</p>
            </template>
          </div>

          <div x-show="paymentMethod === 'nagad'" class="mt-3 px-3 py-2 bg-orange-50 rounded-md space-y-1">
            <template x-if="gatewayRedirect.nagad">
              <p class="text-xs text-orange-900">You will be redirected to Nagad to pay <strong x-text="currencySymbol + formatMoney(total)"></strong>.</p>
              <p class="text-[11px] text-orange-800">Or pay manually to <strong>{{ $merchantNumbers['nagad'] ?? 'N/A' }}</strong> and enter transaction ID below.</p>
            </template>
            <template x-if="!gatewayRedirect.nagad">
              <p class="text-xs text-orange-900">Send <strong x-text="currencySymbol + formatMoney(total)"></strong> to Nagad: <strong>{{ $merchantNumbers['nagad'] ?? 'N/A' }}</strong></p>
              <p class="text-[11px] text-orange-800">Full amount includes product price + shipping charge.</p>
            </template>
          </div>

          <div x-show="['bkash', 'nagad'].includes(paymentMethod)" class="mt-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">
              <span x-show="gatewayRedirect[paymentMethod]">Transaction Reference (optional for redirect)</span>
              <span x-show="!gatewayRedirect[paymentMethod]">Transaction Reference *</span>
            </label>
            <input type="text" x-model="paymentReference" class="input-field" placeholder="Enter your transaction ID"
              :name="['bkash', 'nagad'].includes(paymentMethod) ? 'payment_reference' : ''">
          </div>

          <div x-show="paymentMethod === 'cod'" class="mt-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded-md text-xs text-amber-900">
            <p class="font-semibold mb-0.5">Cash on Delivery</p>
            <p>Pay <strong x-text="currencySymbol + formatMoney(total)"></strong> in cash when your order arrives (product + shipping).</p>
          </div>
        </div>

        <div class="sticky bottom-0 z-20 mt-4 -mx-4 px-4 py-3 border-t bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90">
          <div class="max-w-3xl mx-auto flex gap-2">
            <button type="button" @click="step = 2; window.scrollTo({ top: 0, behavior: 'smooth' })" class="btn-secondary flex-1 h-10 text-sm">Back</button>
            <button type="button" @click="step = 4; window.scrollTo({ top: 0, behavior: 'smooth' })" class="btn-primary flex-[1.4] h-10 text-sm">Review Order</button>
          </div>
        </div>
      </div>

      {{-- Step 4: Review --}}
      <div x-show="step === 4" x-cloak>
        <div class="card p-4 sm:p-5">
          <h2 class="text-sm font-semibold text-gray-900 mb-3">Order Summary</h2>
          @foreach($groupedItems as $group)
            <div class="mb-3 pb-3 border-b last:border-0 last:mb-0 last:pb-0">
              <p class="text-xs font-medium text-primary mb-1.5">{{ $group['shop']->name }}</p>
              @foreach($group['items'] as $item)
                <div class="flex justify-between text-xs sm:text-sm py-0.5 gap-3">
                  <span class="text-gray-600 min-w-0">
                    {{ $item->product->name }}
                    @if($item->variant) <span class="text-gray-400">({{ $item->variant->name }})</span> @endif
                    × {{ $item->quantity }}
                  </span>
                  <span class="flex-shrink-0">{{ config('shipnest.currency_symbol') }}{{ number_format($item->total_price) }}</span>
                </div>
              @endforeach
            </div>
          @endforeach

          <div class="space-y-1 text-xs sm:text-sm border-t pt-3 mt-1">
            <div class="flex justify-between">
              <span class="text-gray-600">Subtotal</span>
              <span>{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(subtotal)"></span></span>
            </div>
            <div class="flex justify-between" x-show="discount > 0">
              <span class="text-gray-600">Discount</span>
              <span class="text-green-600">−{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(discount)"></span></span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Shipping</span>
              <span>{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(shipping)"></span></span>
            </div>
            <div class="flex justify-between font-bold text-sm sm:text-base pt-1.5">
              <span>Total</span>
              <span class="text-primary">{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(total)"></span></span>
            </div>
          </div>

          @if($cart->coupon_code)
            <p class="text-[11px] text-green-600 mt-2">Coupon applied: {{ $cart->coupon_code }}</p>
          @endif
        </div>

        <div class="card p-4 sm:p-5 mt-3">
          <label class="block text-xs font-medium text-gray-700 mb-1">Order Notes (optional)</label>
          <textarea name="notes" rows="2" class="input-field text-sm" placeholder="Special instructions for delivery">{{ old('notes') }}</textarea>
        </div>

        <div class="sticky bottom-0 z-20 mt-4 -mx-4 px-4 py-3 border-t bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90">
          <div class="max-w-3xl mx-auto flex gap-2">
            <button type="button" @click="step = 3; window.scrollTo({ top: 0, behavior: 'smooth' })" class="btn-secondary flex-1 h-10 text-sm">Back</button>
            <button type="submit" class="btn-primary flex-[1.4] h-10 text-sm">Place Order</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</x-layouts.app>
