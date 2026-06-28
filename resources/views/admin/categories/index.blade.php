@extends('layouts.admin')
@section('title','Categories') @section('page-title','Category Management')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Add Category</h2>
        <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3">@csrf
            <input name="name" placeholder="Name" class="input-field" required>
            <select name="parent_id" class="input-field"><option value="">Root</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@foreach($c->children as $ch)<option value="{{ $ch->id }}">— {{ $ch->name }}</option>@endforeach@endforeach</select>
            <input name="icon" placeholder="Icon class" class="input-field">
            <input type="file" name="image" accept="image/*" class="text-sm">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1"> Featured</label>
            <button class="btn-primary w-full">Add</button>
        </form>
    </div>
    <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-5">
        <h2 class="font-semibold mb-4">Category Tree <span class="text-xs text-gray-500">(drag to reorder)</span></h2>
        <form id="categoryReorderForm" action="{{ route('admin.categories.reorder') }}" method="POST">@csrf<input type="hidden" name="order" value=""></form>
        <ul id="categorySortable" class="space-y-2">
            @foreach($categories as $category)
                <li data-id="{{ $category->id }}" class="p-3 bg-gray-50 rounded-lg cursor-move flex justify-between items-center">
                    <span>{{ $category->name }} @if($category->is_featured)<span class="text-xs text-[#F57C00]">★</span>@endif</span>
                    <div class="flex gap-2 text-xs">
                        <form action="{{ route('admin.categories.featured', $category) }}" method="POST">@csrf @method('PATCH')<button>Toggle Featured</button></form>
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
                    </div>
                </li>
                @foreach($category->children as $child)
                    <li data-id="{{ $child->id }}" class="p-3 bg-gray-50 rounded-lg cursor-move ml-6 flex justify-between items-center">
                        <span>↳ {{ $child->name }}</span>
                        <form action="{{ route('admin.categories.destroy', $child) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form>
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>
</div>
@endsection
