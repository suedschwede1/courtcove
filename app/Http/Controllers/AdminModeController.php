<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AdminMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches admin mode on or off (header switch, "Switch on admin mode" gate page).
 *
 * Only accounts that hold a privilege as stored (raw, User::hasAnyPrivilege()) may switch: without
 * admin mode no privilege is in effect, so the effective check (can()) cannot be used here.
 * "on" sets the session cookie, "off" deletes it. The redirect goes to the posted same-host
 * relative path (AdminMode::safePath()), never to another host.
 */
final class AdminModeController extends Controller
{
    public function set(Request $request, string $state): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyPrivilege(), 403);

        $target = AdminMode::safePath($request->input('redirect'));
        $cookie = $state === 'on' ? AdminMode::makeCookie() : AdminMode::forgetCookie();

        return redirect()->to($target)->withCookie($cookie);
    }
}
