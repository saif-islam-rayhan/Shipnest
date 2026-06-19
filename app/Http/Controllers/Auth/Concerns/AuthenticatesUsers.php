<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait AuthenticatesUsers
{
    protected function loginUser(User $user, Request $request, bool $remember = false): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        app(CartService::class)->mergeGuestCart($user);

        return redirect()->intended($this->redirectPath($user))
            ->with('success', 'Welcome back, '.$user->name.'!');
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

    protected function ensureUserCanLogin(?User $user): ?RedirectResponse
    {
        if (! $user) {
            return null;
        }

        if (! $user->isActive()) {
            return back()->withErrors([
                'email' => 'Your account has been deactivated. Please contact support.',
            ]);
        }

        return null;
    }
}
