@extends('layouts.admin')
@section('title','Brands') @section('page-title','Brand Management')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Add Brand</h2>
        <form action="{{ route('admin.brands.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3">@csrf
            <input name="name" placeholder="Brand name" class="input-field" required>
            <textarea name="description" placeholder="Description" class="input-field" rows="2"></textarea>
            <input type="file" name="logo" accept="image/*" class="text-sm">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1"> Featured</label>
            <button class="btn-primary w-full">Add Brand</button>
        </form>
    </div>
    <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <table class="admin-datatable w-full text-sm"><thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Brand</th><th class="px-4 py-2">Featured</th><th class="px-4 py-2">Actions</th></tr></thead>
        <tbody>@foreach($brands as $brand)<tr>
            <td class="px-4 py-2 font-medium">{{ $brand->name }}</td>
            <td class="px-4 py-2">{{ $brand->is_featured ? 'Yes' : 'No' }}</td>
            <td class="px-4 py-2 flex gap-2 text-xs">
                <form action="{{ route('admin.brands.featured', $brand) }}" method="POST">@csrf @method('PATCH')<button>Toggle</button></form>
                <form action="{{ route('admin.brands.destroy', $brand) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
            </td>
        </tr>@endforeach</tbody></table>
        <div class="mt-4">{{ $brands->links() }}</div>
    </div>
</div>
@endsection
