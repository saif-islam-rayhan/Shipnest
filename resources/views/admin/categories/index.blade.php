@extends('layouts.admin')
@section('title', 'Categories')
@section('page-title', 'Category Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="form-card">
        <h2 class="form-section-title mb-5">Add Category</h2>
        <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <x-admin.form-field label="Category name" name="name" required hint="Display name shown on storefront and filters.">
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="input-field" placeholder="e.g. Electronics" required>
            </x-admin.form-field>

            <x-admin.form-field label="Parent category" name="parent_id" hint="Leave as root for a top-level category.">
                <select id="parent_id" name="parent_id" class="input-field">
                    <option value="">Root category</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('parent_id') == $c->id)>{{ $c->name }}</option>
                        @foreach($c->children as $ch)
                            <option value="{{ $ch->id }}" @selected(old('parent_id') == $ch->id)>— {{ $ch->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </x-admin.form-field>

            <x-admin.form-field label="Icon class" name="icon" hint="Optional CSS icon class for navigation menus.">
                <input type="text" id="icon" name="icon" value="{{ old('icon') }}" class="input-field" placeholder="e.g. heroicon-tag">
            </x-admin.form-field>

            <x-admin.form-field label="Category image" name="image" hint="Recommended: square image, PNG or JPG up to 2 MB.">
                <input type="file" id="image" name="image" accept="image/*" class="form-file">
            </x-admin.form-field>

            <div class="form-check">
                <input type="checkbox" id="is_featured" name="is_featured" value="1" class="form-check-input" @checked(old('is_featured'))>
                <label for="is_featured" class="form-check-label">
                    Featured category
                    <span class="form-check-hint">Show this category on the homepage highlights.</span>
                </label>
            </div>

            <button type="submit" class="btn-primary w-full !py-2.5">Add category</button>
        </form>
    </div>

    <div class="lg:col-span-2 form-card">
        <h2 class="form-section-title mb-1">Category tree</h2>
        <p class="form-hint mb-5">Drag items to reorder how categories appear on the storefront.</p>

        <form id="categoryReorderForm" action="{{ route('admin.categories.reorder') }}" method="POST">
            @csrf
            <input type="hidden" name="order" value="">
        </form>

        <ul id="categorySortable" class="space-y-2">
            @foreach($categories as $category)
                <li data-id="{{ $category->id }}" class="flex items-center justify-between rounded-lg bg-gray-50 p-3.5 cursor-move border border-gray-100">
                    <span class="text-sm font-medium text-gray-800">
                        {{ $category->name }}
                        @if($category->is_featured)
                            <span class="ml-1 text-xs text-primary">★ Featured</span>
                        @endif
                    </span>
                    <div class="flex gap-2 text-xs">
                        <form action="{{ route('admin.categories.featured', $category) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="rounded-md px-2.5 py-1.5 font-medium text-gray-600 ring-1 ring-gray-200 hover:bg-white">Toggle featured</button>
                        </form>
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete this category?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-md px-2.5 py-1.5 font-medium text-red-600 ring-1 ring-red-100 hover:bg-red-50">Delete</button>
                        </form>
                    </div>
                </li>
                @foreach($category->children as $child)
                    <li data-id="{{ $child->id }}" class="ml-6 flex items-center justify-between rounded-lg bg-gray-50 p-3.5 cursor-move border border-gray-100">
                        <span class="text-sm text-gray-700">↳ {{ $child->name }}</span>
                        <form action="{{ route('admin.categories.destroy', $child) }}" method="POST" onsubmit="return confirm('Delete this category?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-md px-2.5 py-1.5 text-xs font-medium text-red-600 ring-1 ring-red-100 hover:bg-red-50">Delete</button>
                        </form>
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>
</div>
@endsection
