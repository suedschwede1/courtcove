<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Support\Str;

/**
 * Change-protocol entries for a recorded payment ("Zahlung erfassen" and the bulk "Als bezahlt markieren"; club
 * decision 2026-11-08). One entry per affected member (the payer, the owners of the covered member dues and the members
 * covered by the covered household dues, as the storno logs it) with action CHANGE_ACTION and, in the after snapshot,
 * 'payment_recorded' => id, amount, payment date, a short note, the bulk run id (null for a single payment) and the
 * covered dues (kind, year name, category name, amount, household label for household dues). Who and when are the
 * entry's own admin_user_id and created_at. Called inside the payment's transaction.
 */
final class PaymentProtocol
{
    /** member_changes.action is varchar(16). */
    public const CHANGE_ACTION = 'payment';

    /** The note is kept short in the protocol (the payment keeps the full note). */
    public const NOTE_LENGTH = 80;

    /** A new id shared by all entries of one bulk run. */
    public static function newBatch(): string
    {
        return Str::lower((string) Str::ulid());
    }

    /** The visible part of a run id ("#K3F9QZ"). */
    public static function shortBatch(string $batch): string
    {
        return '#'.Str::upper(substr($batch, -6));
    }

    /**
     * @param  iterable<MemberDue>  $memberDues
     * @param  iterable<HouseholdDue>  $householdDues
     */
    public static function log(Payment $payment, iterable $memberDues, iterable $householdDues, ?string $batch = null): void
    {
        $memberDues = collect($memberDues)->each(fn (MemberDue $due) => $due->loadMissing(['fiscalYear', 'feeCategory']));
        $householdDues = collect($householdDues)->each(fn (HouseholdDue $due) => $due->loadMissing(['fiscalYear', 'feeCategory', 'household', 'coveredMembers']));

        $note = $payment->note !== null && trim($payment->note) !== '' ? trim($payment->note) : null;
        if ($note !== null && mb_strlen($note) > self::NOTE_LENGTH) {
            $note = rtrim(mb_substr($note, 0, self::NOTE_LENGTH - 1)).'…';
        }

        $dues = [
            ...$memberDues->map(fn (MemberDue $due): array => [
                'kind' => 'member',
                'year' => (string) $due->fiscalYear->name,
                'category' => (string) ($due->feeCategory->name ?? '–'),
                'amount' => number_format((float) $due->amount, 2, '.', ''),
            ])->all(),
            ...$householdDues->map(fn (HouseholdDue $due): array => [
                'kind' => 'household',
                'year' => (string) $due->fiscalYear->name,
                'category' => (string) ($due->feeCategory->name ?? '–'),
                'amount' => number_format((float) $due->amount, 2, '.', ''),
                'household' => $due->household->label(),
            ])->all(),
        ];

        $entry = [
            'id' => (int) $payment->id,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'paid_at' => $payment->paid_at->toDateString(),
            'note' => $note,
            'batch' => $batch,
            'dues' => $dues,
        ];

        $memberIds = collect([(int) $payment->member_id])
            ->merge($memberDues->pluck('member_id'))
            ->merge($householdDues->flatMap(fn (HouseholdDue $due) => $due->coveredMembers->pluck('id')))
            ->map(intval(...))->unique()->values()->all();

        foreach (Member::query()->whereKey($memberIds)->orderBy('id')->get() as $member) {
            MemberChangeLogger::log($member, self::CHANGE_ACTION, MemberChangeLogger::snapshot($member), ['payment_recorded' => $entry]);
        }
    }
}
