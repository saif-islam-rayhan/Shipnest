<x-layouts.account>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Returns</h1>

    @if($eligibleItems->isNotEmpty())
        <div class="card p-6 mb-8">
            <h2 class="font-semibold text-gray-900 mb-4">Submit Return Request</h2>
            <form action="{{ route('account.returns.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Item</label>
                    <select name="order_item_id" class="input-field" required>
                        <option value="">Choose an item...</option>
                        @foreach($eligibleItems as $item)
                            <option value="{{ $item->id }}" @selected(old('order_item_id') == $item->id)>
                                #{{ $item->order->order_number }} — {{ $item->product_name }} ({{ config('shipnest.currency_symbol') }}{{ number_format($item->total) }})
                            </option>
                        @endforeach
                    </select>
                    @error('order_item_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <select name="reason" class="input-field" required>
                        <option value="">Select reason...</option>
                        @foreach(['defective' => 'Defective/Damaged', 'wrong_item' => 'Wrong Item', 'not_as_described' => 'Not as Described', 'changed_mind' => 'Changed Mind', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reason')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="input-field" placeholder="Describe the issue...">{{ old('description') }}</textarea>
                    @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary">Submit Return Request</button>
            </form>
        </div>
    @endif

    <div class="card">
        <div class="px-6 py-4 border-b"><h2 class="font-semibold text-gray-900">Return Requests</h2></div>
        @if($returns->isEmpty())
            <p class="p-6 text-sm text-gray-500">No return requests yet.</p>
        @else
            <div class="divide-y">
                @foreach($returns as $return)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <p class="font-medium text-gray-900">{{ $return->orderItem->product_name ?? 'Item' }}</p>
                            <p class="text-sm text-gray-500">
                                Order #{{ $return->order->order_number }}
                                · {{ ucfirst(str_replace('_', ' ', $return->reason)) }}
                                · {{ $return->created_at->format('M d, Y') }}
                            </p>
                            @if($return->description)
                                <p class="text-sm text-gray-600 mt-1">{{ $return->description }}</p>
                            @endif
                        </div>
                        <span class="badge {{ match($return->status) {
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            default => 'bg-yellow-100 text-yellow-800',
                        } }}">
                            {{ ucfirst($return->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="p-4">{{ $returns->links() }}</div>
        @endif
    </div>
</x-layouts.account>
