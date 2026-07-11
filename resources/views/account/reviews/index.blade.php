<x-layouts.account>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">My Reviews</h1>

    @if($pendingItems->isNotEmpty())
        <div class="card p-6 mb-8">
            <h2 class="font-semibold text-gray-900 mb-4">Pending Reviews</h2>
            <div class="space-y-6">
                @foreach($pendingItems as $item)
                    <div class="flex gap-4 pb-6 border-b last:border-0 last:pb-0">
                        <div class="w-16 h-16 rounded bg-gray-100 overflow-hidden flex-shrink-0">
                            @if($item->product?->primary_image_url)
                                <img src="{{ $item->product->primary_image_url }}" alt="" class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-900">{{ $item->product_name }}</p>
                            <p class="text-xs text-gray-500">Order #{{ $item->order->order_number }}</p>
                            <form action="{{ route('account.reviews.store') }}" method="POST" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="order_item_id" value="{{ $item->id }}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label class="block text-sm font-medium text-gray-700">Rating</label>
                                    @if($item->product)
                                    <div data-review-ai data-generate-url="{{ route('products.reviews.generate', $item->product) }}" class="flex items-center gap-2">
                                        <span data-ai-status class="text-xs text-gray-400"></span>
                                        <button type="button" data-ai-generate class="text-xs font-medium text-primary hover:underline">
                                            Generate with AI
                                        </button>
                                    </div>
                                    @endif
                                </div>
                                <select name="rating" class="input-field w-32" required>
                                    @for($i = 5; $i >= 1; $i--)
                                        <option value="{{ $i }}">{{ $i }} Star{{ $i > 1 ? 's' : '' }}</option>
                                    @endfor
                                </select>
                                <div>
                                    <input type="text" name="title" placeholder="Review title" class="input-field" required>
                                </div>
                                <div>
                                    <textarea name="body" rows="2" placeholder="Write your review..." class="input-field" required></textarea>
                                </div>
                                <button type="submit" class="btn-primary text-sm">Submit Review</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card">
        <div class="px-6 py-4 border-b"><h2 class="font-semibold text-gray-900">Reviews Written</h2></div>
        @if($reviews->isEmpty())
            <p class="p-6 text-sm text-gray-500">You haven't written any reviews yet.</p>
        @else
            <div class="divide-y">
                @foreach($reviews as $review)
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-gray-900">{{ $review->title }}</p>
                                <p class="text-sm text-gray-500">{{ $review->product->name ?? 'Product' }} · {{ $review->created_at->format('M d, Y') }}</p>
                            </div>
                            <x-rating-stars :rating="$review->rating" size="sm" />
                        </div>
                        <p class="text-sm text-gray-600 mt-2">{{ $review->body }}</p>
                    </div>
                @endforeach
            </div>
            <div class="p-4">{{ $reviews->links() }}</div>
        @endif
    </div>
</x-layouts.account>
