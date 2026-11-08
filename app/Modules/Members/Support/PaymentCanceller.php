<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * "Zahlung stornieren". In ONE transaction, fiscal-year rows first (same lock order as recording a payment and
 * closing a year), then the payment row, then the due rows:
 *  - refused (returns the closed year) when any covered due lies in a closed fiscal year,
 *  - idempotent: an already cancelled payment is left as it is ('already'),
 *  - otherwise the payment gets the storno (cancelled_at/by/reason; it is never deleted), its member and
 *    household dues are released: they count as unpaid again everywhere (the dues' payments() relation only
 *    sees uncancelled payments), are set to "ausstehend" and lose "Bezahlt am"; Teilbetrag stays.
 *  - one change-log entry per affected member: the payer, the owners of the released dues and the members
 *    covered by released household dues (there is no separate household log).
 */
final class PaymentCanceller
{
    public const CANCELLED = 'cancelled';

    public const ALREADY = 'already';

    /** member_changes.action (varchar 16). */
    public const CHANGE_ACTION = 'payment_cancel';

    public function cancel(Payment $payment, ?string $reason, ?int $userId): string|FiscalYear
    {
        // The links never change, so the years can be read before locking (year rows are locked first).
        $dueIds = DB::table('payment_dues')->where('payment_id', $payment->id)->pluck('member_due_id')->map(intval(...))->all();
        $householdDueIds = DB::table('household_due_payment')->where('payment_id', $payment->id)->pluck('household_due_id')->map(intval(...))->all();
        $yearIds = array_values(array_unique([
            ...MemberDue::query()->whereKey($dueIds)->pluck('fiscal_year_id')->all(),
            ...HouseholdDue::query()->whereKey($householdDueIds)->pluck('fiscal_year_id')->all(),
        ]));

        return DB::transaction(function () use ($payment, $reason, $userId, $dueIds, $householdDueIds, $yearIds): string|FiscalYear {
            $closed = FiscalYear::lockForWrite($yearIds);
            if ($closed !== null) {
                return $closed;
            }

            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->isCancelled()) {
                return self::ALREADY;
            }

            $dues = MemberDue::query()->whereKey($dueIds)->lockForUpdate()->get();
            $householdDues = HouseholdDue::query()->whereKey($householdDueIds)->with('coveredMembers')->lockForUpdate()->get();

            $locked->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancel_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ])->save();

            MemberDue::query()->whereKey($dueIds)->update(['status' => 'ausstehend', 'bezahlt_am' => null]);
            HouseholdDue::query()->whereKey($householdDueIds)->update(['status' => 'ausstehend', 'bezahlt_am' => null]);

            $memberIds = collect([(int) $locked->member_id])
                ->merge($dues->pluck('member_id'))
                ->merge($householdDues->flatMap(fn (HouseholdDue $due) => $due->coveredMembers->pluck('id')))
                ->map(intval(...))->unique()->values();

            $entry = [
                'id' => (int) $locked->id,
                'amount' => (string) $locked->amount,
                'paid_at' => $locked->paid_at->toDateString(),
                'reason' => $locked->cancel_reason,
                'member_due_ids' => $dueIds,
                'household_due_ids' => $householdDueIds,
            ];
            foreach (Member::query()->whereKey($memberIds->all())->get() as $member) {
                MemberChangeLogger::log($member, self::CHANGE_ACTION, MemberChangeLogger::snapshot($member), ['payment_cancelled' => $entry]);
            }

            return self::CANCELLED;
        });
    }
}
