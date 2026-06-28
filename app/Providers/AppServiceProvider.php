<?php

namespace App\Providers;

use App\Contracts\SmsServiceInterface;
use App\Models\Category;
use App\Services\CartService;
use App\Services\DynamicConfigService;
use App\Services\Sms\MockSmsService;
use App\Services\Sms\TwilioSmsService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsServiceInterface::class, function () {
            return match (config('sms.driver')) {
                'twilio' => new TwilioSmsService,
                default => new MockSmsService,
            };
        });
    }

    public function boot(): void
    {
        try {
            app(DynamicConfigService::class)->apply();
        } catch (\Throwable) {
            // Settings table may not exist during install
        }

        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        View::composer('layouts.frontend', function ($view) {
            $view->with('navCategories', Category::query()
                ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
                ->active()
                ->roots()
                ->orderBy('sort_order')
                ->get());
        });

        View::composer(['layouts.frontend', 'layouts.partials.header', 'components.layout.header'], function ($view) {
            $cartService = app(CartService::class);
            $user = auth()->user();

            $view->with([
                'cartItemCount' => $cartService->getItemCount($user),
                'wishlistCount' => $user ? $user->wishlists()->count() : 0,
            ]);
        });
    }
}
