<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedRound;

/**
 * Placeholder: the actions are filled in by the UI tasks (17-22) and answer 501 until then.
 */
class RoundAdminController extends Controller
{
    public function newRound(MixedEvent $event): never
    {
        abort(501);
    }

    public function createRound(MixedEvent $event): never
    {
        abort(501);
    }

    public function show(MixedRound $round): never
    {
        abort(501);
    }

    public function updateConfig(MixedRound $round): never
    {
        abort(501);
    }

    public function generate(MixedRound $round): never
    {
        abort(501);
    }

    public function swap(MixedRound $round): never
    {
        abort(501);
    }

    public function swapSitter(MixedRound $round): never
    {
        abort(501);
    }

    public function confirm(MixedRound $round): never
    {
        abort(501);
    }

    public function complete(MixedRound $round): never
    {
        abort(501);
    }

    public function reopen(MixedRound $round): never
    {
        abort(501);
    }

    public function delete(MixedRound $round): never
    {
        abort(501);
    }

    public function score(MixedRound $round): never
    {
        abort(501);
    }
}
