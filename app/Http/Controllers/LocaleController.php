<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $available = config('shipnest.localization.available_locales', ['en']);

        if (! in_array($locale, $available, true)) {
            abort(404);
        }

        session(['locale' => $locale]);

        return back();
    }
}
