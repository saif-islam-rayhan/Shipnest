<x-layouts.app>
  <div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">All Orders</h1>
    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">Order</th>
            <th class="px-4 py-3 text-left">Customer</th>
            <th class="px-4 py-3 text-left">Shop</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-right">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @foreach($orders as $order)
            <tr>
              <td class="px-4 py-3 font-medium">#{{ $order->order_number }}</td>
              <td class="px-4 py-3">{{ $order->user->name }}</td>
              <td class="px-4 py-3">{{ $order->shop->name }}</td>
              <td class="px-4 py-3">
                <form action="{{ route('admin.orders.status', $order) }}" method="POST" class="inline-flex items-center gap-2">
                  @csrf @method('PATCH')
                  <select name="status" onchange="this.form.submit()" class="text-xs border-gray-300 rounded">
                    @foreach(\App\Enums\OrderStatus::cases() as $status)
                      <option value="{{ $status->value }}" @selected($order->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                  </select>
                </form>
              </td>
              <td class="px-4 py-3 text-right font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
  </div>
</x-layouts.app>
