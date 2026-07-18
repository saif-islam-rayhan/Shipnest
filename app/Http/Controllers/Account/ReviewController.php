<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreReviewRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $reviews = $user->reviews()
            ->with('product.images')
            ->latest()
            ->paginate(10);

        $pendingItems = OrderItem::query()
            ->with(['product.images', 'order'])
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', OrderStatus::Delivered->value))
            ->whereDoesntHave('review')
            ->latest()
            ->get();

        return view('account.reviews.index', compact('reviews', 'pendingItems'));
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $item = OrderItem::query()
            ->with('order')
            ->findOrFail($request->validated('order_item_id'));

        if ($item->order->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($item->order->status !== OrderStatus::Delivered) {
            return back()->with('error', 'You can only review after the product is delivered.');
        }

        if ($item->review) {
            return back()->with('error', 'You have already reviewed this item.');
        }

        ProductReview::query()->create([
            'product_id' => $item->product_id,
            'user_id' => $request->user()->id,
            'order_item_id' => $item->id,
            'rating' => $request->validated('rating'),
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'images' => ProductReview::storeUploadedImages($request->file('images')),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('account.reviews.index')
            ->with('success', 'Thank you! Your review was submitted and is awaiting approval.');
    }
}
