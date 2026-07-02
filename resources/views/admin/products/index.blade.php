@extends('layouts.admin')
@section('title','Products') @section('page-title','Product Management')
@section('content')
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-2">
        <input name="search" value="{{ request('search') }}" placeholder="Search..." class="input-field w-44">
        <select name="merchant_id" class="input-field w-40">
            <option value="">All Merchants</option>
            @foreach($merchants as $m)
                <option value="{{ $m->id }}" @selected(request('merchant_id') == $m->id)>{{ $m->shop_name }}</option>
            @endforeach
        </select>
        <select name="approval_status" class="input-field w-36">
            <option value="">All Approval</option>
            @foreach(['pending','approved','rejected'] as $s)
                <option value="{{ $s }}" @selected(request('approval_status')===$s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="status" class="input-field w-32">
            <option value="">Status</option>
            @foreach(['draft','active','inactive','out_of_stock'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
        <button class="btn-primary">Filter</button>
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn-primary whitespace-nowrap">+ Add Product</a>
</div>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
<table class="admin-datatable w-full text-sm"><thead class="bg-gray-50"><tr>
    <th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3">Merchant</th><th class="px-4 py-3">Price</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Approval</th><th class="px-4 py-3">Actions</th>
</tr></thead><tbody>@foreach($products as $p)<tr>
    <td class="px-4 py-3">
        <div class="flex items-center gap-3">
            @if($p->primary_image_url)
                <img src="{{ $p->primary_image_url }}" alt="" class="w-10 h-10 rounded-lg object-cover bg-gray-100 shrink-0">
            @else
                <div class="w-10 h-10 rounded-lg bg-gray-100 shrink-0"></div>
            @endif
            <span class="font-medium">{{ $p->name }}</span>
        </div>
    </td>
    <td class="px-4 py-3">{{ $p->merchant?->shop_name }}</td>
    <td class="px-4 py-3">{{ config('shipnest.currency_symbol') }}{{ number_format($p->price) }}</td>
    <td class="px-4 py-3">@if($p->discount_percent)<span class="text-green-600">-{{ $p->discount_percent }}%</span>@else—@endif</td>
    <td class="px-4 py-3">{{ $p->status->label() }}</td>
    <td class="px-4 py-3">{{ ucfirst($p->approval_status ?? 'approved') }}</td>
    <td class="px-4 py-3 flex gap-2 text-xs flex-wrap">
        <a href="{{ route('admin.products.edit', $p) }}" class="text-[#F57C00]">Edit</a>
        @if(($p->approval_status ?? 'approved')==='pending')
            <form action="{{ route('admin.products.approve', $p) }}" method="POST">@csrf @method('PATCH')<button class="text-green-600">Approve</button></form>
            <form action="{{ route('admin.products.reject', $p) }}" method="POST">@csrf<button class="text-red-600">Reject</button></form>
        @endif
        <form action="{{ route('admin.products.feature', $p) }}" method="POST">@csrf @method('PATCH')<button>{{ $p->is_featured ? 'Unfeature' : 'Feature' }}</button></form>
        <form action="{{ route('admin.products.destroy', $p) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
    </td>
</tr>@endforeach</tbody></table></div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
