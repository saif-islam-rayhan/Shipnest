<?php

namespace App\Http\Middleware;

use App\Enums\ShopStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MerchantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchant = $request->user()?->merchant;

        if (! $merchant) {
            return redirect()->route('merchant.shop.create');
        }

        if ($merchant->status !== ShopStatus::Active->value || ! $merchant->is_verified) {
            return redirect()->route('merchant.pending')
                ->with('error', 'Your shop must be verified and active to access the seller panel.');
        }

        return $next($request);
    }
}
