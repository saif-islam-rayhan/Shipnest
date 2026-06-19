<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">My Orders</h1>
    @if($orders->isEmpty())
      <div class="card p-12 text-center">
        <p class="text-gray-500">You haven't placed any orders yet.</p>
        <a href="{{ route('products.index') }}" class="btn-primary mt-4 inline-block">Start Shopping</a>
      </div>
    @else
      <div class="space-y-4">
        @foreach($orders as $order)
          <div class="card p-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <a href="{{ route('orders.show', $order) }}" class="font-medium text-primary hover:underline">#{{ $order->order_number }}</a>
                <p class="text-sm text-gray-500">{{ $order->shop->name }} · {{ $order->created_at->format('M d, Y') }}</p>
              </div>
              <div class="flex items-center gap-4">
                <span class="badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">{{ $order->status->label() }}</span>
                <span class="font-bold">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>
      <div class="mt-6">{{ $orders->links() }}</div>
    @endif
  </div>
</x-layouts.app>
