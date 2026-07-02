<x-layouts.app>
  @php
    $checkoutConfig = [
        'step' => 1,
        'useNewAddress' => $addresses->isEmpty(),
        'shippingMethod' => old('shipping_method', 'standard'),
        'paymentMethod' => old('payment_method', 'cod'),
        'codShippingPayment' => old('cod_shipping_payment', 'bkash'),
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

  <div class="max-w-4xl mx-auto px-4 py-8" x-data='checkoutWizard(@json($checkoutConfig))'>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Checkout</h1>

    {{-- Progress steps --}}
    <nav class="flex items-center justify-between mb-8">
      @foreach(['Address', 'Shipping', 'Payment', 'Review'] as $i => $label)
        <div class="flex items-center" :class="{ 'flex-1': {{ $i }} < 3 }">
          <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold"
              :class="step >= {{ $i + 1 }} ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600'">{{ $i + 1 }}</span>
            <span class="text-sm font-medium hidden sm:inline" :class="step >= {{ $i + 1 }} ? 'text-primary' : 'text-gray-500'">{{ $label }}</span>
          </div>
          @if($i < 3)
            <div class="flex-1 h-0.5 mx-2 sm:mx-4" :class="step > {{ $i + 1 }} ? 'bg-primary' : 'bg-gray-200'"></div>
          @endif
        </div>
      @endforeach
    </nav>

    <form action="{{ route('checkout.store') }}" method="POST" novalidate @submit="return validateBeforeSubmit()">
      @csrf
      <input type="hidden" name="payment_method" :value="paymentMethod">
      <input type="hidden" name="shipping_method" :value="shippingMethod">

      {{-- Step 1: Address --}}
      <div x-show="step === 1" x-transition class="space-y-6">
        <div class="card p-6">
          <h2 class="font-semibold text-gray-900 mb-4">Delivery Address</h2>

          @if($addresses->isNotEmpty())
            <div class="space-y-3 mb-4">
              @foreach($addresses as $address)
                <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                  <input type="radio" name="address_id" value="{{ $address->id }}" class="mt-1 text-primary focus:ring-primary"
                    @checked($address->is_default || $loop->first)
                    @click="useNewAddress = false"
                    :disabled="useNewAddress">
                  <div>
                    <p class="font-medium text-gray-900">{{ $address->label }} — {{ $address->recipient_name }}</p>
                    <p class="text-sm text-gray-600">{{ $address->full_address }}</p>
                    <p class="text-sm text-gray-500">{{ $address->phone }}</p>
                  </div>
                </label>
              @endforeach
            </div>
            <button type="button" @click="useNewAddress = !useNewAddress" class="text-sm text-primary hover:underline mb-4">
              + Add New Address
            </button>
          @endif

          <div x-show="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
              <input type="text" name="new_address[recipient_name]" value="{{ old('new_address.recipient_name', auth()->user()->name) }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
              <input type="text" name="new_address[phone]" value="{{ old('new_address.phone', auth()->user()->phone) }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
              <input type="text" name="new_address[postal_code]" value="{{ old('new_address.postal_code') }}" class="input-field">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
              <input type="text" name="new_address[address_line1]" value="{{ old('new_address.address_line1') }}" class="input-field" placeholder="House, road, area" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
              <input type="text" name="new_address[district]" value="{{ old('new_address.district', 'Dhaka') }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Thana</label>
              <input type="text" name="new_address[thana]" value="{{ old('new_address.thana') }}" class="input-field">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
              <input type="text" name="new_address[city]" value="{{ old('new_address.city', 'Dhaka') }}" class="input-field" :required="useNewAddress || {{ $addresses->isEmpty() ? 'true' : 'false' }}">
            </div>
            <div class="sm:col-span-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="new_address[is_default]" value="1" class="rounded text-primary focus:ring-primary">
                <span class="text-sm text-gray-700">Set as default address</span>
              </label>
            </div>
          </div>
        </div>
        <button type="button" @click="step = 2" class="btn-primary w-full py-3">Continue to Shipping</button>
      </div>

      {{-- Step 2: Shipping --}}
      <div x-show="step === 2" x-cloak class="space-y-6">
        <div class="card p-6">
          <h2 class="font-semibold text-gray-900 mb-4">Shipping Method</h2>
          <div class="space-y-3">
            @foreach($shippingMethods as $key => $method)
              <label class="flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                <div class="flex items-center gap-3">
                  <input type="radio" value="{{ $key }}" class="text-primary focus:ring-primary"
                    x-model="shippingMethod" @change="updateShipping('{{ $key }}', {{ $method['rate'] }})"
                    @checked($key === 'standard')>
                  <div>
                    <p class="font-medium text-gray-900">{{ $method['name'] }}</p>
                    <p class="text-sm text-gray-500">{{ $method['courier'] }} · {{ $method['days'] }}</p>
                  </div>
                </div>
                <span class="font-semibold text-gray-900">{{ config('shipnest.currency_symbol') }}{{ number_format($method['rate']) }}</span>
              </label>
            @endforeach
          </div>
          <p x-show="freeShippingEnabled && subtotal >= freeShippingThreshold" class="text-sm text-green-600 mt-3">Free shipping applied — order exceeds {{ config('shipnest.currency_symbol') }}{{ number_format(config('shipnest.free_shipping_threshold', 500)) }}</p>
        </div>
        <div class="flex gap-3">
          <button type="button" @click="step = 1" class="btn-secondary flex-1 py-3">Back</button>
          <button type="button" @click="step = 3" class="btn-primary flex-1 py-3">Continue to Payment</button>
        </div>
      </div>

      {{-- Step 3: Payment --}}
      <div x-show="step === 3" x-cloak class="space-y-6">
        <div class="card p-6">
          <h2 class="font-semibold text-gray-900 mb-4">Payment Method</h2>
          <div class="space-y-3">
            @foreach($paymentMethods as $method)
              <label class="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary-50">
                <input type="radio" value="{{ $method->value }}" class="text-primary focus:ring-primary"
                  x-model="paymentMethod">
                <span class="font-medium text-gray-900">{{ $method->label() }}</span>
              </label>
            @endforeach
          </div>

          <div class="mt-4 p-4 bg-primary-50 rounded-lg text-sm" x-show="paymentMethod !== 'cod'">
            <p class="font-semibold text-gray-900">Amount to pay: <span x-text="currencySymbol + formatMoney(total)"></span></p>
            <p class="text-gray-600 mt-1">Includes shipping: <span x-text="currencySymbol + formatMoney(shipping)"></span></p>
          </div>

          <div x-show="paymentMethod === 'sslcommerz'" class="mt-4 p-4 bg-blue-50 rounded-lg text-sm text-blue-800">
            <template x-if="gatewayRedirect.sslcommerz">
              <p>You will be redirected to SSLCommerz to pay securely with card or internet banking.</p>
            </template>
            <template x-if="!gatewayRedirect.sslcommerz">
              <p class="text-red-700">SSLCommerz is not configured. Choose another payment method.</p>
            </template>
          </div>

          <div x-show="paymentMethod === 'stripe'" class="mt-4 p-4 bg-indigo-50 rounded-lg text-sm text-indigo-900">
            <template x-if="gatewayRedirect.stripe">
              <p>You will be redirected to Stripe to pay securely with international cards (Visa, Mastercard, etc.).</p>
            </template>
            <template x-if="!gatewayRedirect.stripe">
              <p class="text-red-700">Stripe is not enabled. Contact support or choose another method.</p>
            </template>
          </div>

          <div x-show="paymentMethod === 'bkash'" class="mt-4 p-4 bg-pink-50 rounded-lg space-y-3">
            <template x-if="gatewayRedirect.bkash">
              <p class="text-sm text-pink-900">You will be redirected to bKash to pay <strong x-text="currencySymbol + formatMoney(total)"></strong>.</p>
              <p class="text-xs text-pink-800">Or pay manually to <strong>{{ $merchantNumbers['bkash'] ?? 'N/A' }}</strong> and enter transaction ID below.</p>
            </template>
            <template x-if="!gatewayRedirect.bkash">
              <p class="text-sm text-pink-900">Send <strong x-text="currencySymbol + formatMoney(total)"></strong> to bKash: <strong>{{ $merchantNumbers['bkash'] ?? 'N/A' }}</strong></p>
              <p class="text-xs text-pink-800">Full amount includes product price + shipping charge.</p>
            </template>
          </div>

          <div x-show="paymentMethod === 'nagad'" class="mt-4 p-4 bg-orange-50 rounded-lg space-y-3">
            <template x-if="gatewayRedirect.nagad">
              <p class="text-sm text-orange-900">You will be redirected to Nagad to pay <strong x-text="currencySymbol + formatMoney(total)"></strong>.</p>
              <p class="text-xs text-orange-800">Or pay manually to <strong>{{ $merchantNumbers['nagad'] ?? 'N/A' }}</strong> and enter transaction ID below.</p>
            </template>
            <template x-if="!gatewayRedirect.nagad">
              <p class="text-sm text-orange-900">Send <strong x-text="currencySymbol + formatMoney(total)"></strong> to Nagad: <strong>{{ $merchantNumbers['nagad'] ?? 'N/A' }}</strong></p>
              <p class="text-xs text-orange-800">Full amount includes product price + shipping charge.</p>
            </template>
          </div>

          <div x-show="['bkash', 'nagad'].includes(paymentMethod)" class="mt-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              <span x-show="gatewayRedirect[paymentMethod]">Transaction Reference (optional for redirect)</span>
              <span x-show="!gatewayRedirect[paymentMethod]">Transaction Reference *</span>
            </label>
            <input type="text" x-model="paymentReference" class="input-field" placeholder="Enter your transaction ID"
              :name="['bkash', 'nagad'].includes(paymentMethod) ? 'payment_reference' : ''">
          </div>

          <div x-show="paymentMethod === 'cod'" class="mt-4 space-y-4">
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-900">
              <p class="font-semibold mb-2">Cash on Delivery — 2 steps:</p>
              <ol class="list-decimal list-inside space-y-1">
                <li>Pay shipping <strong x-text="currencySymbol + formatMoney(shipping)"></strong> now via bKash/Nagad</li>
                <li>Pay product price <strong x-text="currencySymbol + formatMoney(dueOnDelivery())"></strong> in cash on delivery</li>
              </ol>
            </div>

            <div x-show="shipping > 0">
              <p class="text-sm font-medium text-gray-700 mb-2">Pay shipping charge via:</p>
              <div class="flex flex-wrap gap-4 mb-3">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" name="cod_shipping_payment" value="bkash" x-model="codShippingPayment" class="text-primary focus:ring-primary">
                  <span class="text-sm">bKash ({{ $merchantNumbers['bkash'] ?? 'N/A' }})</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" name="cod_shipping_payment" value="nagad" x-model="codShippingPayment" class="text-primary focus:ring-primary">
                  <span class="text-sm">Nagad ({{ $merchantNumbers['nagad'] ?? 'N/A' }})</span>
                </label>
              </div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Shipping payment transaction ID</label>
              <input type="text" x-model="paymentReference" class="input-field" placeholder="Enter bKash/Nagad transaction ID for shipping"
                :name="paymentMethod === 'cod' && shipping > 0 ? 'payment_reference' : ''">
              <p class="text-xs text-gray-500 mt-1">Order will be confirmed after we verify your shipping payment.</p>
            </div>

            <div x-show="shipping <= 0" class="p-3 bg-green-50 rounded-lg text-sm text-green-800">
              No shipping charge — pay <strong x-text="currencySymbol + formatMoney(dueOnDelivery())"></strong> in cash on delivery.
            </div>
          </div>
        </div>
        <div class="flex gap-3">
          <button type="button" @click="step = 2" class="btn-secondary flex-1 py-3">Back</button>
          <button type="button" @click="step = 4" class="btn-primary flex-1 py-3">Review Order</button>
        </div>
      </div>

      {{-- Step 4: Review --}}
      <div x-show="step === 4" x-cloak class="space-y-6">
        <div class="card p-6">
          <h2 class="font-semibold text-gray-900 mb-4">Order Summary</h2>
          @foreach($groupedItems as $group)
            <div class="mb-4 pb-4 border-b last:border-0">
              <p class="text-sm font-medium text-primary mb-2">{{ $group['shop']->name }}</p>
              @foreach($group['items'] as $item)
                <div class="flex justify-between text-sm py-1">
                  <span class="text-gray-600">
                    {{ $item->product->name }}
                    @if($item->variant) <span class="text-gray-400">({{ $item->variant->name }})</span> @endif
                    × {{ $item->quantity }}
                  </span>
                  <span>{{ config('shipnest.currency_symbol') }}{{ number_format($item->total_price) }}</span>
                </div>
              @endforeach
            </div>
          @endforeach

          <div class="space-y-2 text-sm border-t pt-4">
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
            <div class="flex justify-between font-bold text-lg pt-2">
              <span>Total</span>
              <span class="text-primary">{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(total)"></span></span>
            </div>
          </div>

          @if($cart->coupon_code)
            <p class="text-xs text-green-600 mt-2">Coupon applied: {{ $cart->coupon_code }}</p>
          @endif
        </div>

        <div class="card p-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Order Notes (optional)</label>
          <textarea name="notes" rows="2" class="input-field" placeholder="Special instructions for delivery">{{ old('notes') }}</textarea>
        </div>

        <div class="flex gap-3">
          <button type="button" @click="step = 3" class="btn-secondary flex-1 py-3">Back</button>
          <button type="submit" class="btn-primary flex-1 py-3 text-base">Place Order</button>
        </div>
      </div>
    </form>
  </div>
</x-layouts.app>
