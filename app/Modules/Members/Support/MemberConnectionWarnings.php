<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;

/**
 * Warnings about the Anschlussmitglied state of a member: shown on the member page and in the dues run report.
 * They only describe what is wrong; nothing is changed automatically (club rule). Since the club decision of
 * 2026-11-08 categories are not checked (each member pays its own category); the only category-based line left is
 * the information that a member of the Anschluss category has no link yet.
 */
final class MemberConnectionWarnings
{
    /** @return list<string> */
    public static function forMember(Member $member): array
    {
        $member->loadMissing(['feeCategory', 'connectedTo.feeCategory', 'anschlussMembers', 'household.feeCategory']);
        $warnings = [];

        if ($member->feeCategory?->isAnschluss() && $member->connected_to_member_id === null && $member->anschlussMembers->isEmpty()) {
            $warnings[] = __('members::admin.connection.warnings.anschluss_without_connection');
        }

        $partner = $member->connectedTo;
        if ($member->connected_to_member_id !== null && $partner !== null) {
            if (! in_array($partner->status, ['aktiv', 'ruhend'], true) || $partner->anonymized_at !== null) {
                $warnings[] = __('members::admin.connection.warnings.partner_departed', ['name' => $partner->fullName()]);
            }
            if ($partner->connected_to_member_id !== null) {
                $warnings[] = __('members::admin.connection.warnings.partner_connected', ['name' => $partner->fullName()]);
            }
        }

        if ($member->anschlussMembers->isNotEmpty()) {
            // A departed or anonymised Anschlussmitglied still blocks a new partner: say so.
            $current = $member->anschlussMembers->filter(fn (Member $m) => in_array($m->status, ['aktiv', 'ruhend'], true) && $m->anonymized_at === null);
            foreach ($member->anschlussMembers->diff($current) as $gone) {
                $warnings[] = __('members::admin.connection.warnings.anschluss_departed', ['name' => $gone->fullName()]);
            }
            if ($current->count() > 1) {
                $names = $current->map(fn (Member $m) => $m->fullName())->sort()->values()->implode(', ');
                $warnings[] = __('members::admin.connection.warnings.too_many_anschluss', ['names' => $names]);
            }
        }

        $inFamilyTariff = $member->household !== null && $member->household->feeCategory?->isPerHousehold();
        if (($member->connected_to_member_id !== null || $member->anschlussMembers->isNotEmpty()) && $inFamilyTariff) {
            $warnings[] = __('members::admin.connection.warnings.family_tariff');
        }

        return $warnings;
    }

    /**
     * Warnings of every current member that has any (for the dues run report), keyed by member id, together with the
     * member itself (all relations the checks need are eager-loaded: no query per member).
     *
     * @return array<int, array{member: Member, warnings: list<string>}>
     */
    public static function all(): array
    {
        $members = Member::query()
            ->whereIn('status', ['aktiv', 'ruhend'])
            ->whereNull('anonymized_at')
            ->where(fn ($q) => $q->whereNotNull('connected_to_member_id')
                ->orWhereHas('anschlussMembers')
                ->orWhereHas('feeCategory', fn ($c) => $c->where('connection_role', 'anschluss')))
            ->with(['feeCategory', 'connectedTo.feeCategory', 'anschlussMembers', 'household.feeCategory'])
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        $all = [];
        foreach ($members as $member) {
            $warnings = self::forMember($member);
            if ($warnings !== []) {
                $all[$member->id] = ['member' => $member, 'warnings' => $warnings];
            }
        }

        return $all;
    }

    /**
     * The notice for the member that stays when a link ended because the partner departed ("Der Anschluss an … /
     * von … wurde am … gelöst"), as long as no later protocol entry changed the connection; null otherwise and on the
     * departed member itself.
     */
    public static function endedNotice(Member $member): ?string
    {
        if ($member->connected_to_member_id !== null) {
            return null;
        }
        $changes = MemberChange::query()->where('member_id', $member->id)->orderByDesc('id')->limit(100)->get(['id', 'action', 'before', 'after', 'created_at']);
        foreach ($changes as $change) {
            if ($change->action === MemberConnectionService::ENDED_ACTION) {
                $departedId = (int) ($change->after['connection_ended_by'] ?? 0);
                if ($departedId === $member->id) {
                    return null;
                }
                $departed = Member::query()->find($departedId);
                // Older entries (Vollzahler departed) have no partner key: the member was the Anschlussmitglied.
                $wasVollzahler = array_key_exists('anschluss_member', (array) $change->before);

                return (string) __($wasVollzahler ? 'members::admin.connection.ended_notice_anschluss' : 'members::admin.connection.ended_notice', [
                    'name' => $departed?->fullName() ?? '—',
                    'date' => $change->created_at?->format('d.m.Y') ?? '—',
                ]);
            }
            // Any later change of the connection (own link, an Anschlussmitglied on this member, a swap) supersedes it.
            if (in_array($change->action, ['connection', MemberConnectionService::SWAP_ACTION], true)
                || ($change->before['connected_to_member_id'] ?? null) !== ($change->after['connected_to_member_id'] ?? null)) {
                return null;
            }
        }

        return null;
    }
}
