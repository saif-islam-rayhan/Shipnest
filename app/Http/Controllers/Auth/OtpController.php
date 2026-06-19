<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\AuthenticatesUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OtpController extends Controller
{
    use AuthenticatesUsers;

    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function showVerifyForm(string $type): View|RedirectResponse
    {
        if (! in_array($type, ['registration', 'login'], true)) {
            abort(404);
        }

        $phone = match ($type) {
            'registration' => session('pending_verification_phone'),
            'login' => session('otp_login_phone'),
        };

        if (! $phone) {
            return redirect()->route($type === 'login' ? 'login.otp' : 'register')
                ->with('error', 'Session expired. Please try again.');
        }

        return view('auth.verify-phone', compact('type', 'phone'));
    }

    public function send(SendOtpRequest $request): RedirectResponse
    {
        $phone = $request->validated('phone');
        $type = $request->validated('type');

        if ($type === 'login') {
            $user = User::query()->where('phone', $phone)->first();

            if (! $user) {
                return back()->withErrors(['phone' => 'No account found with this phone number.']);
            }

            $blocked = $this->ensureUserCanLogin($user);

            if ($blocked) {
                return $blocked;
            }

            session(['otp_login_phone' => $phone, 'otp_login_user_id' => $user->id]);
        }

        if ($type === 'registration') {
            $sessionPhone = session('pending_verification_phone');

            if ($sessionPhone && $sessionPhone !== $phone) {
                return back()->withErrors(['phone' => 'Phone number does not match your registration.']);
            }
        }

        $this->otpService->send($phone, $type);

        return back()->with('success', 'OTP sent successfully.');
    }

    public function verify(VerifyOtpRequest $request): RedirectResponse
    {
        $phone = $request->validated('phone');
        $otp = $request->validated('otp');
        $type = $request->validated('type');

        if (! $this->otpService->verify($phone, $otp, $type)) {
            return back()
                ->withErrors(['otp' => 'Invalid or expired OTP. Please try again.'])
                ->withInput(['phone' => $phone]);
        }

        if ($type === 'registration') {
            return $this->handleRegistrationVerification($phone);
        }

        return $this->handleLoginVerification($phone);
    }

    protected function handleRegistrationVerification(string $phone): RedirectResponse
    {
        $userId = session('pending_verification_user_id');

        if (! $userId) {
            return redirect()->route('register')->with('error', 'Session expired. Please register again.');
        }

        $user = User::query()->findOrFail($userId);

        if ($user->phone !== $phone) {
            return back()->withErrors(['otp' => 'Phone number mismatch.']);
        }

        $user->update(['phone_verified_at' => now()]);

        session()->forget([
            'pending_verification_user_id',
            'pending_verification_phone',
            'pending_verification_type',
        ]);

        Auth::login($user);
        request()->session()->regenerate();

        if (session()->pull('pending_merchant_registration')) {
            session()->forget('pending_merchant_registration');

            return redirect()
                ->route('merchant.pending')
                ->with('success', 'Phone verified! Your merchant application is pending admin approval.');
        }

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Phone verified! Please verify your email address.');
    }

    protected function handleLoginVerification(string $phone): RedirectResponse
    {
        $userId = session('otp_login_user_id');

        if (! $userId) {
            return redirect()->route('login.otp')->with('error', 'Session expired. Please try again.');
        }

        $user = User::query()->findOrFail($userId);

        if ($user->phone !== $phone) {
            return back()->withErrors(['otp' => 'Phone number mismatch.']);
        }

        session()->forget(['otp_login_phone', 'otp_login_user_id']);

        return $this->loginUser($user, request());
    }
}
