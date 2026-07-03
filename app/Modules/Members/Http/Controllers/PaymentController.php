<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'member_due_ids' => ['required', 'array', 'min:1'],
            'member_due_ids.*' => ['integer', 'exists:member_dues,id'],
        ]);

        $payment = Payment::create([
            'member_id' => $member->id,
            'amount' => $validated['amount'],
            'paid_at' => $validated['paid_at'],
            'note' => $validated['note'] ?? null,
        ]);

        $payment->dues()->attach($validated['member_due_ids']);

        return redirect()->route('admin.members.edit', $member)
            ->with('status', __('members::admin.payments.created'));
    }
}
