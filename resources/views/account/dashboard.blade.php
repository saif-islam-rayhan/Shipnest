<x-layouts.account>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Account Overview</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Total Orders', 'value' => $stats['total_orders'], 'color' => 'blue'],
            ['label' => 'Wishlist Items', 'value' => $stats['wishlist_items'], 'color' => 'pink'],
            ['label' => 'Reviews Written', 'value' => $stats['reviews_written'], 'color' => 'purple'],
            ['label' => 'Wallet Balance', 'value' => config('shipnest.currency_symbol').number_format($stats['wallet_balance']), 'color' => 'green'],
        ] as $stat)
            <div class="card p-4">
                <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h2 class="font-semibold text-gray-900">Recent Orders</h2>
            <a href="{{ route('account.orders.index') }}" class="text-sm text-primary hover:underline">View All</a>
        </div>
        @if($recentOrders->isEmpty())
            <p class="p-6 text-sm text-gray-500">No orders yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Order</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium">Items</th>
                            <th class="px-6 py-3 font-medium">Total</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($recentOrders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('account.orders.show', $order->order_number) }}" class="text-primary font-medium hover:underline">
                                        #{{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $order->created_at->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $order->items_count }}</td>
                                <td class="px-6 py-3 font-medium">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</td>
                                <td class="px-6 py-3">
                                    <span class="badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.account>
