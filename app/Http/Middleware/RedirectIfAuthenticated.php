<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect($this->redirectPath(Auth::guard($guard)->user()));
            }
        }

        return $next($request);
    }

    protected function redirectPath(User $user): string
    {
        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->isMerchant()) {
            $merchant = $user->merchant;

            if (! $merchant || $merchant->status !== 'active') {
                return route('merchant.pending');
            }

            return route('merchant.dashboard');
        }

        return route('home');
    }
}
