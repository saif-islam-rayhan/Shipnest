<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Admin Dashboard</h1>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
      @foreach([
        ['label' => 'Users', 'value' => $stats['total_users'], 'color' => 'blue'],
        ['label' => 'Shops', 'value' => $stats['total_shops'], 'color' => 'green'],
        ['label' => 'Pending Shops', 'value' => $stats['pending_shops'], 'color' => 'yellow'],
        ['label' => 'Products', 'value' => $stats['total_products'], 'color' => 'purple'],
        ['label' => 'Orders', 'value' => $stats['total_orders'], 'color' => 'indigo'],
        ['label' => 'Revenue', 'value' => config('shipnest.currency_symbol').number_format($stats['revenue']), 'color' => 'primary'],
      ] as $stat)
        <div class="card p-4">
          <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stat['value'] }}</p>
        </div>
      @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <div class="card">
        <div class="px-6 py-4 border-b flex justify-between items-center">
          <h2 class="font-semibold">Pending Shop Approvals</h2>
          <a href="{{ route('admin.shops.index', ['status' => 'pending']) }}" class="text-sm text-primary">View All</a>
        </div>
        <div class="divide-y">
          @forelse($pendingShops as $shop)
            <div class="p-4 flex justify-between items-center">
              <div>
                <p class="font-medium">{{ $shop->name }}</p>
                <p class="text-sm text-gray-500">{{ $shop->owner->name }}</p>
              </div>
              <form action="{{ route('admin.shops.approve', $shop) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit" class="btn-primary text-xs py-1 px-3">Approve</button>
              </form>
            </div>
          @empty
            <p class="p-4 text-sm text-gray-500">No pending shops.</p>
          @endforelse
        </div>
      </div>

      <div class="card">
        <div class="px-6 py-4 border-b flex justify-between items-center">
          <h2 class="font-semibold">Recent Orders</h2>
          <a href="{{ route('admin.orders.index') }}" class="text-sm text-primary">View All</a>
        </div>
        <div class="divide-y">
          @foreach($recentOrders as $order)
            <div class="p-4 flex justify-between">
              <div>
                <p class="font-medium text-sm">#{{ $order->order_number }}</p>
                <p class="text-xs text-gray-500">{{ $order->user->name }} · {{ $order->shop->name }}</p>
              </div>
              <span class="text-sm font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</x-layouts.app>
