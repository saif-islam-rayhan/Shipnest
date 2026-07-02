@extends('layouts.merchant')

@section('title', $product->exists ? 'Edit Product' : 'Add Product')
@section('page-title', $product->exists ? 'Edit Product' : 'Add Product')

@section('content')
@php
    $variants = old('variants', $product->exists ? $product->variants->map(fn($v) => [
        'id' => $v->id, 'name' => $v->name, 'sku' => $v->sku, 'price' => $v->price,
        'compare_price' => $v->compare_price, 'stock' => $v->stock, 'weight' => $v->weight,
    ])->toArray() : [['name' => 'Default', 'sku' => '', 'price' => '', 'compare_price' => '', 'stock' => 0, 'weight' => '']]);
    $attributes = old('attributes', $product->exists ? $product->attributes->map(fn($a) => ['name' => $a->attribute_name, 'value' => $a->attribute_value])->toArray() : []);
    $existingImages = $product->exists ? $product->images : collect();
@endphp

<form action="{{ $product->exists ? route('merchant.products.update', $product) : route('merchant.products.store') }}"
      method="POST" enctype="multipart/form-data"
      x-data="productWizard(@js(['variants' => $variants, 'attributes' => $attributes, 'existingImages' => $existingImages->map(fn($i) => ['id' => $i->id, 'url' => $i->url])->values()]))">
    @csrf
    @if($product->exists) @method('PUT') @endif

    {{-- Steps nav --}}
    <div class="flex gap-2 mb-6 overflow-x-auto">
        @foreach(['Basic Info', 'Images', 'Variants', 'Attributes', 'SEO'] as $i => $label)
            <button type="button" @click="step = {{ $i + 1 }}"
                    class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition"
                    :class="step === {{ $i + 1 }} ? 'bg-[#F57C00] text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200'">
                {{ $i + 1 }}. {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Step 1 --}}
    <div x-show="step === 1" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Product Name *</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" class="input-field" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Category *</label>
                <select name="category_id" class="input-field" required>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Brand</label>
                <select name="brand_id" class="input-field">
                    <option value="">None</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">SKU *</label>
                <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" class="input-field" required>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Short Description</label>
                <textarea name="short_description" rows="2" class="input-field">{{ old('short_description', $product->short_description) }}</textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Description</label>
                <div id="quill-editor" class="bg-white min-h-[200px]"></div>
                <input type="hidden" name="description" id="description-input" value="{{ old('description', $product->description) }}">
            </div>
        </div>
        <button type="button" @click="step = 2" class="btn-primary">Next: Images</button>
    </div>

    {{-- Step 2 --}}
    <div x-show="step === 2" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center"
             @dragover.prevent @drop.prevent="handleDrop($event)">
            <p class="text-gray-500 mb-2">Drag & drop images here or click to browse</p>
            <input type="file" name="images[]" multiple accept="image/*" class="mx-auto" @change="previewFiles($event)">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Or paste image URLs (one per line)</label>
            <textarea name="image_urls" rows="3" class="input-field text-sm"
                placeholder="https://images.unsplash.com/photo-...">{{ old('image_urls') }}</textarea>
        </div>
        <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
            <template x-for="(img, i) in previews" :key="i">
                <div class="relative aspect-square rounded-lg overflow-hidden ring-2" :class="mainImage === i ? 'ring-[#F57C00]' : 'ring-gray-200'">
                    <img :src="img.url" class="w-full h-full object-cover">
                    <button type="button" @click="setMain(i)" class="absolute bottom-1 left-1 text-xs bg-[#F57C00] text-white px-2 py-0.5 rounded">Main</button>
                </div>
            </template>
            <template x-for="(img, i) in existingImages" :key="'ex-'+img.id">
                <div class="relative aspect-square rounded-lg overflow-hidden ring-2 ring-gray-200">
                    <img :src="img.url" class="w-full h-full object-cover">
                    <input type="hidden" name="image_order[]" :value="img.id">
                    <label class="absolute top-1 right-1 bg-red-500 text-white text-xs px-1 rounded cursor-pointer">
                        <input type="checkbox" name="remove_images[]" :value="img.id" class="sr-only"> ×
                    </label>
                </div>
            </template>
        </div>
        <div class="flex gap-2">
            <button type="button" @click="step = 1" class="btn-outline">Back</button>
            <button type="button" @click="step = 3" class="btn-primary">Next: Variants</button>
        </div>
    </div>

    {{-- Step 3 --}}
    <div x-show="step === 3" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <template x-for="(variant, i) in variants" :key="i">
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 p-3 bg-gray-50 rounded-lg">
                <input type="hidden" :name="'variants['+i+'][id]'" :value="variant.id || ''">
                <input type="text" :name="'variants['+i+'][name]'" x-model="variant.name" placeholder="Variant name" class="input-field">
                <input type="text" :name="'variants['+i+'][sku]'" x-model="variant.sku" placeholder="SKU" class="input-field">
                <input type="number" :name="'variants['+i+'][price]'" x-model="variant.price" placeholder="Price" class="input-field" step="0.01">
                <input type="number" :name="'variants['+i+'][compare_price]'" x-model="variant.compare_price" placeholder="Compare" class="input-field" step="0.01">
                <input type="number" :name="'variants['+i+'][stock]'" x-model="variant.stock" placeholder="Stock" class="input-field">
                <div class="flex gap-1">
                    <input type="number" :name="'variants['+i+'][weight]'" x-model="variant.weight" placeholder="Weight" class="input-field" step="0.01">
                    <button type="button" @click="variants.splice(i,1)" class="text-red-500 px-2" x-show="variants.length > 1">×</button>
                </div>
            </div>
        </template>
        <button type="button" @click="variants.push({name:'',sku:'',price:'',compare_price:'',stock:0,weight:''})" class="text-sm text-[#F57C00]">+ Add Variant Row</button>
        <div class="flex gap-2">
            <button type="button" @click="step = 2" class="btn-outline">Back</button>
            <button type="button" @click="step = 4" class="btn-primary">Next: Attributes</button>
        </div>
    </div>

    {{-- Step 4 --}}
    <div x-show="step === 4" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <template x-for="(attr, i) in attributes" :key="i">
            <div class="flex gap-3">
                <input type="text" :name="'attributes['+i+'][name]'" x-model="attr.name" placeholder="Attribute (e.g. Color)" class="input-field flex-1">
                <input type="text" :name="'attributes['+i+'][value]'" x-model="attr.value" placeholder="Value (e.g. Red)" class="input-field flex-1">
                <button type="button" @click="attributes.splice(i,1)" class="text-red-500 px-2">×</button>
            </div>
        </template>
        <button type="button" @click="attributes.push({name:'',value:''})" class="text-sm text-[#F57C00]">+ Add Attribute</button>
        <div class="flex gap-2">
            <button type="button" @click="step = 3" class="btn-outline">Back</button>
            <button type="button" @click="step = 5" class="btn-primary">Next: SEO</button>
        </div>
    </div>

    {{-- Step 5 --}}
    <div x-show="step === 5" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Meta Title</label>
            <input type="text" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" class="input-field">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Meta Description</label>
            <textarea name="meta_description" rows="3" class="input-field">{{ old('meta_description', $product->meta_description) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Tags (comma separated)</label>
            <input type="text" name="tags" value="{{ old('tags', is_array($product->tags) ? implode(', ', $product->tags) : '') }}" class="input-field">
        </div>
        <div class="flex gap-2 pt-4">
            <button type="button" @click="step = 4" class="btn-outline">Back</button>
            <button type="submit" name="action" value="draft" class="btn-outline" @click="syncQuill()">Save as Draft</button>
            <button type="submit" name="action" value="publish" class="btn-primary" @click="syncQuill()">Publish</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>window.initProductQuill && window.initProductQuill();</script>
@endpush
