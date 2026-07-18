<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'pending');

        $reviews = ProductReview::query()
            ->with(['product.images', 'user', 'orderItem.order'])
            ->when(
                $status !== 'all',
                fn ($q) => $q->where('status', $status),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.reviews.index', compact('reviews', 'status'));
    }

    public function approve(ProductReview $review): RedirectResponse
    {
        if ($review->status === 'approved') {
            return back()->with('error', 'This review is already approved.');
        }

        $review->update(['status' => 'approved']);

        return back()->with('success', 'Review approved and is now visible on the product page.');
    }

    public function reject(ProductReview $review): RedirectResponse
    {
        if ($review->status === 'rejected') {
            return back()->with('error', 'This review is already rejected.');
        }

        $review->update(['status' => 'rejected']);

        return back()->with('success', 'Review rejected.');
    }
}
