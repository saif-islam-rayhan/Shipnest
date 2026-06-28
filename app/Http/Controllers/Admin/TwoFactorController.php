<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $secret = $user->google2fa_secret ?: $google2fa->generateSecretKey();

        if (! $user->google2fa_secret) {
            $user->update(['google2fa_secret' => encrypt($secret)]);
        } else {
            $secret = decrypt($user->google2fa_secret);
        }

        $qrUrl = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        return view('admin.2fa.setup', compact('secret', 'qrUrl'));
    }

    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = auth()->user();
        $secret = decrypt($user->google2fa_secret);
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
        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey(decrypt($user->google2fa_secret), $request->input('code'))) {
            return back()->with('error', 'Invalid code.');
        }

        $request->session()->put('2fa_verified', true);

        return redirect()->intended(route('admin.dashboard'));
    }
}
