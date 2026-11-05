<?php

declare(strict_types=1);

namespace App\Modules\Pyramid\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Pyramid\Http\Controllers\Concerns\ResolvesPyramidViewer;
use App\Modules\Pyramid\Services\LadderAdminService;
use App\Modules\Pyramid\Services\PyramidRepository;
use App\Modules\Pyramid\Support\PyramidLinks;
use App\Modules\Pyramid\Support\PyramidRuleException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administration overview: ladders by season, overdue challenges and the form for a new ladder.
 *
 * The routes sit behind the login middleware; the global right is checked here through the Gate
 * (a grant holder administers single ladders, but not this global page).
 */
class LadderAdminController extends Controller
{
    use ResolvesPyramidViewer;

    public function __construct(
        private readonly PyramidRepository $repo,
        private readonly LadderAdminService $admin,
    ) {}

    public function index(): View
    {
        $this->requireGlobalAdmin();

        $bySeason = [];

        foreach ($this->repo->ladders() as $ladder) {
            $bySeason[(int) $ladder->season][] = [
                'ladder' => $ladder,
                'count' => $this->repo->countActive((int) $ladder->id),
            ];
        }

        return view('pyramid::admin.index', [
            'bySeason' => $bySeason,
            'overdue' => $this->repo->overdueChallenges(CarbonImmutable::now()->format('Y-m-d')),
            'year' => (int) CarbonImmutable::now()->format('Y'),
        ]);
    }

    /**
     * A GET (e.g. a reloaded form target) just returns to the overview, like the old action.
     */
    public function create(Request $request): RedirectResponse
    {
        $this->requireGlobalAdmin();

        $overview = redirect()->to(PyramidLinks::adminIndex());

        if (! $request->isMethod('POST')) {
            return $overview;
        }

        $year = (int) CarbonImmutable::now()->format('Y');
        $season = (int) $request->input('season', $year);

        try {
            $ladder = $this->admin->createLadder(
                (string) $request->input('title', ''),
                $season > 0 ? $season : $year,
                $request->input('category') === null ? null : (string) $request->input('category'),
            );
        } catch (PyramidRuleException $e) {
            return $overview->with('error', $e->verdict->message());
        }

        return redirect()->to(PyramidLinks::adminLadder((int) $ladder->id));
    }

    private function requireGlobalAdmin(): void
    {
        abort_unless($this->canAdmin(), 403, __('pyramid::admin.forbidden'));
    }
}
