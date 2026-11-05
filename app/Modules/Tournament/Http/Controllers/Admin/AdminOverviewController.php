<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tournament\Services\NoCurrentTournament;
use App\Modules\Tournament\Support\AdminDashboard;
use App\Modules\Tournament\Support\TournamentAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The championship admin page "Turnierleitung" (old AdminController::indexAction). Access: route middleware
 * (module login, then can:admin.tournament). Without any championship the empty notice answers.
 */
final class AdminOverviewController extends Controller
{
    public function index(Request $request, TournamentAccess $access, AdminDashboard $dashboard): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $tournament = $access->resolveTournament($user);
        } catch (NoCurrentTournament) {
            return view('tournament::admin.empty');
        }

        $data = $dashboard->build($tournament);

        // Default pick deadline: tomorrow 20:00, in a test tournament half an hour from now (ZF2).
        $deadline = $data['isTest'] ? now()->addMinutes(30) : now()->addDay()->setTime(20, 0);

        return view('tournament::admin.index', $data + [
            'defaultDeadline' => $deadline->format('Y-m-d\TH:i'),
            'defaultSeason' => (int) now()->format('Y') + 1,
        ]);
    }
}
