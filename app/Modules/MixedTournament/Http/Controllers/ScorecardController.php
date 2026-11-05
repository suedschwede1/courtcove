<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedRound;

/**
 * Placeholder: the actions are filled in by the UI tasks (17-22) and answer 501 until then.
 */
class ScorecardController extends Controller
{
    public function round(MixedRound $round): never
    {
        abort(501);
    }

    public function blank(MixedEvent $event): never
    {
        abort(501);
    }
}
