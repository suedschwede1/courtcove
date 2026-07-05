<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController
{
    public function create(Member $member): View
    {
        $openDues = $member->dues()
            ->whereDoesntHave('payments')
            ->with(['fiscalYear', 'feeCategory'])
            ->get();

        return view('members::payments.create', ['member' => $member, 'openDues' => $openDues]);
    }

    public function store(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'member_due_ids' => ['required', 'array', 'min:1'],
            'member_due_ids.*' => [
                'integer',
                'exists:member_dues,id,member_id,'.$member->id,
                function (string $attribute, mixed $value, callable $fail): void {
                    $isAlreadyPaid = MemberDue::query()
                        ->whereKey($value)
                        ->whereHas('payments')
                        ->exists();

                    if ($isAlreadyPaid) {
                        $fail(__('members::admin.payments.due_already_paid'));
                    }
                },
            ],
        ]);

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
                    'sum' => number_format($expectedCents / 100, 2, ',', '.'),
                ]),
            ]);
        }

        DB::transaction(function () use ($validated, $member, $dueIds): void {
            // Re-check inside the transaction (with the due rows locked) so two
            // concurrent submissions cannot allocate the same due twice.
            $alreadyPaid = MemberDue::query()
                ->whereKey($dueIds)
                ->whereHas('payments')
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

            MemberDue::whereKey($dueIds)->update(['status' => 'bezahlt']);
        });

        return redirect()->route('members.edit', $member)
            ->with('status', __('members::admin.payments.created'));
    }
}
