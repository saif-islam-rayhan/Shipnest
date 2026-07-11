<x-layouts.app>
  @php
    $isConfirmed = $order->status === \App\Enums\OrderStatus::Confirmed;
    $isCod = $order->payment_method === \App\Enums\PaymentMethod::Cod;
    $dueOnDelivery = $order->amount_due_on_delivery;
    $symbol = config('shipnest.currency_symbol', '৳');
  @endphp
  <div class="max-w-md mx-auto px-4 py-4 sm:py-6 text-center">
    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full {{ $isConfirmed ? 'bg-green-100' : 'bg-yellow-100' }}">
      @if($isConfirmed)
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
      @else
        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      @endif
    </div>

    <h1 class="text-xl font-bold text-gray-900 mt-2">
      {{ $isConfirmed ? 'Order Confirmed!' : 'Order Received' }}
    </h1>
    <p class="text-sm text-gray-600 mt-0.5 mb-3">
      @if($isConfirmed)
        Thank you for shopping with {{ config('shipnest.name') }}.
      @else
        We received your order. It will be confirmed after payment verification.
      @endif
    </p>

    <div class="card p-3 sm:p-4 mb-4 text-left space-y-1.5">
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Order Number</span>
        <span class="font-semibold text-gray-900">{{ $order->order_number }}</span>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Subtotal</span>
        <span class="font-medium text-gray-900">{{ $symbol }}{{ number_format($order->subtotal) }}</span>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Shipping</span>
        <span class="font-medium text-gray-900">{{ $symbol }}{{ number_format($order->shipping_charge) }}</span>
      </div>
      @if($order->discount > 0)
        <div class="flex justify-between text-sm">
          <span class="text-gray-500">Discount</span>
          <span class="font-medium text-green-600">−{{ $symbol }}{{ number_format($order->discount) }}</span>
        </div>
      @endif
      @if($isCod && $order->shipping_charge > 0)
        <div class="flex justify-between text-sm">
          <span class="text-gray-500">Pay on delivery</span>
          <span class="font-medium text-gray-900">{{ $symbol }}{{ number_format($dueOnDelivery) }}</span>
        </div>
      @endif
      <div class="flex justify-between text-sm border-t pt-1.5">
        <span class="text-gray-500 font-semibold">Order Total</span>
        <span class="font-bold text-primary">{{ $symbol }}{{ number_format($order->total) }}</span>
      </div>
      @if($isCod)
        <p class="text-xs text-green-700 bg-green-50 px-2 py-1.5 rounded">Pay {{ $symbol }}{{ number_format($dueOnDelivery) }} in cash when your order arrives.</p>
      @endif
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Estimated Delivery</span>
        <span class="font-medium text-gray-900">{{ $estimatedDelivery }}</span>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Payment</span>
        <span class="font-medium text-gray-900">{{ $order->payment_method->label() }}</span>
      </div>
      <div class="flex justify-between text-sm">
        <span class="text-gray-500">Status</span>
        <span class="font-medium text-gray-900">{{ $order->status->label() }}</span>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-2 justify-center">
      <a href="{{ route('products.index') }}" class="btn-primary h-10 px-5 text-sm inline-flex items-center justify-center">Continue Shopping</a>
      <a href="{{ route('account.orders.index') }}" class="btn-secondary h-10 px-5 text-sm inline-flex items-center justify-center">View My Orders</a>
    </div>
  </div>
</x-layouts.app>
