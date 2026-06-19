<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/login/otp', [LoginController::class, 'showOtpLogin'])->name('login.otp');
    Route::post('/login/otp', [LoginController::class, 'initiateOtpLogin'])->name('login.otp.send');

    Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    Route::get('/merchant/register', [RegisterController::class, 'showMerchantRegister'])->name('merchant.register');
    Route::post('/merchant/register', [RegisterController::class, 'registerMerchant']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

    Route::get('/auth/{provider}/redirect', [SocialController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialController::class, 'callback'])->name('social.callback');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::prefix('otp')->name('otp.')->group(function () {
    Route::get('/verify/{type}', [OtpController::class, 'showVerifyForm'])->name('verify-form');
    Route::post('/send', [OtpController::class, 'send'])->name('send')->middleware('throttle:5,1');
    Route::post('/verify', [OtpController::class, 'verify'])->name('verify')->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [RegisterController::class, 'showVerifyEmail'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [RegisterController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [RegisterController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/merchant/pending', [RegisterController::class, 'showMerchantPending'])->name('merchant.pending');
});
