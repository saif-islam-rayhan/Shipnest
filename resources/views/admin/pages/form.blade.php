@extends('layouts.admin')
@section('title', $page->exists ? 'Edit Page' : 'New Page')
@section('page-title', $page->exists ? 'Edit Page' : 'Create Page')
@section('content')
<form action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" method="POST" class="max-w-4xl space-y-4">
    @csrf
    @if($page->exists) @method('PUT') @endif
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
        <input name="title" value="{{ old('title', $page->title) }}" placeholder="Page title" class="input-field" required>
        <select name="status" class="input-field w-40">
            <option value="draft" @selected(old('status', $page->status)==='draft')>Draft</option>
            <option value="published" @selected(old('status', $page->status)==='published')>Published</option>
        </select>
        <div>
            <label class="text-sm text-gray-600 mb-2 block">Content</label>
            <div id="quill-editor" class="min-h-[300px] bg-white">{!! old('content', $page->content) !!}</div>
            <input type="hidden" name="content" id="content-input" value="{{ old('content', $page->content) }}">
        </div>
    </div>
    <div class="flex gap-3">
        <button class="btn-primary">Save Page</button>
        <a href="{{ route('admin.pages.index') }}" class="btn-outline">Cancel</a>
    </div>
</form>
@endsection
@push('scripts')<script>document.addEventListener('DOMContentLoaded', () => window.initAdminQuill?.());</script>@endpush
