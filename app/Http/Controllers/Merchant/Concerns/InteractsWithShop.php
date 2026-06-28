<?php

namespace App\Http\Controllers\Merchant\Concerns;

use App\Models\Merchant;
use Illuminate\Http\Request;

trait InteractsWithShop
{
    protected function shop(Request $request): Merchant
    {
        return $request->user()->merchant;
    }

    protected function authorizeProduct(Merchant $shop, $product): void
    {
        if ($product->merchant_id !== $shop->id) {
            abort(403);
        }
    }

    protected function authorizeOrder(Merchant $shop, $order): void
    {
        if (! $order->items()->where('merchant_id', $shop->id)->exists()) {
            abort(403);
        }
    }
}
