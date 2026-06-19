<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MerchantRegisterRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Notifications\NewMerchantApplicationNotification;
use App\Services\OtpService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
            'status' => 'active',
        ]);

        $user->assignRole('customer');

        event(new Registered($user));

        $this->otpService->send($user->phone, 'registration', $user->email);

        session([
            'pending_verification_user_id' => $user->id,
            'pending_verification_phone' => $user->phone,
            'pending_verification_type' => 'registration',
        ]);

        return redirect()
            ->route('otp.verify-form', ['type' => 'registration'])
            ->with('success', 'Account created! Please verify your phone number.');
    }

    public function showMerchantRegister(): View
    {
        return view('auth.merchant-register');
    }

    public function registerMerchant(MerchantRegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
            'status' => 'active',
        ]);

        $user->assignRole('merchant');

        $merchant = Merchant::query()->create([
            'user_id' => $user->id,
            'shop_name' => $request->validated('shop_name'),
            'shop_slug' => $request->validated('shop_slug'),
            'phone' => $request->validated('phone'),
            'district' => $request->validated('district'),
            'address' => $request->validated('address'),
            'status' => ShopStatus::Pending->value,
        ]);

        event(new Registered($user));

        $this->notifyAdmins($merchant);
        $this->otpService->send($user->phone, 'registration', $user->email);

        session([
            'pending_verification_user_id' => $user->id,
            'pending_verification_phone' => $user->phone,
            'pending_verification_type' => 'registration',
            'pending_merchant_registration' => true,
        ]);

        return redirect()
            ->route('otp.verify-form', ['type' => 'registration'])
            ->with('success', 'Application submitted! Please verify your phone number.');
    }

    public function showVerifyEmail(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        return view('auth.verify-email');
    }

    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()
            ->intended(route('home'))
            ->with('success', 'Email verified successfully!');
    }

    public function resendVerificationEmail(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Verification link sent!');
    }

    public function showMerchantPending(Request $request): View
    {
        return view('auth.merchant-pending', [
            'merchant' => $request->user()?->merchant,
        ]);
    }

    protected function notifyAdmins(Merchant $merchant): void
    {
        $adminEmail = config('shipnest.admin_notification_email');

        if ($adminEmail) {
            Notification::route('mail', $adminEmail)
                ->notify(new NewMerchantApplicationNotification($merchant));
        }

        $admins = User::role(['super_admin', 'admin'])->get();

        Notification::send($admins, new NewMerchantApplicationNotification($merchant));
    }
}
