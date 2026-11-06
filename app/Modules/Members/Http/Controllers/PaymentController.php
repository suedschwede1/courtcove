<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\MembersFormat;
use Illuminate\Contracts\View\View;
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

        return view('members::payments.create', ['member' => $member, 'openDues' => $openDues]);
    }

    public function store(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            'paid_at' => ['required', ...MemberDue::paidOnRules()],
            'note' => ['nullable', 'string', 'max:255'],
            'member_due_ids' => ['required', 'array', 'min:1'],
            'member_due_ids.*' => [
                'integer',
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
        ], MemberDue::paidOnMessages('paid_at'));

        $dueIds = array_map(intval(...), $validated['member_due_ids']);

        // The payment must cover the selected dues exactly - otherwise a partial
        // payment would mark them as paid and block the remainder forever.
        $expectedCents = (int) MemberDue::query()
            ->whereKey($dueIds)
            ->get()
            ->sum(fn (MemberDue $due): int => (int) round(((float) $due->amount) * 100));
        $paidCents = (int) round(((float) $validated['amount']) * 100);

        if ($paidCents !== $expectedCents) {
            throw ValidationException::withMessages([
                'amount' => __('members::admin.payments.amount_mismatch', [
                    'sum' => MembersFormat::money($expectedCents / 100),
                ]),
            ]);
        }

        DB::transaction(function () use ($validated, $member, $dueIds): void {
            // Lock order: the dues' year rows first, then the due rows. A year closed since
            // validation refuses the payment here.
            $yearIds = MemberDue::query()->whereKey($dueIds)->pluck('fiscal_year_id')->all();
            if (FiscalYear::lockForWrite($yearIds) !== null) {
                throw ValidationException::withMessages([
                    'member_due_ids' => __('members::admin.payments.due_in_closed_year'),
                ]);
            }

            // Re-check inside the transaction (with the due rows locked) so two
            // concurrent submissions cannot allocate the same due twice.
            $alreadyPaid = MemberDue::query()
                ->whereKey($dueIds)
                ->paid()
                ->lockForUpdate()
                ->exists();

            if ($alreadyPaid) {
                throw ValidationException::withMessages([
                    'member_due_ids' => __('members::admin.payments.due_already_paid'),
                ]);
            }

            $payment = Payment::create([
                'member_id' => $member->id,
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'],
                'note' => $validated['note'] ?? null,
            ]);

            $payment->dues()->attach($dueIds);

            // A payment always covers its dues in full (checked above), so its date is
            // the date each of these dues was settled.
            MemberDue::whereKey($dueIds)->update(['status' => 'bezahlt', 'bezahlt_am' => $validated['paid_at']]);
        });

        return redirect()->route('members.edit', $member)
            ->with('status', __('members::admin.payments.created'));
    }
}
