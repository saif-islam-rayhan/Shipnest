@extends('layouts.admin')
@section('title','Users') @section('page-title','User Management')
@section('content')
<form method="GET" class="flex flex-wrap gap-2 mb-4">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="input-field w-48">
    <select name="role" class="input-field w-36"><option value="">All Roles</option>@foreach($roles as $r)<option value="{{ $r }}" @selected(request('role')===$r)>{{ ucfirst($r) }}</option>@endforeach</select>
    <select name="status" class="input-field w-36"><option value="">All Status</option>@foreach(['active','inactive','suspended'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
    <button class="btn-primary">Filter</button>
</form>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
<table class="admin-datatable w-full text-sm"><thead class="bg-gray-50"><tr>
    <th class="px-4 py-3 text-left">Name</th><th class="px-4 py-3 text-left">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
</tr></thead><tbody>@foreach($users as $user)<tr>
    <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
    <td class="px-4 py-3">{{ $user->email }}</td>
    <td class="px-4 py-3">{{ $user->roles->first()?->name ?? '—' }}</td>
    <td class="px-4 py-3"><span class="badge bg-gray-100">{{ $user->status }}</span></td>
    <td class="px-4 py-3 flex gap-2 flex-wrap">
        <a href="{{ route('admin.users.show', $user) }}" class="text-[#F57C00] text-xs">View</a>
        <a href="{{ route('admin.users.edit', $user) }}" class="text-xs">Edit</a>
        @unless($user->isAdmin())<form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="inline">@csrf<button class="text-xs text-blue-600">Impersonate</button></form>
        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-xs text-red-600">Delete</button></form>@endunless
    </td>
</tr>@endforeach</tbody></table></div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
