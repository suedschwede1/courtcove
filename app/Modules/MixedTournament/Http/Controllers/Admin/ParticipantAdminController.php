<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedParticipant;

/**
 * Placeholder: the actions are filled in by the UI tasks (17-22) and answer 501 until then.
 */
class ParticipantAdminController extends Controller
{
    public function memberSearch(MixedEvent $event): never
    {
        abort(501);
    }

    public function matchMembers(MixedEvent $event): never
    {
        abort(501);
    }

    public function pauseAll(MixedEvent $event): never
    {
        abort(501);
    }

    public function add(MixedEvent $event): never
    {
        abort(501);
    }

    public function update(MixedParticipant $participant): never
    {
        abort(501);
    }

    public function status(MixedParticipant $participant): never
    {
        abort(501);
    }

    public function remove(MixedParticipant $participant): never
    {
        abort(501);
    }

    public function addAvoidPair(MixedEvent $event): never
    {
        abort(501);
    }

    public function removeAvoidPair(MixedEvent $event): never
    {
        abort(501);
    }
}
