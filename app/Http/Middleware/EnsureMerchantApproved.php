<?php

namespace App\Http\Middleware;

use App\Enums\ShopStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchant = $request->user()?->merchant;

        if (! $merchant || $merchant->status !== ShopStatus::Active->value) {
            return redirect()->route('merchant.pending');
        }

        return $next($request);
    }
}
