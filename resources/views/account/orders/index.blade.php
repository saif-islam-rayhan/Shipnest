<x-layouts.account>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">My Orders</h1>

    <div class="flex flex-wrap gap-2 mb-6">
        @foreach($statuses as $key => $label)
            <a href="{{ route('account.orders.index', ['status' => $key]) }}"
               class="px-4 py-1.5 rounded-full text-sm font-medium transition
               {{ $status === $key ? 'bg-primary text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($orders->isEmpty())
        <div class="card p-12 text-center">
            <p class="text-gray-500">No orders found.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4 inline-block">Start Shopping</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                <div class="card p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold text-gray-900">#{{ $order->order_number }}</p>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ $order->created_at->format('M d, Y') }}
                                · {{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}
                                @if($order->shop)
                                    · {{ $order->shop->name }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="badge bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-800">
                                {{ $order->status->label() }}
                            </span>
                            <span class="font-bold text-gray-900">{{ config('shipnest.currency_symbol') }}{{ number_format($order->total) }}</span>
                            <a href="{{ route('account.orders.show', $order->order_number) }}" class="btn-outline text-sm py-1.5">View Details</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-layouts.account>
