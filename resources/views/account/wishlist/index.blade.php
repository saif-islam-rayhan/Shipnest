<x-layouts.account>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">My Wishlist</h1>

    @if($wishlists->isEmpty())
        <div class="card p-12 text-center">
            <p class="text-gray-500">Your wishlist is empty.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4 inline-block">Browse Products</a>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($wishlists as $wishlist)
                @php $product = $wishlist->product; @endphp
                @if($product)
                    <div class="card overflow-hidden">
                        <a href="{{ route('products.show', $product->slug) }}" class="block">
                            <div class="aspect-square bg-gray-100 overflow-hidden">
                                @if($product->primary_image_url)
                                    <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                @endif
                            </div>
                            <div class="p-3">
                                <h3 class="text-sm font-medium text-gray-900 line-clamp-2">{{ $product->name }}</h3>
                                <p class="text-lg font-bold text-primary mt-1">{{ config('shipnest.currency_symbol') }}{{ number_format($product->price) }}</p>
                            </div>
                        </a>
                        <div class="px-3 pb-3 flex gap-2">
                            <form action="{{ route('account.wishlist.move-to-cart', $wishlist) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full btn-primary text-xs py-2">Move to Cart</button>
                            </form>
                            <form action="{{ route('account.wishlist.destroy', $wishlist) }}" method="POST">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-outline text-xs py-2 px-3 text-red-600 border-red-200 hover:bg-red-50">Remove</button>
                            </form>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="mt-6">{{ $wishlists->links() }}</div>
    @endif
</x-layouts.account>
