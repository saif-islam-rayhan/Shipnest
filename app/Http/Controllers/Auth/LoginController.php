<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\AuthenticatesUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpLoginRequest;
use App\Models\User;
use App\Services\CartService;
use App\Services\OtpService;
use App\Services\UserInterestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct(
        private readonly OtpService $otpService,
        private readonly CartService $cartService,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $blocked = $this->ensureUserCanLogin($user);

        if ($blocked) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $blocked;
        }

        $this->cartService->mergeGuestCart($user);
        app(UserInterestService::class)->mergeGuestSession($user, $request->session()->getId());

        return redirect()->intended($this->redirectPath($user))
            ->with('success', 'Welcome back, '.$user->name.'!');
    }

    public function showOtpLogin(): View
    {
        return view('auth.otp-login');
    }

    public function initiateOtpLogin(OtpLoginRequest $request): RedirectResponse
    {
        $user = User::query()->where('phone', $request->validated('phone'))->first();

        if (! $user) {
            return back()
                ->withErrors(['phone' => 'No account found with this phone number.'])
                ->onlyInput('phone');
        }

        $blocked = $this->ensureUserCanLogin($user);

        if ($blocked) {
            return $blocked->withInput(['phone' => $request->validated('phone')]);
        }

        $phone = $request->validated('phone');

        $this->otpService->send($phone, 'login');

        session([
            'otp_login_phone' => $phone,
            'otp_login_user_id' => $user->id,
        ]);

        return redirect()
            ->route('otp.verify-form', ['type' => 'login'])
            ->with('success', 'OTP sent to your phone number.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been logged out.');
    }
}
