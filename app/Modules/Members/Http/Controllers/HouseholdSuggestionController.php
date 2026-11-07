<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\HouseholdSuggestionDismissal;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Support\HouseholdRuleException;
use App\Modules\Members\Support\HouseholdService;
use App\Modules\Members\Support\HouseholdSuggestion;
use App\Modules\Members\Support\HouseholdSuggestionFinder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Mögliche Familien" (decision 8): suggestions only, confirmed or dismissed by hand; viewing creates
 * nothing. Behind the admin.members gate like the rest of the module.
 */
class HouseholdSuggestionController
{
    public function __construct(
        private readonly HouseholdSuggestionFinder $finder,
        private readonly HouseholdService $households,
    ) {}

    public function index(): View
    {
        $suggestions = $this->finder->find();
        $members = Member::query()->whereKey($suggestions->flatMap(fn (HouseholdSuggestion $s) => $s->memberIds)->all())
            ->get()->keyBy('id');

        return view('members::households.suggestions', [
            'suggestions' => $suggestions,
            'members' => $members,
            'tariffs' => FeeCategory::query()->perHousehold()->active()->orderBy('name')->get(),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'size:64'],
            'member_ids' => ['required', 'array', 'min:2'],
            'member_ids.*' => ['integer'],
            'fee_category_id' => ['bail', 'nullable', 'integer', HouseholdController::tariffRule()],
            'name' => ['nullable', 'string', 'max:128'],
        ]);

        $suggestion = $this->current($validated['signature']);
        $ids = array_values(array_unique(array_map(intval(...), $validated['member_ids'])));
        if (count($ids) < 2 || array_diff($ids, $suggestion->memberIds) !== []) {
            throw ValidationException::withMessages(['member_ids' => __('members::admin.households.suggestions.members_invalid')]);
        }

        try {
            $household = $this->households->create($ids, isset($validated['fee_category_id']) ? (int) $validated['fee_category_id'] : null, $validated['name'] ?? null);
        } catch (HouseholdRuleException $e) {
            return back()->withErrors(['member_ids' => $e->getMessage()]);
        }

        return redirect()->route('members.households.show', $household->id)->with('status', __('members::admin.households.created'));
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $validated = $request->validate(['signature' => ['required', 'string', 'size:64']]);
        $suggestion = $this->current($validated['signature']);

        HouseholdSuggestionDismissal::query()->firstOrCreate(
            ['signature' => $suggestion->signature],
            ['dismissed_by_user_id' => auth()->id()],
        );

        return redirect()->route('members.households.suggestions')->with('status', __('members::admin.households.suggestions.dismissed'));
    }

    /** The current suggestion with this signature (a stale or made-up signature is refused). */
    private function current(string $signature): HouseholdSuggestion
    {
        $suggestion = $this->finder->find()->first(fn (HouseholdSuggestion $s) => hash_equals($s->signature, $signature));
        if ($suggestion === null) {
            throw ValidationException::withMessages(['signature' => __('members::admin.households.suggestions.stale')]);
        }

        return $suggestion;
    }
}
