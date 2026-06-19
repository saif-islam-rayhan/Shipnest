<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\AuthenticatesUsers;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialController extends Controller
{
    use AuthenticatesUsers;

    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', 'Unable to authenticate with '.ucfirst($provider).'. Please try again.');
        }

        $providerIdColumn = $provider.'_id';

        $user = User::query()->where($providerIdColumn, $socialUser->getId())->first();

        if (! $user && $socialUser->getEmail()) {
            $user = User::query()->where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->update([$providerIdColumn => $socialUser->getId()]);
            }
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email' => $socialUser->getEmail(),
                'password' => bcrypt(Str::random(32)),
                $providerIdColumn => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $user->assignRole('customer');
        }

        $blocked = $this->ensureUserCanLogin($user);

        if ($blocked) {
            return redirect()->route('login')->with('error', 'Your account has been deactivated.');
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        app(CartService::class)->mergeGuestCart($user);

        return redirect()
            ->intended($this->redirectPath($user))
            ->with('success', 'Welcome, '.$user->name.'!');
    }

    protected function validateProvider(string $provider): void
    {
        if (! in_array($provider, ['google', 'facebook'], true)) {
            abort(404);
        }
    }
}
