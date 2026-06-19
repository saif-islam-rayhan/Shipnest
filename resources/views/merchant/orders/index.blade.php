<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Shop Orders</h1>
    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">Order</th>
            <th class="px-4 py-3 text-left">Customer</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Payment</th>
            <th class="px-4 py-3 text-right">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($orders as $order)
            <tr>
              <td class="px-4 py-3 font-medium">#{{ $order->order_number }}</td>
              <td class="px-4 py-3">{{ $order->user->name }}</td>
              <td class="px-4 py-3"><span class="badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">{{ $order->status->label() }}</span></td>
              <td class="px-4 py-3">{{ $order->payment_method->label() }}</td>
              <td class="px-4 py-3 text-right font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No orders yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
  </div>
</x-layouts.app>
