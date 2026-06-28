<x-layouts.account>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Saved Addresses</h1>
        <a href="{{ route('account.addresses.create') }}" class="btn-primary text-sm">Add Address</a>
    </div>

    @if($addresses->isEmpty())
        <div class="card p-12 text-center">
            <p class="text-gray-500">No saved addresses yet.</p>
            <a href="{{ route('account.addresses.create') }}" class="btn-primary mt-4 inline-block">Add Address</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($addresses as $address)
                <div class="card p-5 {{ $address->is_default ? 'ring-2 ring-primary' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-900">{{ $address->label }}</span>
                                @if($address->is_default)
                                    <span class="badge bg-primary-100 text-primary-800">Default</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-900 mt-2">{{ $address->recipient_name }}</p>
                            <p class="text-sm text-gray-600">{{ $address->full_address }}</p>
                            <p class="text-sm text-gray-500">{{ $address->phone }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="btn-outline text-xs py-1.5">Edit</a>
                        @unless($address->is_default)
                            <form action="{{ route('account.addresses.default', $address) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-outline text-xs py-1.5">Set Default</button>
                            </form>
                        @endunless
                        <form action="{{ route('account.addresses.destroy', $address) }}" method="POST"
                              onsubmit="return confirm('Delete this address?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-outline text-xs py-1.5 text-red-600 border-red-200">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.account>
