<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Support\Facades\DB;

/**
 * The single place that writes members.connected_to_member_id (Anschlussmitglied). Rules, checked with both member
 * rows locked (ascending id):
 * - no self link; both members current (aktiv or ruhend) and not anonymised;
 * - the Vollzahler has a 'vollzahler' fee category, is not connected itself and has no other Anschlussmitglied
 *   (so a connection is always exactly 2 persons); the Anschlussmitglied has the 'anschluss' fee category and no
 *   Anschlussmitglied of its own;
 * - neither is in a household with a per-household tariff (Familientarif), so nobody is billed twice.
 * Dues are never written here: each member's due comes from the member's own category in the normal dues run.
 * Every change is logged on both members (the Anschlussmitglied's snapshot, a 'connection' entry on the Vollzahler).
 */
final class MemberConnectionService
{
    /** @throws MemberConnectionException */
    public function connect(Member $anschluss, Member $vollzahler): void
    {
        if ($anschluss->id === $vollzahler->id) {
            throw new MemberConnectionException(__('members::admin.connection.errors.self'));
        }

        DB::transaction(function () use ($anschluss, $vollzahler): void {
            $locked = Member::query()->with('feeCategory')->whereKey([$anschluss->id, $vollzahler->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var Member $anschluss */
            $anschluss = $locked[$anschluss->id];
            /** @var Member $vollzahler */
            $vollzahler = $locked[$vollzahler->id];

            $this->assertCurrent($anschluss);
            $this->assertCurrent($vollzahler);
            if (! $vollzahler->feeCategory?->isVollzahler()) {
                throw new MemberConnectionException(__('members::admin.connection.errors.not_vollzahler', ['name' => $vollzahler->fullName()]));
            }
            if (! $anschluss->feeCategory?->isAnschluss()) {
                throw new MemberConnectionException(__('members::admin.connection.errors.not_anschluss', ['name' => $anschluss->fullName()]));
            }
            if ($vollzahler->connected_to_member_id !== null) {
                throw new MemberConnectionException(__('members::admin.connection.errors.vollzahler_connected', ['name' => $vollzahler->fullName()]));
            }
            if ($anschluss->connected_to_member_id !== null) {
                throw new MemberConnectionException(__('members::admin.connection.errors.already_connected', ['name' => $anschluss->fullName()]));
            }
            $others = Member::query()->where('connected_to_member_id', $vollzahler->id)->lockForUpdate()->get();
            if ($others->isNotEmpty()) {
                throw new MemberConnectionException(__('members::admin.connection.errors.vollzahler_taken', ['name' => $vollzahler->fullName(), 'partner' => $others->first()->fullName()]));
            }
            if (Member::query()->where('connected_to_member_id', $anschluss->id)->exists()) {
                throw new MemberConnectionException(__('members::admin.connection.errors.anschluss_has_partner', ['name' => $anschluss->fullName()]));
            }
            foreach ([$anschluss, $vollzahler] as $member) {
                if (self::inFamilyTariff($member)) {
                    throw new MemberConnectionException(__('members::admin.connection.errors.family_tariff', ['name' => $member->fullName()]));
                }
            }

            $this->write($anschluss, $vollzahler->id);
        });
    }

    /** Removes the link of the Anschlussmitglied; logged on both sides. */
    public function disconnect(Member $anschluss): void
    {
        DB::transaction(function () use ($anschluss): void {
            $locked = Member::query()->lockForUpdate()->findOrFail($anschluss->id);
            if ($locked->connected_to_member_id === null) {
                return;
            }
            $this->write($locked, null);
        });
    }

    /**
     * Inside the anonymise transaction, before its snapshot: the member's own link goes (recorded by the 'anonymized'
     * snapshot) and the links of members connected to it go, each logged on that member.
     */
    public function detachForAnonymisation(Member $member): void
    {
        DB::transaction(function () use ($member): void {
            if ($member->connected_to_member_id !== null) {
                $member->forceFill(['connected_to_member_id' => null])->save();
            }
            foreach (Member::query()->where('connected_to_member_id', $member->id)->lockForUpdate()->get() as $partner) {
                $before = MemberChangeLogger::snapshot($partner);
                $partner->forceFill(['connected_to_member_id' => null])->save();
                MemberChangeLogger::log($partner, 'updated', $before);
            }
        });
    }

    /** True when the member belongs to a household with a per-household tariff (Familientarif). */
    public static function inFamilyTariff(Member $member): bool
    {
        if ($member->household_id === null) {
            return false;
        }

        return MemberHousehold::query()->whereKey($member->household_id)
            ->whereHas('feeCategory', fn ($q) => $q->where('billing_unit', 'household'))
            ->exists();
    }

    /** True when the member is connected or has an Anschlussmitglied. */
    public static function isConnected(Member $member): bool
    {
        return $member->connected_to_member_id !== null
            || Member::query()->where('connected_to_member_id', $member->id)->exists();
    }

    private function assertCurrent(Member $member): void
    {
        if (! in_array($member->status, ['aktiv', 'ruhend'], true) || $member->anonymized_at !== null) {
            throw new MemberConnectionException(__('members::admin.connection.errors.not_current', ['name' => $member->fullName()]));
        }
    }

    /** Writes the link and logs it on the Anschlussmitglied (snapshot) and on the old/new Vollzahler ('connection'). */
    private function write(Member $anschluss, ?int $vollzahlerId): void
    {
        $oldId = $anschluss->connected_to_member_id;
        $before = MemberChangeLogger::snapshot($anschluss);
        $anschluss->forceFill(['connected_to_member_id' => $vollzahlerId])->save();
        MemberChangeLogger::log($anschluss, 'updated', $before);

        foreach (array_filter([$oldId, $vollzahlerId]) as $id) {
            $vollzahler = Member::query()->find($id);
            if ($vollzahler === null) {
                continue;
            }
            $isNew = $id === $vollzahlerId;
            MemberChangeLogger::log($vollzahler, 'connection',
                [...MemberChangeLogger::snapshot($vollzahler), 'anschluss_member' => $isNew ? null : $anschluss->id],
                ['anschluss_member' => $isNew ? $anschluss->id : null]);
        }
    }
}
