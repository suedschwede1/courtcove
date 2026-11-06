<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Support\TournamentPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Base of the championship read pages (old IndexController): every page gets the shared context, and
 * without any championship the empty page answers instead of ZF2's error page.
 */
abstract class TournamentPageController extends Controller
{
    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $data  page data from the shared context
     */
    protected function page(Request $request, string $active, string $view, callable $data): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $page = app(TournamentPage::class)->build($user, $active);

        if ($page === null) {
            return view('tournament::empty');
        }

        return view($view, $data($page) + $page + ['active' => $active]);
    }
}
