@extends('layouts.admin')
@section('title','Promotions') @section('page-title','Promotions')
@section('content')
<div class="flex gap-2 mb-4">@foreach(['banners'=>'Banners','flash'=>'Flash Sales','coupons'=>'Coupons'] as $key=>$label)
    <a href="?tab={{ $key }}" class="px-4 py-1.5 rounded-full text-sm {{ $tab===$key ? 'bg-[#F57C00] text-white' : 'bg-white ring-1 ring-gray-200' }}">{{ $label }}</a>
@endforeach</div>

@if($tab==='banners')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form action="{{ route('admin.promotions.banners.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl ring-1 ring-gray-200 p-5 space-y-3">@csrf
        <h2 class="font-semibold">Add Banner</h2>
        <input name="title" placeholder="Title" class="input-field" required>
        <input name="link" placeholder="Link URL" class="input-field">
        <select name="position" class="input-field"><option value="homepage_hero">Homepage Hero</option><option value="category">Category</option><option value="sidebar">Sidebar</option></select>
        <input type="datetime-local" name="starts_at" class="input-field"><input type="datetime-local" name="ends_at" class="input-field">
        <input type="file" name="image" accept="image/*" required class="text-sm">
        <button class="btn-primary w-full">Create Banner</button>
    </form>
    <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-5 divide-y">
        @foreach($banners as $b)<div class="py-3 flex justify-between items-center"><div><p class="font-medium">{{ $b->title }}</p><p class="text-xs text-gray-500">{{ $b->position }}</p></div>
            <form action="{{ route('admin.promotions.banners.destroy', $b) }}" method="POST">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form></div>@endforeach
    </div>
</div>
@elseif($tab==='flash')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <form action="{{ route('admin.promotions.flash-sales.store') }}" method="POST" class="bg-white rounded-xl ring-1 ring-gray-200 p-5 space-y-3">@csrf
        <h2 class="font-semibold">Create Flash Sale</h2>
        <input name="title" placeholder="Title" class="input-field" required>
        <input type="datetime-local" name="starts_at" class="input-field" required><input type="datetime-local" name="ends_at" class="input-field" required>
        <button class="btn-primary w-full">Create</button>
    </form>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">@foreach($flashSales as $fs)
        <div class="py-3 border-b"><p class="font-medium">{{ $fs->title }}</p><p class="text-xs text-gray-500">{{ $fs->starts_at?->format('M d') }} — {{ $fs->ends_at?->format('M d') }} · {{ $fs->products_count }} products</p>
            <form action="{{ route('admin.promotions.flash-sales.products', $fs) }}" method="POST" class="mt-2 flex gap-2 flex-wrap">@csrf
                <input name="product_id" placeholder="Product ID" class="input-field text-xs w-24" required>
                <select name="discount_type" class="input-field text-xs w-28"><option value="percentage">Percent</option><option value="fixed">Fixed</option></select>
                <input name="discount_value" placeholder="Discount" class="input-field text-xs w-20" required>
                <input name="stock" placeholder="Stock" class="input-field text-xs w-16" required>
                <button class="btn-outline text-xs">Add Product</button>
            </form>
        </div>
    @endforeach</div>
</div>
@else
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form action="{{ route('admin.promotions.coupons.store') }}" method="POST" class="bg-white rounded-xl ring-1 ring-gray-200 p-5 space-y-3">@csrf
        <h2 class="font-semibold">Create Coupon</h2>
        <input name="code" placeholder="CODE" class="input-field uppercase" required>
        <select name="type" class="input-field"><option value="percentage">Percent</option><option value="fixed">Flat</option></select>
        <input name="value" type="number" step="0.01" placeholder="Value" class="input-field" required>
        <input name="min_order" type="number" placeholder="Min order" class="input-field">
        <input name="max_discount" type="number" placeholder="Max discount" class="input-field">
        <input name="usage_limit" type="number" placeholder="Usage limit" class="input-field">
        <input type="datetime-local" name="expires_at" class="input-field">
        <button class="btn-primary w-full">Create Coupon</button>
    </form>
    <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <table class="w-full text-sm"><thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Code</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Value</th><th class="px-3 py-2">Used</th><th class="px-3 py-2"></th></tr></thead>
        <tbody>@foreach($coupons as $c)<tr><td class="px-3 py-2 font-mono">{{ $c->code }}</td><td class="px-3 py-2">{{ $c->type }}</td><td class="px-3 py-2">{{ $c->value }}</td><td class="px-3 py-2">{{ $c->used_count }}/{{ $c->usage_limit ?? '∞' }}</td>
            <td class="px-3 py-2"><form action="{{ route('admin.promotions.coupons.destroy', $c) }}" method="POST">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form></td></tr>@endforeach</tbody></table>
        <div class="mt-4">{{ $coupons->links() }}</div>
    </div>
</div>
@endif
@endsection
