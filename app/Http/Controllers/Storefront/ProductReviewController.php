<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $orderItem = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereDoesntHave('review')
            ->first();

        if (! $orderItem) {
            return back()->with('error', 'You can only review products you have purchased.');
        }

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_item_id' => $orderItem->id,
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => 'approved',
        ]);

        return back()->with('success', 'Thank you for your review!');
    }
}
