@extends('layouts.admin')
@section('title','CMS Pages') @section('page-title','CMS Pages')
@section('content')
<div class="flex justify-between items-center mb-4">
    <p class="text-sm text-gray-600">Manage static pages (About, Terms, Privacy, etc.)</p>
    <a href="{{ route('admin.pages.create') }}" class="btn-primary text-sm">New Page</a>
</div>
<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
<table class="admin-datatable w-full text-sm"><thead class="bg-gray-50"><tr>
    <th class="px-4 py-3 text-left">Title</th><th class="px-4 py-3">Slug</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Updated</th><th class="px-4 py-3">Actions</th>
</tr></thead><tbody>@foreach($pages as $page)<tr>
    <td class="px-4 py-3 font-medium">{{ $page->title }}</td>
    <td class="px-4 py-3 text-gray-500">{{ $page->slug }}</td>
    <td class="px-4 py-3"><span class="badge {{ $page->status==='published' ? 'bg-green-100 text-green-800' : 'bg-gray-100' }}">{{ ucfirst($page->status) }}</span></td>
    <td class="px-4 py-3 text-gray-500">{{ $page->updated_at->diffForHumans() }}</td>
    <td class="px-4 py-3 flex gap-2 text-xs">
        <a href="{{ route('admin.pages.edit', $page) }}" class="text-[#F57C00]">Edit</a>
        <form action="{{ route('admin.pages.destroy', $page) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
    </td>
</tr>@endforeach</tbody></table></div>
<div class="mt-4">{{ $pages->links() }}</div>
@endsection
