<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\HouseholdDue;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\MembersFormat;
use App\Modules\Members\Support\PaymentCanceller;
use App\Modules\Members\Support\PaymentProtocol;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController
{
    public function create(Member $member): View
    {
        // Dues of a closed business year can no longer be paid, so they are not offered.
        $openDues = $member->dues()
            ->open()
            ->whereHas('fiscalYear', fn ($q) => $q->whereNull('abgeschlossen_am'))
            ->with(['fiscalYear', 'feeCategory'])
            ->get();

        return view('members::payments.create', [
            'member' => $member,
            'openDues' => $openDues,
            'openHouseholdDues' => self::householdDuesFor($member)->open()
                ->whereHas('fiscalYear', fn ($q) => $q->whereNull('abgeschlossen_am'))
                ->with(['fiscalYear', 'household.members'])->get(),
        ]);
    }

    /**
     * Household dues a payment on this member's page may settle: those covering the member and those of
     * the member's current household (the person who actually pays enters it on their own page).
     *
     * @return Builder<HouseholdDue>
     */
    private static function householdDuesFor(Member $member): Builder
    {
        return HouseholdDue::query()->where(function (Builder $q) use ($member): void {
            $q->whereHas('coveredMembers', fn ($m) => $m->whereKey($member->id));
            if ($member->household_id !== null) {
                $q->orWhere('household_id', $member->household_id);
            }
        });
    }

    public function store(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            'paid_at' => ['required', ...MemberDue::paidOnRules()],
            'note' => ['nullable', 'string', 'max:255'],
            'member_due_ids' => ['required_without:household_due_ids', 'array', 'min:1'],
            'member_due_ids.*' => [
                'integer',
                'distinct',
                'exists:member_dues,id,member_id,'.$member->id,
                function (string $attribute, mixed $value, callable $fail): void {
                    $isAlreadyPaid = MemberDue::query()
                        ->whereKey($value)
                        ->paid()
                        ->exists();

                    if ($isAlreadyPaid) {
                        $fail(__('members::admin.payments.due_already_paid'));

                        return;
                    }

                    $inClosedYear = MemberDue::query()
                        ->whereKey($value)
                        ->whereHas('fiscalYear', fn ($q) => $q->whereNotNull('abgeschlossen_am'))
                        ->exists();

                    if ($inClosedYear) {
                        $fail(__('members::admin.payments.due_in_closed_year'));
                    }
                },
            ],
            'household_due_ids' => ['nullable', 'array', 'min:1'],
            'household_due_ids.*' => [
                'integer',
                'distinct',
                function (string $attribute, mixed $value, callable $fail) use ($member): void {
                    $due = is_int($value) || (is_string($value) && ctype_digit($value))
                        ? self::householdDuesFor($member)->whereKey((int) $value)->with('fiscalYear')->first()
                        : null;
                    if ($due === null) {
                        $fail(__('members::admin.payments.household_due_not_related'));
                    } elseif ($due->isPaid()) {
                        $fail(__('members::admin.payments.due_already_paid'));
                    } elseif ($due->fiscalYear->isClosed()) {
                        $fail(__('members::admin.payments.due_in_closed_year'));
                    }
                },
            ],
        ], MemberDue::paidOnMessages('paid_at'));

        $dueIds = array_map(intval(...), $validated['member_due_ids'] ?? []);
        $householdDueIds = array_map(intval(...), $validated['household_due_ids'] ?? []);

        // The payment must cover the selected dues exactly - otherwise a partial
        // payment would mark them as paid and block the remainder forever.
        $expectedCents = (int) MemberDue::query()->whereKey($dueIds)->get()
            ->sum(fn (MemberDue $due): int => (int) round(((float) $due->amount) * 100))
            + (int) HouseholdDue::query()->whereKey($householdDueIds)->get()
                ->sum(fn (HouseholdDue $due): int => (int) round(((float) $due->amount) * 100));
        $paidCents = (int) round(((float) $validated['amount']) * 100);

        if ($paidCents !== $expectedCents) {
            throw ValidationException::withMessages([
                'amount' => __('members::admin.payments.amount_mismatch', [
                    'sum' => MembersFormat::money($expectedCents / 100),
                ]),
            ]);
        }

        $errorKey = $dueIds !== [] ? 'member_due_ids' : 'household_due_ids';

        DB::transaction(function () use ($validated, $member, $dueIds, $householdDueIds, $errorKey): void {
            // Lock order: the dues' year rows first, then the due rows. A year closed since
            // validation refuses the payment here.
            $yearIds = [
                ...MemberDue::query()->whereKey($dueIds)->pluck('fiscal_year_id')->all(),
                ...HouseholdDue::query()->whereKey($householdDueIds)->pluck('fiscal_year_id')->all(),
            ];
            if (FiscalYear::lockForWrite($yearIds) !== null) {
                throw ValidationException::withMessages([
                    $errorKey => __('members::admin.payments.due_in_closed_year'),
                ]);
            }

            // Re-check inside the transaction (with the due rows locked) so two
            // concurrent submissions cannot allocate the same due twice.
            $lockedDues = MemberDue::whereKey($dueIds)->orderBy('id')->lockForUpdate()->get();
            $lockedHouseholdDues = HouseholdDue::whereKey($householdDueIds)->orderBy('id')->lockForUpdate()->get();
            $alreadyPaid = $lockedDues->contains(fn (MemberDue $due): bool => $due->isPaid())
                || $lockedHouseholdDues->contains(fn (HouseholdDue $due): bool => $due->isPaid());
            $expected = $lockedDues->concat($lockedHouseholdDues)->sum(fn ($due): int => (int) round((float) $due->amount * 100));
            if ($expected !== (int) round((float) $validated['amount'] * 100)) {
                throw ValidationException::withMessages(['amount' => __('members::admin.payments.amount_mismatch', ['sum' => MembersFormat::money($expected / 100)])]);
            }

            if ($alreadyPaid) {
                throw ValidationException::withMessages([
                    $errorKey => __('members::admin.payments.due_already_paid'),
                ]);
            }

            $payment = Payment::create([
                'member_id' => $member->id,
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'],
                'note' => $validated['note'] ?? null,
            ]);

            // A payment always covers its dues in full (checked above), so its date is
            // the date each of these dues was settled.
            if ($dueIds !== []) {
                $payment->dues()->attach($dueIds);
                MemberDue::whereKey($dueIds)->update(['status' => 'bezahlt', 'bezahlt_am' => $validated['paid_at']]);
            }
            if ($householdDueIds !== []) {
                $payment->householdDues()->attach($householdDueIds);
                HouseholdDue::whereKey($householdDueIds)->update(['status' => 'bezahlt', 'bezahlt_am' => $validated['paid_at']]);
            }

            // Change protocol of the payer and of every member the payment covers (club decision 2026-11-08).
            PaymentProtocol::log($payment, MemberDue::query()->whereKey($dueIds)->orderBy('fiscal_year_id')->get(), HouseholdDue::query()->whereKey($householdDueIds)->orderBy('fiscal_year_id')->get());
        });

        return redirect()->route('members.edit', $member)
            ->with('status', __('members::admin.payments.created'));
    }

    /**
     * "Zahlung stornieren" from the member page (members right + admin mode via the route group). The payment
     * must belong to this member (payer) or pay a household due covering the member, else 404. See PaymentCanceller.
     */
    public function cancel(Request $request, Member $member, int $payment, PaymentCanceller $canceller): RedirectResponse
    {
        $record = Payment::query()->whereKey($payment)
            ->where(fn ($q) => $q->where('member_id', $member->id)
                ->orWhereHas('householdDues', fn ($h) => $h->whereHas('coveredMembers', fn ($m) => $m->whereKey($member->id))))
            ->firstOrFail();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $canceller->cancel($record, $validated['reason'] ?? null, $request->user()?->getAuthIdentifier());

        if ($result instanceof FiscalYear) {
            return redirect()->route('members.edit', $member)->withErrors(['payment' => $result->lockedMessage()]);
        }

        return redirect()->route('members.edit', $member)->with('status', $result === PaymentCanceller::ALREADY
            ? __('members::admin.payments.already_cancelled')
            : __('members::admin.payments.cancelled'));
    }
}
