@extends('layouts.admin')
@section('title','Merchants') @section('page-title','Merchant Management')
@section('content')
<div class="flex gap-2 mb-4 flex-wrap">
    @foreach(['pending'=>'Applications ('.$pendingCount.')','active'=>'Active','suspended'=>'Suspended','rejected'=>'Rejected'] as $key=>$label)
        <a href="{{ route('admin.merchants.index',['tab'=>$key]) }}" class="px-4 py-1.5 rounded-full text-sm {{ $tab===$key ? 'bg-[#F57C00] text-white' : 'bg-white ring-1 ring-gray-200' }}">{{ $label }}</a>
    @endforeach
    <a href="{{ route('admin.merchants.payouts') }}" class="px-4 py-1.5 rounded-full text-sm bg-white ring-1 ring-gray-200 ml-auto">Payout Requests</a>
</div>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
<table class="admin-datatable w-full text-sm"><thead class="bg-gray-50"><tr>
    <th class="px-4 py-3 text-left">Shop</th><th class="px-4 py-3 text-left">Owner</th><th class="px-4 py-3">Products</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
</tr></thead><tbody>@foreach($merchants as $m)<tr>
    <td class="px-4 py-3 font-medium">{{ $m->shop_name }}</td>
    <td class="px-4 py-3">{{ $m->owner->name ?? '—' }}</td>
    <td class="px-4 py-3">{{ $m->products_count }}</td>
    <td class="px-4 py-3">{{ $m->status->label() }}</td>
    <td class="px-4 py-3 flex gap-2 flex-wrap text-xs">
        <a href="{{ route('admin.merchants.show', $m) }}" class="text-[#F57C00]">View</a>
        @if($m->status->value==='pending')
            <form action="{{ route('admin.merchants.approve', $m) }}" method="POST" class="inline">@csrf @method('PATCH')<button class="text-green-600">Approve</button></form>
            <button type="button" onclick="document.getElementById('reject-{{ $m->id }}').classList.toggle('hidden')" class="text-red-600">Reject</button>
        @endif
        @if($m->status->value==='active')<form action="{{ route('admin.merchants.suspend', $m) }}" method="POST" class="inline">@csrf @method('PATCH')<button>Suspend</button></form>@endif
    </td>
</tr>
<tr id="reject-{{ $m->id }}" class="hidden bg-red-50"><td colspan="5" class="px-4 py-3">
    <form action="{{ route('admin.merchants.reject', $m) }}" method="POST" class="flex gap-2">@csrf
        <input name="reason" placeholder="Rejection reason..." class="input-field flex-1" required>
        <button class="btn-primary text-sm">Submit Reject</button>
    </form>
</td></tr>
@endforeach</tbody></table></div>
<div class="mt-4">{{ $merchants->links() }}</div>
@endsection
