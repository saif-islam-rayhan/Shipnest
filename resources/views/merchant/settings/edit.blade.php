@extends('layouts.merchant')

@section('title', 'Store Settings')
@section('page-title', 'Store Settings')

@section('content')
<form action="{{ route('merchant.settings.update') }}" method="POST" enctype="multipart/form-data" class="max-w-3xl space-y-8">
    @csrf @method('PUT')

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <h2 class="font-semibold text-gray-900">Shop Information</h2>
        <div>
            <label class="block text-sm font-medium mb-1">Shop Name *</label>
            <input type="text" name="shop_name" value="{{ old('shop_name', $shop->shop_name) }}" class="input-field" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Description</label>
            <textarea name="description" rows="4" class="input-field">{{ old('description', $shop->description) }}</textarea>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Logo</label>
                @if($shop->logo)<img src="{{ asset('storage/'.$shop->logo) }}" class="w-16 h-16 rounded mb-2 object-cover">@endif
                <input type="file" name="logo" accept="image/*" class="text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Banner</label>
                @if($shop->banner)<img src="{{ asset('storage/'.$shop->banner) }}" class="w-full h-20 rounded mb-2 object-cover">@endif
                <input type="file" name="banner" accept="image/*" class="text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <h2 class="font-semibold text-gray-900">Contact Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $shop->phone) }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">District</label>
                <input type="text" name="district" value="{{ old('district', $shop->district) }}" class="input-field">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Address</label>
                <input type="text" name="address" value="{{ old('address', $shop->address) }}" class="input-field">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <h2 class="font-semibold text-gray-900">Business Documents</h2>
        <div>
            <label class="block text-sm font-medium mb-1">NID Number</label>
            <input type="text" name="nid_number" value="{{ old('nid_number', $shop->nid_number) }}" class="input-field">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Trade License</label>
            @if($shop->trade_license)
                <a href="{{ asset('storage/'.$shop->trade_license) }}" target="_blank" class="text-sm text-[#F57C00] block mb-2">View current document</a>
            @endif
            <input type="file" name="trade_license" accept=".pdf,.jpg,.jpeg,.png" class="text-sm">
        </div>
    </div>

    <button type="submit" class="btn-primary">Save Settings</button>
</form>
@endsection
