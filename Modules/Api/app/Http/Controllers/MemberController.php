<?php

declare(strict_types=1);

namespace Modules\Api\Http\Controllers;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Api\Http\Requests\MemberRequest;
use Modules\Api\Transformers\MemberResource;

class MemberController extends ApiController
{
    public function store(MemberRequest $request): JsonResponse
    {
        $validated = collect($request->validated())->except(['left_at', 'club_roles'])->all();

        $member = DB::transaction(function () use ($request, $validated): Member {
            $member = Member::create($validated);
            $member->setLeftAt($request->validated()['left_at'] ?? null);
            $member->clubRoles()->sync($request->input('club_roles', []));
            $this->logChange($member, 'created', null);

            return $member;
        });

        return $this->success(new MemberResource($member), 201);
    }

    public function update(MemberRequest $request, Member $member): JsonResponse
    {
        $validated = collect($request->validated())->except(['left_at', 'club_roles'])->all();
        $leftAt = $request->has('left_at') ? $request->validated()['left_at'] : $member->left_at?->toDateString();
        $before = $member->auditSnapshot();
        $departs = $before['left_at'] === null && $leftAt !== null;

        DB::transaction(function () use ($request, $member, $validated, $before, $leftAt, $departs): void {
            $member->update($validated);
            if ($request->has('club_roles')) {
                $member->clubRoles()->sync($request->validated()['club_roles']);
            }
            $member->setLeftAt($leftAt);
            $this->logChange($member, $departs ? 'departed' : 'updated', $before);
        });

        return $this->success(new MemberResource($member->fresh()));
    }

    private function logChange(Member $member, string $action, ?array $before): void
    {
        MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => auth()->id(),
            'action' => $action,
            'before' => $before,
            'after' => $member->fresh()->auditSnapshot(),
        ]);
    }
}
