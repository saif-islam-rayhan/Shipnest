<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->with('children')->roots()->orderBy('sort_order')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        $data['sort_order'] = Category::query()->max('sort_order') + 1;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        Category::query()->create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->with('error', 'Remove child categories first.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $order = $request->input('order', []);
        if (is_string($order)) {
            $order = json_decode($order, true) ?? [];
        }

        foreach ($order as $index => $id) {
            Category::query()->where('id', $id)->update(['sort_order' => $index]);
        }

        return back()->with('success', 'Order updated.');
    }

    public function toggleFeatured(Category $category): RedirectResponse
    {
        $category->update(['is_featured' => ! $category->is_featured]);

        return back()->with('success', 'Featured toggled.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);
    }
}
