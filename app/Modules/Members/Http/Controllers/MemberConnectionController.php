<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Support\MemberConnectionException;
use App\Modules\Members\Support\MemberConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Anschlussmitglied on the member page: connect this member to a Vollzahler, or remove the link. Ids are plain
 * route parameters looked up after the admin.members gate; every write goes through MemberConnectionService.
 */
class MemberConnectionController
{
    public function __construct(private readonly MemberConnectionService $connections) {}

    public function store(Request $request, int $member): RedirectResponse
    {
        $anschluss = Member::query()->findOrFail($member);
        $validated = $request->validate(['vollzahler_id' => ['required', 'integer']]);
        $vollzahler = Member::query()->find((int) $validated['vollzahler_id']);
        if ($vollzahler === null) {
            return back()->withErrors(['connection' => __('members::admin.connection.errors.member_not_found')]);
        }

        try {
            $this->connections->connect($anschluss, $vollzahler);
        } catch (MemberConnectionException $e) {
            return back()->withErrors(['connection' => $e->getMessage()]);
        }

        return redirect()->route('members.edit', $anschluss)->with('status', __('members::admin.connection.connected'));
    }

    public function destroy(int $member): RedirectResponse
    {
        $anschluss = Member::query()->findOrFail($member);
        $this->connections->disconnect($anschluss);

        return redirect()->route('members.edit', $anschluss)->with('status', __('members::admin.connection.disconnected'));
    }
}
