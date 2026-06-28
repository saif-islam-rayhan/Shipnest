<x-layouts.account>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('account.orders.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Orders</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Order #{{ $order->order_number }}</h1>
        </div>
        <span class="badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800 text-sm self-start">
            {{ $order->status->label() }}
        </span>
    </div>

    {{-- Status Timeline --}}
    @if($order->status !== \App\Enums\OrderStatus::Cancelled)
        <div class="card p-6 mb-6">
            <h2 class="font-semibold text-gray-900 mb-6">Order Status</h2>
            @php
                $statusOrder = ['pending', 'confirmed', 'shipped', 'delivered'];
                $currentIndex = array_search($order->status->value, $statusOrder);
                if ($currentIndex === false && $order->status === \App\Enums\OrderStatus::Processing) {
                    $currentIndex = 1;
                }
            @endphp
            <div class="flex items-center justify-between">
                @foreach($timelineSteps as $i => $step)
                    @php
                        $stepIndex = array_search($step->value, $statusOrder);
                        $isComplete = $currentIndex !== false && $stepIndex !== false && $stepIndex <= $currentIndex;
                        $isCurrent = $order->status === $step || ($order->status === \App\Enums\OrderStatus::Processing && $step === \App\Enums\OrderStatus::Confirmed);
                    @endphp
                    <div class="flex flex-col items-center flex-1 relative">
                        @if($i > 0)
                            <div class="absolute top-4 right-1/2 w-full h-0.5 -translate-y-1/2 {{ $isComplete ? 'bg-primary' : 'bg-gray-200' }}"></div>
                        @endif
                        <div class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
                            {{ $isComplete ? 'bg-primary text-white' : 'bg-gray-200 text-gray-500' }}">
                            @if($isComplete && ! $isCurrent)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </div>
                        <span class="text-xs mt-2 text-center {{ $isComplete ? 'text-primary font-medium' : 'text-gray-500' }}">{{ $step->label() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="card p-4 mb-6 bg-red-50 border-red-200">
            <p class="text-red-800 font-medium">This order has been cancelled.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="card p-6">
            <h2 class="font-semibold text-gray-900 mb-3">Payment Info</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Method</dt><dd>{{ $order->payment_method->label() }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd>{{ $order->payment_status->label() }}</dd></div>
                @if($order->payment_reference)
                    <div class="flex justify-between"><dt class="text-gray-500">Reference</dt><dd>{{ $order->payment_reference }}</dd></div>
                @endif
            </dl>
        </div>
        <div class="card p-6">
            <h2 class="font-semibold text-gray-900 mb-3">Shipping Address</h2>
            @if($order->shippingAddress)
                <p class="text-sm text-gray-600">
                    {{ $order->shippingAddress->recipient_name }}<br>
                    {{ $order->shippingAddress->full_address }}<br>
                    {{ $order->shippingAddress->phone }}
                </p>
            @else
                <p class="text-sm text-gray-500">No shipping address on file.</p>
            @endif
        </div>
    </div>

    <div class="card mb-6">
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
                        @if($item->variant_name)
                            <p class="text-xs text-gray-500">{{ $item->variant_name }}</p>
                        @endif
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

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('account.orders.invoice', $order->order_number) }}" class="btn-outline">
            Download Invoice
        </a>
        @if($order->canBeCancelled())
            <form action="{{ route('account.orders.cancel', $order->order_number) }}" method="POST"
                  onsubmit="return confirm('Are you sure you want to cancel this order?')">
                @csrf
                <button type="submit" class="btn-outline text-red-600 border-red-300 hover:bg-red-50">Cancel Order</button>
            </form>
        @endif
        @if($order->canRequestReturn())
            <a href="{{ route('account.returns.index') }}" class="btn-primary">Request Return</a>
        @endif
    </div>
</x-layouts.account>
