@extends('layouts.admin')
@section('title','Edit User') @section('page-title','Edit User')
@section('content')
<form action="{{ route('admin.users.update', $user) }}" method="POST" class="max-w-xl bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">@csrf @method('PUT')
    <div><label class="text-sm font-medium">Name</label><input name="name" value="{{ old('name',$user->name) }}" class="input-field" required></div>
    <div><label class="text-sm font-medium">Email</label><input name="email" type="email" value="{{ old('email',$user->email) }}" class="input-field" required></div>
    <div><label class="text-sm font-medium">Phone</label><input name="phone" value="{{ old('phone',$user->phone) }}" class="input-field"></div>
    <div><label class="text-sm font-medium">Status</label><select name="status" class="input-field">@foreach(['active','inactive','suspended'] as $s)<option value="{{ $s }}" @selected(old('status',$user->status)===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div><label class="text-sm font-medium">Role</label><select name="role" class="input-field">@foreach($roles as $r)<option value="{{ $r }}" @selected($user->hasRole($r))>{{ ucfirst($r) }}</option>@endforeach</select></div>
    <button class="btn-primary">Save</button>
</form>
@endsection
