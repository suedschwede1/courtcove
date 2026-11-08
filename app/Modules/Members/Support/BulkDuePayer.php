<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bulk "Als bezahlt markieren" on the dues list. Every due gets the same result as "Zahlung erfassen" for that one
 * due: a payment row (full amount, chosen date, optional note) linked through payment_dues / household_due_payment,
 * the due set to "bezahlt" with "Bezahlt am". Payments are never deleted; a bulk payment is cancelled like any other.
 *
 * Each due runs in its OWN transaction (one failure never undoes the others), with the same lock order as the
 * single payment and the year close: the fiscal-year row first (closed = skipped), then the due row, then the
 * paid check again (a concurrent payment or a double submit = skipped). Rows outside the listed year, unknown ids
 * and already paid rows are skipped with a reason. A household due is paid once (ids are de-duplicated), by the
 * household's payer, else its first covered member. The caller caps the number of rows (LIMIT).
 */
final class BulkDuePayer
{
    /** Max rows per bulk run: about 10 queries per row keep a run of this size well inside the web time limit. */
    public const LIMIT = 500;

    public const REASONS = ['already_paid', 'closed_year', 'other_year', 'not_found', 'failed'];

    /**
     * @param  list<int>  $memberDueIds
     * @param  list<int>  $householdDueIds
     * @return array{paid: int, skipped: list<array{reason: string, label: string}>}
     */
    public function pay(int $fiscalYearId, array $memberDueIds, array $householdDueIds, string $paidAt, ?string $note): array
    {
        $result = ['paid' => 0, 'skipped' => []];
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        $memberDues = MemberDue::query()->with('member')->whereKey(array_values(array_unique($memberDueIds)))->get()->keyBy('id');
        foreach (array_values(array_unique($memberDueIds)) as $id) {
            $due = $memberDues->get($id);
            $outcome = $due === null ? 'not_found' : $this->payMemberDue($due, $fiscalYearId, $paidAt, $note);
            $this->record($result, $outcome, $due?->member?->fullName() ?? '#'.$id);
        }

        $householdDues = HouseholdDue::query()->with(['household.payer', 'coveredMembers'])->whereKey(array_values(array_unique($householdDueIds)))->get()->keyBy('id');
        foreach (array_values(array_unique($householdDueIds)) as $id) {
            $due = $householdDues->get($id);
            $outcome = $due === null ? 'not_found' : $this->payHouseholdDue($due, $fiscalYearId, $paidAt, $note);
            $this->record($result, $outcome, $due?->household?->label() ?? '#'.$id);
        }

        return $result;
    }

    /** @param array{paid: int, skipped: list<array{reason: string, label: string}>} $result */
    private function record(array &$result, string $outcome, string $label): void
    {
        if ($outcome === 'paid') {
            $result['paid']++;

            return;
        }
        $result['skipped'][] = ['reason' => $outcome, 'label' => $label];
    }

    private function payMemberDue(MemberDue $due, int $fiscalYearId, string $paidAt, ?string $note): string
    {
        if ((int) $due->fiscal_year_id !== $fiscalYearId) {
            return 'other_year';
        }

        return $this->guarded(function () use ($due, $paidAt, $note): string {
            if (FiscalYear::lockForWrite([(int) $due->fiscal_year_id]) !== null) {
                return 'closed_year';
            }
            $locked = MemberDue::query()->whereKey($due->id)->lockForUpdate()->first();
            if ($locked === null) {
                return 'not_found';
            }
            if (MemberDue::query()->whereKey($due->id)->paid()->lockForUpdate()->exists()) {
                return 'already_paid';
            }

            $payment = Payment::create(['member_id' => $locked->member_id, 'amount' => $locked->amount, 'paid_at' => $paidAt, 'note' => $note]);
            $payment->dues()->attach($locked->id);
            $locked->update(['status' => 'bezahlt', 'bezahlt_am' => $paidAt]);

            return 'paid';
        });
    }

    private function payHouseholdDue(HouseholdDue $due, int $fiscalYearId, string $paidAt, ?string $note): string
    {
        if ((int) $due->fiscal_year_id !== $fiscalYearId) {
            return 'other_year';
        }
        $payerId = $due->household->payer_member_id ?? $due->coveredMembers->sortBy('id')->first()->id
            ?? $due->household->members()->orderBy('id')->value('id');
        if ($payerId === null) {
            return 'failed';
        }

        return $this->guarded(function () use ($due, $paidAt, $note, $payerId): string {
            if (FiscalYear::lockForWrite([(int) $due->fiscal_year_id]) !== null) {
                return 'closed_year';
            }
            $locked = HouseholdDue::query()->whereKey($due->id)->lockForUpdate()->first();
            if ($locked === null) {
                return 'not_found';
            }
            if (HouseholdDue::query()->whereKey($due->id)->paid()->lockForUpdate()->exists()) {
                return 'already_paid';
            }

            $payment = Payment::create(['member_id' => (int) $payerId, 'amount' => $locked->amount, 'paid_at' => $paidAt, 'note' => $note]);
            $payment->householdDues()->attach($locked->id);
            $locked->update(['status' => 'bezahlt', 'bezahlt_am' => $paidAt]);

            return 'paid';
        });
    }

    /** One transaction per due; an unexpected error of one row is reported and skipped, never fatal for the run. */
    private function guarded(callable $work): string
    {
        try {
            return DB::transaction($work);
        } catch (Throwable $e) {
            report($e);

            return 'failed';
        }
    }
}
