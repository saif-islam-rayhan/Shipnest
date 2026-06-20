<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8" x-data='cartPage(@json($totals))'>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Shopping Cart</h1>

    @if($cart->items->isEmpty())
      <div class="card p-12 text-center">
        <p class="text-gray-500 mb-4">Your cart is empty.</p>
        <a href="{{ route('products.index') }}" class="btn-primary">Continue Shopping</a>
      </div>
    @else
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
          @foreach($groupedItems as $group)
            <div class="card">
              <div class="px-4 py-3 border-b bg-gray-50">
                <span class="font-medium text-primary">{{ $group['shop']->name }}</span>
              </div>
              <div class="divide-y">
                @foreach($group['items'] as $item)
                  <div class="p-4 flex gap-4">
                    <div class="w-20 h-20 flex-shrink-0 rounded bg-gray-100 overflow-hidden">
                      @if($item->product->primary_image_url)
                        <img src="{{ $item->product->primary_image_url }}" alt="{{ $item->product->name }}" class="w-full h-full object-cover">
                      @endif
                    </div>
                    <div class="flex-1 min-w-0">
                      <a href="{{ route('products.show', $item->product->slug) }}" class="text-sm font-medium text-gray-900 hover:text-primary line-clamp-2">{{ $item->product->name }}</a>
                      @if($item->variant)
                        <p class="text-xs text-gray-500 mt-0.5">{{ $item->variant->name }}</p>
                      @endif
                      <p class="text-xs text-gray-500">{{ $group['shop']->name }}</p>
                      <p class="text-primary font-bold mt-1">{{ config('shipnest.currency_symbol') }}{{ number_format($item->unit_price) }}</p>
                      <div class="mt-2 flex items-center gap-4">
                        <form action="{{ route('cart.update', $item) }}" method="POST" class="flex items-center border rounded">
                          @csrf @method('PATCH')
                          <button type="submit" name="quantity" value="{{ max(1, $item->quantity - 1) }}" class="px-2 py-1 text-gray-600 hover:bg-gray-50">−</button>
                          <span class="px-3 text-sm min-w-[2rem] text-center">{{ $item->quantity }}</span>
                          <button type="submit" name="quantity" value="{{ $item->quantity + 1 }}" class="px-2 py-1 text-gray-600 hover:bg-gray-50">+</button>
                        </form>
                        <form action="{{ route('cart.destroy', $item) }}" method="POST">
                          @csrf @method('DELETE')
                          <button type="submit" class="text-sm text-red-600 hover:underline">Remove</button>
                        </form>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="font-bold text-gray-900">{{ config('shipnest.currency_symbol') }}{{ number_format($item->total_price) }}</p>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>

        <div class="space-y-4">
          <div class="card p-6">
            <h2 class="font-semibold text-gray-900 mb-3">Coupon Code</h2>
            <div class="flex gap-2">
              <input type="text" x-model="couponCode" placeholder="Enter code" class="input-field flex-1" :disabled="couponApplied">
              <button type="button" @click="applyCoupon()" class="btn-primary px-4" x-show="!couponApplied">Apply</button>
              <button type="button" @click="removeCoupon()" class="btn-secondary px-4" x-show="couponApplied">Remove</button>
            </div>
            <p x-show="couponMessage" x-text="couponMessage" class="text-sm mt-2" :class="couponError ? 'text-red-600' : 'text-green-600'"></p>
          </div>

          <div class="card p-6 h-fit sticky top-4">
            <h2 class="font-semibold text-gray-900 mb-4">Order Summary</h2>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Subtotal (<span x-text="itemCount">{{ $totals['item_count'] }}</span> items)</span>
                <span class="font-medium">{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(subtotal)">{{ number_format($totals['subtotal']) }}</span></span>
              </div>
              <div class="flex justify-between" x-show="discount > 0">
                <span class="text-gray-600">Discount</span>
                <span class="font-medium text-green-600">−{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(discount)">{{ number_format($totals['discount']) }}</span></span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Shipping</span>
                <span class="font-medium text-gray-500">TBD at checkout</span>
              </div>
            </div>
            <div class="border-t mt-4 pt-4 flex justify-between font-bold text-lg">
              <span>Total</span>
              <span class="text-primary">{{ config('shipnest.currency_symbol') }}<span x-text="formatMoney(total)">{{ number_format($totals['total']) }}</span></span>
            </div>
            @auth
              <a href="{{ route('checkout.index') }}" class="btn-primary w-full mt-6 py-3 text-center block">Proceed to Checkout</a>
            @else
              <a href="{{ route('login') }}" class="btn-primary w-full mt-6 py-3 text-center block">Login to Checkout</a>
            @endauth
          </div>
        </div>
      </div>
    @endif
  </div>
</x-layouts.app>
