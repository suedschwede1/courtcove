<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;
use App\Services\Notifications\ConsentService;

/**
 * Ends the WhatsApp consent of the linked booking account when the member leaves (status ausgetreten),
 * is anonymised, is unlinked from the account or deleted. Dormant (ruhend) only pauses through the gate.
 * Mass updates fire no events; the send-time gate covers them.
 */
final class MemberConsentObserver
{
    public function __construct(private readonly ConsentService $consents) {}

    public function updated(Member $member): void
    {
        // getOriginal() still holds the values before this save: anonymisation clears user_id in the same update.
        $userId = (int) $member->getOriginal('user_id');
        if ($userId === 0) {
            return;
        }

        $via = match (true) {
            $member->wasChanged('anonymized_at') && $member->anonymized_at !== null => 'anonymized',
            $member->wasChanged('status') && $member->status === 'ausgetreten' => 'member_exit',
            $member->wasChanged('user_id') => 'member_exit',
            default => null,
        };

        if ($via !== null) {
            $this->consents->revokeForUserId($userId, $via);
        }
    }

    public function deleted(Member $member): void
    {
        if ($member->user_id !== null) {
            $this->consents->revokeForUserId((int) $member->user_id, 'member_exit');
        }
    }
}
