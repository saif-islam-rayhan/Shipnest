<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('shipnest.localization.available_locales', ['en']);
        $default = config('shipnest.localization.default_locale', config('app.locale', 'en'));
        $locale = session('locale', $default);

        if (! in_array($locale, $available, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
