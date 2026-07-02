@extends('layouts.admin')
@section('title', 'Brands')
@section('page-title', 'Brand Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="form-card">
        <h2 class="form-section-title mb-5">Add brand</h2>
        <form action="{{ route('admin.brands.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <x-admin.form-field label="Brand name" name="name" required>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="input-field" placeholder="e.g. Samsung" required>
            </x-admin.form-field>

            <x-admin.form-field label="Description" name="description" hint="Short summary shown on brand listing pages.">
                <textarea id="description" name="description" rows="3" class="input-field" placeholder="Brief brand description">{{ old('description') }}</textarea>
            </x-admin.form-field>

            <x-admin.form-field label="Brand logo" name="logo" hint="Square logo works best. PNG or JPG, max 2 MB.">
                <input type="file" id="logo" name="logo" accept="image/*" class="form-file">
            </x-admin.form-field>

            <div class="form-check">
                <input type="checkbox" id="is_featured" name="is_featured" value="1" class="form-check-input" @checked(old('is_featured'))>
                <label for="is_featured" class="form-check-label">
                    Featured brand
                    <span class="form-check-hint">Highlight on homepage and promotional sections.</span>
                </label>
            </div>

            <button type="submit" class="btn-primary w-full !py-2.5">Add brand</button>
        </form>
    </div>

    <div class="lg:col-span-2 form-card">
        <h2 class="form-section-title mb-5">All brands</h2>
        <table class="admin-datatable w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Brand</th>
                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Featured</th>
                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($brands as $brand)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $brand->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $brand->is_featured ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2 text-xs">
                                <form action="{{ route('admin.brands.featured', $brand) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="rounded-md px-2.5 py-1.5 font-medium text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50">Toggle</button>
                                </form>
                                <form action="{{ route('admin.brands.destroy', $brand) }}" method="POST" onsubmit="return confirm('Delete this brand?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-md px-2.5 py-1.5 font-medium text-red-600 ring-1 ring-red-100 hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $brands->links() }}</div>
    </div>
</div>
@endsection
