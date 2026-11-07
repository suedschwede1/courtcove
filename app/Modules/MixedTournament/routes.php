<?php

declare(strict_types=1);

use App\Modules\MixedTournament\Http\Controllers\Admin\EventAdminController;
use App\Modules\MixedTournament\Http\Controllers\Admin\MemberImportController;
use App\Modules\MixedTournament\Http\Controllers\Admin\ParticipantAdminController;
use App\Modules\MixedTournament\Http\Controllers\Admin\RoundAdminController;
use App\Modules\MixedTournament\Http\Controllers\PlayerController;
use App\Modules\MixedTournament\Http\Controllers\ScorecardController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

// Routes of the mixed tournament module (URL prefix `mixed-tournament`, route names `mixed.*`).
// Every route needs a login; the event abilities (view/score/administer) are checked in the controllers,
// the global right (`admin.mixed`) here. Ids are numeric only; an unknown event/round/participant
// redirects to the module index with a flash.
$missingEvent = static fn () => redirect()->route('mixed.index')->with('error', __('mixed::messages.event_not_found'));
$missingParticipant = static fn () => redirect()->route('mixed.index')->with('error', __('mixed::messages.participant_not_found'));
$missingRound = static fn () => redirect()->route('mixed.index')->with('error', __('mixed::messages.round_not_found'));

Route::prefix('mixed-tournament')->name('mixed.')->middleware('tournament-base.login:mixed::messages.login_required')->group(function () use ($missingEvent, $missingRound, $missingParticipant): void {
    $event = static fn (RoutingRoute $r): RoutingRoute => $r->whereNumber('event')->missing($missingEvent);
    $round = static fn (RoutingRoute $r): RoutingRoute => $r->whereNumber('round')->missing($missingRound);
    $participant = static fn (RoutingRoute $r): RoutingRoute => $r->whereNumber('participant')->missing($missingParticipant);

    // Players (read access per event, checked in the controller).
    Route::get('', [PlayerController::class, 'index'])->name('index');
    // Read-only member event list; reachable also while a default event exists (/mixed-tournament opens that one).
    Route::get('events', [PlayerController::class, 'events'])->name('events');
    Route::get('info', [PlayerController::class, 'info'])->name('info');
    $event(Route::get('event/{event}', [PlayerController::class, 'event']))->name('event');
    $round(Route::get('round/{round}', [PlayerController::class, 'round']))->name('round');
    $round(Route::get('round/{round}/scorecard', [ScorecardController::class, 'round']))->name('scorecard');
    $event(Route::get('event/{event}/scorecard-blank', [ScorecardController::class, 'blank']))->name('scorecard-blank');
    $event(Route::get('event/{event}/durchmischung', [PlayerController::class, 'durchmischung']))->name('durchmischung');
    $event(Route::get('event/{event}/standings', [PlayerController::class, 'standings']))->name('standings');

    // The admin pages ignore the admin mode (global right or event grant, as stored).
    Route::prefix('admin')->name('admin.')->group(function () use ($event, $round, $participant): void {
        // The overview answers everyone.
        Route::get('', [EventAdminController::class, 'index'])->name('index');

        // Global administrator only.
        Route::middleware('can:admin.mixed')->group(function () use ($event): void {
            Route::post('create-event', [EventAdminController::class, 'createEvent'])->name('create-event');
            $event(Route::post('event/{event}/set-default', [EventAdminController::class, 'setDefault']))->name('set-default');
            $event(Route::post('event/{event}/set-test-mode', [EventAdminController::class, 'setTestMode']))->name('set-test-mode');
            $event(Route::post('event/{event}/grant', [EventAdminController::class, 'grant']))->name('grant');
            $event(Route::post('event/{event}/revoke', [EventAdminController::class, 'revoke']))->name('revoke');
            Route::get('import-members', [MemberImportController::class, 'show'])->name('import-members');
            Route::post('import-members', [MemberImportController::class, 'store'])->name('import-members.store');
        });

        // Event administration (ability checked in the controllers).
        $event(Route::post('event/{event}/set-court-count', [EventAdminController::class, 'setCourtCount']))->name('set-court-count');
        $event(Route::get('event/{event}', [EventAdminController::class, 'event']))->name('event');
        $event(Route::get('event/{event}/config', [EventAdminController::class, 'config']))->name('config');
        $event(Route::get('event/{event}/members', [ParticipantAdminController::class, 'memberSearch']))->name('member-search');
        $event(Route::post('event/{event}/match-members', [ParticipantAdminController::class, 'matchMembers']))->name('match-members');
        $event(Route::post('event/{event}/pause-all-participants', [ParticipantAdminController::class, 'pauseAll']))->name('pause-all');
        $event(Route::post('event/{event}/add-participant', [ParticipantAdminController::class, 'add']))->name('add-participant');
        $participant(Route::post('participant/{participant}/update', [ParticipantAdminController::class, 'update']))->name('update-participant');
        $participant(Route::post('participant/{participant}/status', [ParticipantAdminController::class, 'status']))->name('participant-status');
        $participant(Route::post('participant/{participant}/remove', [ParticipantAdminController::class, 'remove']))->name('remove-participant');
        $event(Route::post('event/{event}/add-avoid-pair', [ParticipantAdminController::class, 'addAvoidPair']))->name('add-avoid-pair');
        $event(Route::post('event/{event}/remove-avoid-pair', [ParticipantAdminController::class, 'removeAvoidPair']))->name('remove-avoid-pair');
        $event(Route::get('event/{event}/new-round', [RoundAdminController::class, 'newRound']))->name('new-round');
        $event(Route::post('event/{event}/create-round', [RoundAdminController::class, 'createRound']))->name('create-round');

        // Rounds (resolve their event; score or administer ability in the controllers).
        $round(Route::get('round/{round}', [RoundAdminController::class, 'show']))->name('round');

        foreach ([
            'update-config' => ['updateConfig', 'update-round-config'],
            'generate' => ['generate', 'generate'],
            'swap' => ['swap', 'swap'],
            'swap-sitter' => ['swapSitter', 'swap-sitter'],
            'confirm' => ['confirm', 'confirm'],
            'complete' => ['complete', 'complete'],
            'reopen' => ['reopen', 'reopen'],
            'delete' => ['delete', 'delete-round'],
            'score' => ['score', 'score'],
        ] as $path => [$action, $name]) {
            $round(Route::post('round/{round}/'.$path, [RoundAdminController::class, $action]))->name($name);
        }

        $event(Route::get('event/{event}/durchmischung', [EventAdminController::class, 'durchmischung']))->name('durchmischung');
        $event(Route::get('event/{event}/standings', [EventAdminController::class, 'standings']))->name('standings');
    });
});
