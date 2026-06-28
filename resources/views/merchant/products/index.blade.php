@extends('layouts.merchant')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..." class="input-field w-48">
        <select name="category" class="input-field w-40">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="status" class="input-field w-36">
            <option value="">All Status</option>
            @foreach(['draft','active','inactive','out_of_stock'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-primary">Filter</button>
    </form>
    <a href="{{ route('merchant.products.create') }}" class="btn-primary">+ Add Product</a>
</div>

<form id="bulkForm" action="{{ route('merchant.products.bulk') }}" method="POST">
    @csrf
    <div class="flex gap-2 mb-3">
        <select name="bulk_action" class="input-field w-40 text-sm">
            <option value="">Bulk Action</option>
            <option value="enable">Enable</option>
            <option value="disable">Disable</option>
            <option value="delete">Delete</option>
        </select>
        <button type="submit" class="btn-outline text-sm" onclick="return confirm('Apply bulk action?')">Apply</button>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table id="productsTable" class="w-full text-sm display">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3"><input type="checkbox" id="selectAll"></th>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">SKU</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Price</th>
                    <th class="px-4 py-3 text-left">Stock</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                    <tr>
                        <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $product->id }}" class="row-check"></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded bg-gray-100 overflow-hidden">
                                    @if($product->primary_image_url)
                                        <img src="{{ $product->primary_image_url }}" class="w-full h-full object-cover">
                                    @endif
                                </div>
                                <span class="font-medium">{{ $product->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $product->sku }}</td>
                        <td class="px-4 py-3">{{ $product->category?->name }}</td>
                        <td class="px-4 py-3">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</td>
                        <td class="px-4 py-3 {{ $product->stock <= 5 ? 'text-red-600 font-medium' : '' }}">{{ $product->stock }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100">{{ $product->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1 flex-wrap">
                                <a href="{{ route('merchant.products.edit', $product) }}" class="text-xs text-[#F57C00] hover:underline">Edit</a>
                                <form action="{{ route('merchant.products.toggle', $product) }}" method="POST" class="inline">@csrf @method('PATCH')
                                    <button class="text-xs text-gray-600 hover:underline">Toggle</button>
                                </form>
                                <form action="{{ route('merchant.products.duplicate', $product) }}" method="POST" class="inline">@csrf
                                    <button class="text-xs text-gray-600 hover:underline">Duplicate</button>
                                </form>
                                <form action="{{ route('merchant.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>
<div class="mt-4">{{ $products->links() }}</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('#productsTable').DataTable({ paging: false, info: false, searching: true, order: [] });
    $('#selectAll').on('change', function() { $('.row-check').prop('checked', this.checked); });
});
</script>
@endpush
