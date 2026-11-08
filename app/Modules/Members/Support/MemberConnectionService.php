<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Support\Facades\DB;

/**
 * The single place that writes members.connected_to_member_id (Anschlussmitglied). Club decision 2026-11-08: no
 * category or role checks (each member simply pays its own category; Pensionisten etc. can be the Vollzahler). What
 * stays, checked with both member rows locked (ascending id), protects the data:
 * - no self link; both members current (aktiv or ruhend) and not anonymised (a departure ends a link anyway);
 * - no chain or cycle: the Vollzahler is not connected itself, the Anschlussmitglied has no Anschlussmitglied;
 * - exactly 2 persons: the Vollzahler has no other Anschlussmitglied;
 * - neither is in a household with a per-household tariff (Familientarif), so nobody is billed twice.
 * Dues are never written here: each member's due comes from the member's own category in the normal dues run.
 * Every change is logged on both members. When the Vollzahler departs, its links end (memberDeparted); "Rollen
 * tauschen" swaps the two sides in one transaction (swap).
 */
final class MemberConnectionService
{
    /** Protocol action on an Anschlussmitglied whose link ended because the Vollzahler departed (max 16 chars). */
    public const ENDED_ACTION = 'conn_ended';

    /** Protocol action on both members of a role swap (max 16 chars). */
    public const SWAP_ACTION = 'conn_swap';

    /** @throws MemberConnectionException */
    public function connect(Member $anschluss, Member $vollzahler): void
    {
        if ($anschluss->id === $vollzahler->id) {
            throw new MemberConnectionException(__('members::admin.connection.errors.self'));
        }

        DB::transaction(function () use ($anschluss, $vollzahler): void {
            $locked = Member::query()->whereKey([$anschluss->id, $vollzahler->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var Member $anschluss */
            $anschluss = $locked[$anschluss->id];
            /** @var Member $vollzahler */
            $vollzahler = $locked[$vollzahler->id];

            $this->assertCurrent($anschluss);
            $this->assertCurrent($vollzahler);
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
     * The member departed (Austrittsdatum set): every Anschlussmitglied connected to it loses the link (club decision:
     * the connection no longer applies). Each is logged with ENDED_ACTION (the member page shows a notice from it) and
     * the Vollzahler gets a 'connection' entry per partner. Categories and dues stay as they are. Called inside the
     * departure transaction; a departing Anschlussmitglied keeps its own link (reported as a warning).
     */
    public function memberDeparted(Member $vollzahler): void
    {
        DB::transaction(function () use ($vollzahler): void {
            $this->lockForDeparture($vollzahler);
            $partners = Member::query()->where('connected_to_member_id', $vollzahler->id)->orderBy('id')->lockForUpdate()->get();
            foreach ($partners as $partner) {
                $before = MemberChangeLogger::snapshot($partner);
                $partner->forceFill(['connected_to_member_id' => null])->save();
                MemberChangeLogger::log($partner, self::ENDED_ACTION, $before, ['connection_ended_by' => $vollzahler->id]);
                MemberChangeLogger::log($vollzahler, 'connection',
                    [...MemberChangeLogger::snapshot($vollzahler), 'anschluss_member' => $partner->id],
                    ['anschluss_member' => null]);
            }
        });
    }

    /**
     * Locks the departing member and the members connected to it in ascending id order (the order of connect() and
     * swap()). The departure flow calls this before it writes the member row, so it never holds the Vollzahler row
     * while waiting for a partner row that a swap holds.
     */
    public function lockForDeparture(Member $vollzahler): void
    {
        $ids = Member::query()->where('connected_to_member_id', $vollzahler->id)->pluck('id')->push($vollzahler->id)->all();
        Member::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /**
     * "Rollen tauschen": the Anschlussmitglied becomes the Vollzahler and vice versa, started from either side. One
     * transaction with both rows locked; refused unless the pair is exactly two current persons. Categories and dues
     * are not touched. Returns the number of open dues of both (reported to the admin, never rewritten).
     *
     * @throws MemberConnectionException
     */
    public function swap(Member $member): int
    {
        return DB::transaction(function () use ($member): int {
            // Find the pair with a plain read, then lock both rows in ascending id order (as connect() does) and check
            // again: locking the started-from row first could deadlock with a swap started from the other side.
            $member = Member::query()->findOrFail($member->id);
            if ($member->connected_to_member_id !== null) {
                $anschlussId = $member->id;
                $vollzahlerId = (int) $member->connected_to_member_id;
            } else {
                $partnerIds = Member::query()->where('connected_to_member_id', $member->id)->pluck('id');
                if ($partnerIds->count() !== 1) {
                    throw new MemberConnectionException(__($partnerIds->isEmpty() ? 'members::admin.connection.errors.swap_none' : 'members::admin.connection.errors.swap_many', ['name' => $member->fullName()]));
                }
                $anschlussId = (int) $partnerIds->first();
                $vollzahlerId = $member->id;
            }

            $locked = Member::query()->whereKey([$anschlussId, $vollzahlerId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var Member|null $anschluss */
            $anschluss = $locked->get($anschlussId);
            /** @var Member|null $vollzahler */
            $vollzahler = $locked->get($vollzahlerId);
            if ($anschluss === null || $vollzahler === null || $anschluss->connected_to_member_id !== $vollzahler->id || $vollzahler->connected_to_member_id !== null) {
                throw new MemberConnectionException(__('members::admin.connection.errors.swap_changed'));
            }
            $this->assertCurrent($anschluss);
            $this->assertCurrent($vollzahler);
            $partners = Member::query()->where('connected_to_member_id', $vollzahler->id)->lockForUpdate()->count();
            if ($partners !== 1) {
                throw new MemberConnectionException(__('members::admin.connection.errors.swap_many', ['name' => $vollzahler->fullName()]));
            }
            if (Member::query()->where('connected_to_member_id', $anschluss->id)->exists()) {
                throw new MemberConnectionException(__('members::admin.connection.errors.swap_changed'));
            }

            $beforeAnschluss = MemberChangeLogger::snapshot($anschluss);
            $beforeVollzahler = MemberChangeLogger::snapshot($vollzahler);
            $anschluss->forceFill(['connected_to_member_id' => null])->save();
            $vollzahler->forceFill(['connected_to_member_id' => $anschluss->id])->save();
            MemberChangeLogger::log($anschluss, self::SWAP_ACTION, $beforeAnschluss, ['swapped_with' => $vollzahler->id]);
            MemberChangeLogger::log($vollzahler, self::SWAP_ACTION, $beforeVollzahler, ['swapped_with' => $anschluss->id]);

            return MemberDue::query()->whereIn('member_id', [$anschluss->id, $vollzahler->id])->open()->count();
        });
    }

    /**
     * Inside the anonymise transaction, before its snapshot: the member's own link goes (recorded by the 'anonymized'
     * snapshot) and the links of members connected to it go, each logged on that member.
     */
    public function detachForAnonymisation(Member $member): void
    {
        DB::transaction(function () use ($member): void {
            $locked = Member::query()->lockForUpdate()->findOrFail($member->id);
            $oldVollzahlerId = $locked->connected_to_member_id;
            if ($oldVollzahlerId !== null) {
                $locked->forceFill(['connected_to_member_id' => null])->save();
                $member->connected_to_member_id = null;
                $vollzahler = Member::query()->find($oldVollzahlerId);
                if ($vollzahler !== null) {
                    MemberChangeLogger::log($vollzahler, 'connection',
                        [...MemberChangeLogger::snapshot($vollzahler), 'anschluss_member' => $locked->id],
                        ['anschluss_member' => null]);
                }
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
