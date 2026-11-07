<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;

/**
 * Warnings about the Anschlussmitglied state of a member: shown on the member page and in the dues run report.
 * They only describe what is wrong; nothing is changed automatically (club rule).
 */
final class MemberConnectionWarnings
{
    /** @return list<string> */
    public static function forMember(Member $member): array
    {
        $member->loadMissing(['feeCategory', 'connectedTo.feeCategory', 'anschlussMembers']);
        $warnings = [];

        if ($member->feeCategory?->isAnschluss() && $member->connected_to_member_id === null && $member->anschlussMembers->isEmpty()) {
            $warnings[] = __('members::admin.connection.warnings.anschluss_without_connection');
        }

        $partner = $member->connectedTo;
        if ($member->connected_to_member_id !== null && $partner !== null) {
            if (! $member->feeCategory?->isAnschluss()) {
                $warnings[] = __('members::admin.connection.warnings.connected_category_not_anschluss');
            }
            if (! in_array($partner->status, ['aktiv', 'ruhend'], true) || $partner->anonymized_at !== null) {
                $warnings[] = __('members::admin.connection.warnings.partner_departed', ['name' => $partner->fullName()]);
            }
            if (! $partner->feeCategory?->isVollzahler()) {
                $warnings[] = __('members::admin.connection.warnings.partner_not_vollzahler', ['name' => $partner->fullName()]);
            }
            if ($partner->connected_to_member_id !== null) {
                $warnings[] = __('members::admin.connection.warnings.partner_connected', ['name' => $partner->fullName()]);
            }
        }

        if ($member->anschlussMembers->isNotEmpty()) {
            if (! $member->feeCategory?->isVollzahler()) {
                $warnings[] = __('members::admin.connection.warnings.not_vollzahler_with_anschluss');
            }
            if ($member->anschlussMembers->count() > 1) {
                $names = $member->anschlussMembers->map(fn (Member $m) => $m->fullName())->sort()->values()->implode(', ');
                $warnings[] = __('members::admin.connection.warnings.too_many_anschluss', ['names' => $names]);
            }
        }

        if (($member->connected_to_member_id !== null || $member->anschlussMembers->isNotEmpty()) && MemberConnectionService::inFamilyTariff($member)) {
            $warnings[] = __('members::admin.connection.warnings.family_tariff');
        }

        return $warnings;
    }

    /**
     * Warnings of every current member that has any (for the dues run report), keyed by member id.
     *
     * @return array<int, list<string>>
     */
    public static function all(): array
    {
        $members = Member::query()
            ->whereIn('status', ['aktiv', 'ruhend'])
            ->whereNull('anonymized_at')
            ->where(fn ($q) => $q->whereNotNull('connected_to_member_id')
                ->orWhereHas('anschlussMembers')
                ->orWhereHas('feeCategory', fn ($c) => $c->where('connection_role', 'anschluss')))
            ->with(['feeCategory', 'connectedTo.feeCategory', 'anschlussMembers'])
            ->orderBy('lastname')->orderBy('firstname')
            ->get();

        $all = [];
        foreach ($members as $member) {
            $warnings = self::forMember($member);
            if ($warnings !== []) {
                $all[$member->id] = $warnings;
            }
        }

        return $all;
    }
}
