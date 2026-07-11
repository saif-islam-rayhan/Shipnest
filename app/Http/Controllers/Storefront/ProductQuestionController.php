<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductQuestionController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        ProductQuestion::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'question' => trim($validated['question']),
            'status' => 'pending',
        ]);

        return redirect()
            ->to(route('products.show', $product->slug).'#product-qa')
            ->with('success', 'Your question has been submitted. The seller will answer soon.');
    }

    public function answer(Request $request, Product $product, ProductQuestion $question): RedirectResponse
    {
        if ($question->product_id !== $product->id) {
            abort(404);
        }

        $user = $request->user();
        $isMerchantOwner = $product->merchant
            && (int) $product->merchant->user_id === (int) $user->id;

        if (! $user->isAdmin() && ! $isMerchantOwner) {
            abort(403);
        }

        $validated = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $question->update([
            'answer' => trim($validated['answer']),
            'answered_by' => $user->id,
            'answered_at' => now(),
            'status' => 'answered',
        ]);

        return redirect()
            ->to(route('products.show', $product->slug).'#product-qa')
            ->with('success', 'Answer posted.');
    }
}
