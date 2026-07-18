@extends('layouts.admin')
@section('title', 'Products')
@section('page-title', 'Product Management')

@section('content')
@include('layouts.partials.admin-page-header', [
    'subtitle' => 'Manage catalog products, approvals, and inventory.',
    'actionUrl' => route('admin.products.create'),
    'actionLabel' => '+ Add Product',
])

<form method="GET" class="admin-filter-bar mb-5">
    <input name="search" value="{{ request('search') }}" placeholder="Search products..." class="input-field w-44">
    <select name="merchant_id" class="input-field w-40">
        <option value="">All Merchants</option>
        @foreach($merchants as $m)
            <option value="{{ $m->id }}" @selected(request('merchant_id') == $m->id)>{{ $m->shop_name }}</option>
        @endforeach
    </select>
    <select name="approval_status" class="input-field w-36">
        <option value="">All Approval</option>
        @foreach(['pending', 'approved', 'rejected'] as $s)
            <option value="{{ $s }}" @selected(request('approval_status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <select name="status" class="input-field w-32">
        <option value="">Status</option>
        @foreach(['draft', 'active', 'inactive', 'out_of_stock'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-primary">Filter</button>
    @if(request()->hasAny(['search', 'merchant_id', 'approval_status', 'status']))
        <a href="{{ route('admin.products.index') }}" class="btn-outline">Clear</a>
    @endif
</form>

<div class="admin-card">
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Barcode</th>
                    <th>Merchant</th>
                    <th>Price</th>
                    <th>Discount</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                @if($p->primary_image_url)
                                    <img src="{{ $p->primary_image_url }}" alt="" class="h-10 w-10 shrink-0 rounded-lg bg-gray-100 object-cover">
                                @else
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs text-gray-400">—</div>
                                @endif
                                <div>
                                    <span class="font-medium text-gray-900">{{ $p->name }}</span>
                                    <div class="text-xs text-gray-400">SKU: {{ $p->sku ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @php
                                $barcode = $p->defaultVariant?->barcode
                                    ?? $p->variants->first()?->barcode
                                    ?? $p->sku;
                            @endphp
                            @if($barcode)
                                <div class="inline-flex flex-col items-start gap-0.5 rounded border border-slate-200 bg-white p-1.5">
                                    <svg class="jsbarcode" data-barcode="{{ $barcode }}"></svg>
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="text-gray-600">{{ $p->merchant?->shop_name ?? '—' }}</td>
                        <td class="font-medium text-gray-900">{{ config('shipnest.currency_symbol') }}{{ number_format($p->price) }}</td>
                        <td>
                            @if($p->discount_percent)
                                <span class="admin-badge bg-emerald-50 text-emerald-700">-{{ $p->discount_percent }}%</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $statusTone = match($p->status) {
                                    \App\Enums\ProductStatus::Active => 'bg-emerald-50 text-emerald-700',
                                    \App\Enums\ProductStatus::Draft => 'bg-gray-100 text-gray-700',
                                    \App\Enums\ProductStatus::Inactive => 'bg-slate-100 text-slate-600',
                                    \App\Enums\ProductStatus::OutOfStock => 'bg-red-50 text-red-700',
                                };
                            @endphp
                            <span class="admin-badge {{ $statusTone }}">
                                {{ $p->status->label() }}
                            </span>
                        </td>
                        <td>
                            @php $approval = $p->approval_status ?? 'approved'; @endphp
                            <span class="admin-badge {{ $approval === 'pending' ? 'bg-amber-50 text-amber-700' : ($approval === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700') }}">
                                {{ ucfirst($approval) }}
                            </span>
                        </td>
                        <td>
                            <div class="flex flex-wrap justify-end gap-2 text-xs">
                                <a href="{{ route('admin.products.edit', $p) }}" class="admin-link">Edit</a>
                                @if(($p->approval_status ?? 'approved') === 'pending')
                                    <form action="{{ route('admin.products.approve', $p) }}" method="POST" class="inline">@csrf @method('PATCH')
                                        <button type="submit" class="font-medium text-emerald-600 hover:underline">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.products.reject', $p) }}" method="POST" class="inline">@csrf
                                        <button type="submit" class="font-medium text-red-600 hover:underline">Reject</button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.products.feature', $p) }}" method="POST" class="inline">@csrf @method('PATCH')
                                    <button type="submit" class="text-gray-600 hover:text-gray-900">{{ $p->is_featured ? 'Unfeature' : 'Feature' }}</button>
                                </form>
                                <form action="{{ route('admin.products.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('Delete this product?')">@csrf @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="admin-empty">No products found. <a href="{{ route('admin.products.create') }}" class="admin-link">Add your first product</a></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($products->hasPages())
    <div class="mt-4">{{ $products->links() }}</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.querySelectorAll('svg.jsbarcode[data-barcode]').forEach((el) => {
    const value = el.getAttribute('data-barcode');
    if (!value) return;
    try {
        JsBarcode(el, value, {
            format: 'CODE128',
            width: 1.4,
            height: 40,
            displayValue: true,
            fontSize: 11,
            margin: 2,
            background: '#ffffff',
            lineColor: '#0f172a',
        });
    } catch (e) {
        el.replaceWith(Object.assign(document.createElement('span'), {
            className: 'font-mono text-xs text-slate-600',
            textContent: value,
        }));
    }
});
</script>
@endpush
