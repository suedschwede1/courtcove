<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;

/**
 * Writes one protocol entry for a member: the snapshot before the change and the current one after
 * it, by the logged-in admin (null for console runs). Extra keys can be added to the after snapshot
 * for changes that live outside the member row (e.g. the household payer).
 */
final class MemberChangeLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $afterExtra
     */
    public static function log(Member $member, string $action, ?array $before, array $afterExtra = []): void
    {
        MemberChange::create([
            'member_id' => $member->id,
            'admin_user_id' => auth()->id(),
            'action' => $action,
            'before' => $before,
            'after' => [...($member->fresh()?->auditSnapshot() ?? $member->auditSnapshot()), ...$afterExtra],
        ]);
    }

    /**
     * The member row as stored right now, without loaded relations (the protocol compares flat snapshots).
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Member $member): array
    {
        return $member->fresh()?->auditSnapshot() ?? $member->auditSnapshot();
    }
}
