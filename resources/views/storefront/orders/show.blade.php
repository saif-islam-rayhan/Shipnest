<x-layouts.app>
  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Order #{{ $order->order_number }}</h1>
      <span class="badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800 text-sm">{{ $order->status->label() }}</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <div class="card p-6">
        <h2 class="font-semibold text-gray-900 mb-3">Order Details</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd>{{ $order->created_at->format('M d, Y H:i') }}</dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Seller</dt><dd>{{ $order->shop->name }}</dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Payment</dt><dd>{{ $order->payment_method->label() }}</dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Payment Status</dt><dd>{{ $order->payment_status->label() }}</dd></div>
          @if($order->tracking_number)
            <div class="flex justify-between"><dt class="text-gray-500">Tracking</dt><dd>{{ $order->tracking_number }}</dd></div>
          @endif
        </dl>
      </div>
      <div class="card p-6">
        <h2 class="font-semibold text-gray-900 mb-3">Shipping Address</h2>
        @if($order->shipping_address)
          <p class="text-sm text-gray-600">
            {{ $order->shipping_address['name'] }}<br>
            {{ $order->shipping_address['address_line_1'] }}<br>
            @if($order->shipping_address['address_line_2']){{ $order->shipping_address['address_line_2'] }}<br>@endif
            {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['district'] }}<br>
            {{ $order->shipping_address['phone'] }}
          </p>
        @endif
      </div>
    </div>

    <div class="card">
      <div class="px-6 py-4 border-b"><h2 class="font-semibold text-gray-900">Items</h2></div>
      <div class="divide-y">
        @foreach($order->items as $item)
          <div class="p-4 flex gap-4">
            <div class="w-16 h-16 rounded bg-gray-100 overflow-hidden flex-shrink-0">
              @if($item->product_image)
                <img src="{{ asset('storage/'.$item->product_image) }}" alt="" class="w-full h-full object-cover">
              @endif
            </div>
            <div class="flex-1">
              <p class="font-medium text-gray-900">{{ $item->product_name }}</p>
              <p class="text-sm text-gray-500">Qty: {{ $item->quantity }} × {{ config('shipnest.currency_symbol') }}{{ number_format($item->unit_price) }}</p>
            </div>
            <p class="font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($item->total_price) }}</p>
          </div>
        @endforeach
      </div>
      <div class="px-6 py-4 border-t bg-gray-50 space-y-2 text-sm">
        <div class="flex justify-between"><span>Subtotal</span><span>{{ config('shipnest.currency_symbol') }}{{ number_format($order->subtotal) }}</span></div>
        <div class="flex justify-between"><span>Shipping</span><span>{{ config('shipnest.currency_symbol') }}{{ number_format($order->shipping_fee) }}</span></div>
        @if($order->discount > 0)
          <div class="flex justify-between text-green-600"><span>Discount</span><span>-{{ config('shipnest.currency_symbol') }}{{ number_format($order->discount) }}</span></div>
        @endif
        <div class="flex justify-between font-bold text-base pt-2 border-t"><span>Total</span><span class="text-primary">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</span></div>
      </div>
    </div>
  </div>
</x-layouts.app>
