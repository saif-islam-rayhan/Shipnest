<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::query()
            ->with(['defaultVariant', 'merchant'])
            ->active()
            ->inStock()
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image' => $product->primary_image_url,
                'price' => $product->price,
                'formatted_price' => config('shipnest.currency_symbol').number_format($product->price),
                'merchant' => $product->merchant?->shop_name,
                'url' => route('products.show', $product->slug),
            ]);

        return response()->json($products);
    }
}
