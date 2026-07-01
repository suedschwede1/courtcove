<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Switches the active UI language. Persists the choice to bs_users_meta for
 * authenticated users and to a long-lived cookie for everyone (guests rely
 * on the cookie alone; it also seeds the choice before first login).
 */
final class LocaleController extends Controller
{
    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function switch(Request $request, string $locale): RedirectResponse
    {
        $available = config('app.available_locales', ['de']);
        if (! in_array($locale, $available, true)) {
            throw new NotFoundHttpException();
        }

        if ($request->user() !== null) {
            $request->user()->setMeta('locale', $locale);
        }

        return redirect()->back()->withCookie(
            cookie('locale', $locale, self::COOKIE_MINUTES)
        );
    }
}
