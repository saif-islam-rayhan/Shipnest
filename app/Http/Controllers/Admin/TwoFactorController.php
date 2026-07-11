<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function setup(): View
    {
        $user = auth()->user();
        $google2fa = new Google2FA;
        $secret = $this->resolveOrCreateSecret($user, $google2fa);
        $qrUrl = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        return view('admin.2fa.setup', compact('secret', 'qrUrl'));
    }

    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = auth()->user();
        $secret = $this->readSecret($user);

        if ($secret === null) {
            return redirect()->route('admin.2fa.setup')
                ->with('error', '2FA setup expired. Please scan the QR code again.');
        }

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($secret, $request->input('code'))) {
            return back()->with('error', 'Invalid verification code.');
        }

        $user->update(['google2fa_enabled' => true]);
        $request->session()->put('2fa_verified', true);

        return redirect()->route('admin.dashboard')->with('success', '2FA enabled.');
    }

    public function disable(Request $request): RedirectResponse
    {
        auth()->user()->update(['google2fa_enabled' => false, 'google2fa_secret' => null]);
        $request->session()->forget('2fa_verified');

        return back()->with('success', '2FA disabled.');
    }

    public function challenge(): View
    {
        return view('admin.2fa.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = auth()->user();
        $secret = $this->readSecret($user);

        if ($secret === null) {
            $user->update(['google2fa_enabled' => false, 'google2fa_secret' => null]);

            return redirect()->route('admin.dashboard')
                ->with('error', '2FA configuration was invalid and has been reset. Please set up 2FA again.');
        }

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($secret, $request->input('code'))) {
            return back()->with('error', 'Invalid code.');
        }

        $request->session()->put('2fa_verified', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    protected function resolveOrCreateSecret(User $user, Google2FA $google2fa): string
    {
        $existing = $this->readSecret($user);

        if ($existing !== null) {
            return $existing;
        }

        $secret = $google2fa->generateSecretKey();
        $user->update(['google2fa_secret' => encrypt($secret)]);

        return $secret;
    }

    protected function readSecret(User $user): ?string
    {
        if (! $user->google2fa_secret) {
            return null;
        }

        try {
            $secret = decrypt($user->google2fa_secret);

            return filled($secret) ? $secret : null;
        } catch (\Throwable) {
            $user->update(['google2fa_secret' => null, 'google2fa_enabled' => false]);

            return null;
        }
    }
}
