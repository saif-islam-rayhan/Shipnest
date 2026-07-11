<x-layouts.account>
    <div class="mb-6">
        <a href="{{ route('account.addresses.index') }}" class="text-sm text-primary hover:underline">&larr; Back to Addresses</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $address->exists ? 'Edit Address' : 'Add Address' }}</h1>
    </div>

    <div class="card p-6 max-w-2xl">
        <form action="{{ $address->exists ? route('account.addresses.update', $address) : route('account.addresses.store') }}"
              method="POST" class="space-y-4">
            @csrf
            @if($address->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                    <input type="text" name="label" value="{{ old('label', $address->label ?? 'Home') }}" class="input-field" required>
                    @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Name</label>
                    <input type="text" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name ?? auth()->user()->name) }}" class="input-field" required>
                    @error('recipient_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $address->phone ?? auth()->user()->phone) }}" class="input-field" required>
                    @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $address->postal_code) }}" class="input-field">
                    @error('postal_code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" name="address_line1" value="{{ old('address_line1', $address->address_line1) }}" class="input-field" required>
                    @error('address_line1')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city', $address->city) }}" class="input-field" required>
                    @error('city')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                    <input type="text" name="district" value="{{ old('district', $address->district) }}" class="input-field" required>
                    @error('district')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Thana</label>
                    <input type="text" name="thana" value="{{ old('thana', $address->thana) }}" class="input-field">
                    @error('thana')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <x-map-address-picker
                prefix=""
                :latitude="old('latitude', $address->latitude)"
                :longitude="old('longitude', $address->longitude)"
            />

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_default" value="1" id="is_default" class="rounded text-primary focus:ring-primary"
                    @checked(old('is_default', $address->is_default))>
                <label for="is_default" class="text-sm text-gray-700">Set as default address</label>
            </div>

            <button type="submit" class="btn-primary">{{ $address->exists ? 'Update Address' : 'Save Address' }}</button>
        </form>
    </div>
</x-layouts.account>
