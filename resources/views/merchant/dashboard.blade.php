<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Seller Center — {{ $shop->name }}</h1>
      <span class="badge bg-{{ $shop->status->color() }}-100 text-{{ $shop->status->color() }}-800">{{ $shop->status->label() }}</span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      @foreach([
        ['label' => 'Products', 'value' => $stats['total_products']],
        ['label' => 'Total Orders', 'value' => $stats['total_orders']],
        ['label' => 'Pending Orders', 'value' => $stats['pending_orders']],
        ['label' => 'Revenue', 'value' => config('shipnest.currency_symbol').number_format($stats['revenue'])],
      ] as $stat)
        <div class="card p-4">
          <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stat['value'] }}</p>
        </div>
      @endforeach
    </div>

    <div class="flex gap-3 mb-6">
      <a href="{{ route('merchant.products.create') }}" class="btn-primary">Add Product</a>
      <a href="{{ route('merchant.products.index') }}" class="btn-outline">Manage Products</a>
      <a href="{{ route('merchant.orders.index') }}" class="btn-outline">View Orders</a>
    </div>

    <div class="card">
      <div class="px-6 py-4 border-b"><h2 class="font-semibold">Recent Orders</h2></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">Order</th>
              <th class="px-4 py-3 text-left">Customer</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-right">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            @forelse($recentOrders as $order)
              <tr>
                <td class="px-4 py-3 font-medium">#{{ $order->order_number }}</td>
                <td class="px-4 py-3">{{ $order->user->name }}</td>
                <td class="px-4 py-3"><span class="badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">{{ $order->status->label() }}</span></td>
                <td class="px-4 py-3 text-right font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No orders yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</x-layouts.app>
