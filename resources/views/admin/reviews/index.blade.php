@extends('layouts.admin')
@section('title', 'Reviews')
@section('page-title', 'Product Reviews')
@section('content')
<div class="flex flex-wrap gap-2 mb-4">
    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
        <a href="{{ route('admin.reviews.index', ['status' => $key]) }}"
           class="px-3 py-1 rounded-full text-sm {{ $status === $key ? 'bg-[#F57C00] text-white' : 'bg-white ring-1 ring-gray-200' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
    <table class="admin-datatable w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">Product</th>
                <th class="px-4 py-3 text-left">Customer</th>
                <th class="px-4 py-3 text-left">Rating</th>
                <th class="px-4 py-3 text-left">Review</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reviews as $review)
                <tr>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded bg-gray-100 overflow-hidden flex-shrink-0">
                                @if($review->product?->primary_image_url)
                                    <img src="{{ $review->product->primary_image_url }}" alt="" class="w-full h-full object-cover">
                                @endif
                            </div>
                            <span class="font-medium text-gray-900 line-clamp-2">{{ $review->product->name ?? 'Product' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">{{ $review->user->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-rating-stars :rating="$review->rating" size="sm" />
                    </td>
                    <td class="px-4 py-3 max-w-xs">
                        <p class="font-medium text-gray-900">{{ $review->title }}</p>
                        <p class="text-xs text-gray-500 line-clamp-2 mt-0.5">{{ $review->body }}</p>
                        @if($review->image_urls)
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach($review->image_urls as $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="block w-10 h-10 rounded overflow-hidden bg-gray-100 ring-1 ring-gray-200">
                                        <img src="{{ $url }}" alt="Review photo" class="w-full h-full object-cover"
                                             onerror="this.closest('a')?.remove()">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php
                            $badge = match ($review->status) {
                                'approved' => 'bg-green-100 text-green-700',
                                'rejected' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <div class="inline-flex flex-col items-center gap-1">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ ucfirst($review->status) }}
                            </span>
                            @if($review->sentiment)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium {{ $review->sentiment === 'positive' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                    {{ ucfirst($review->sentiment) }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500 whitespace-nowrap">
                        {{ $review->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-2 text-xs">
                            @if($review->status !== 'approved')
                                <form action="{{ route('admin.reviews.approve', $review) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-green-600 hover:underline">Approve</button>
                                </form>
                            @endif
                            @if($review->status !== 'rejected')
                                <form action="{{ route('admin.reviews.reject', $review) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-red-600 hover:underline">Reject</button>
                                </form>
                            @endif
                            @if($review->status === 'approved')
                                <span class="text-gray-400">Live</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No reviews found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $reviews->links() }}</div>
@endsection
