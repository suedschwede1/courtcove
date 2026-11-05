<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Support\PyramidLinks;
use App\Modules\Pyramid\Support\PyramidRuleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Late sign-up: the user lands on the waiting list, the administration admits them.
 */
class JoinController extends Controller
{
    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly LadderAdminService $admin,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $ladder = $this->repo->ladder((int) $request->input('ladderId'));

        if ($ladder === null) {
            abort(404, __('pyramid::ui.no_ladder'));
        }

        $target = redirect()->to(PyramidLinks::ladder((int) $ladder->id));

        if ($this->repo->playerByUid((int) $ladder->id, (int) $user->uid) !== null) {
            return $target->with('info', __('pyramid::ui.join.already'));
        }

        // The label comes from the profile like in the old code; the alias only fills in for an empty profile.
        $label = trim($user->getMeta('firstname', '').' '.$user->getMeta('lastname', ''));

        try {
            $player = $this->admin->addPlayer($ladder, [
                'uid' => (int) $user->uid,
                'label' => $label !== '' ? $label : (string) $user->alias,
                'itn' => $request->string('itn')->toString(),
            ]);
        } catch (PyramidRuleException $e) {
            return $target->with('error', $e->verdict->message());
        }

        $this->repo->addEvent((int) $ladder->id, 'join', ['player' => (int) $player->id], null, (int) $user->uid);

        return $target->with('success', __('pyramid::ui.join.done'));
    }
}
