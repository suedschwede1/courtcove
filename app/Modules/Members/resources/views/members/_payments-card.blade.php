{{--
    Card "Zahlungen" on the member page: payments of the member (payer) and payments of household dues covering
    the member. Needs: $member, $payments (with dues.fiscalYear, householdDues.fiscalYear/household, cancelledByUser).
    "Zahlung stornieren" opens one shared confirmation modal; cancelled payments stay listed as "Storniert".
--}}
@php
    use App\Modules\Members\Support\MembersFormat;
@endphp
<div x-data="{ cancelling: false, action: '', text: '' }">
<x-ui.card padding="none" :title="__('members::admin.payments.list_title')" class="mbr-dues-card">
    @if($errors->has('payment'))
        <x-ui.notice tone="problem">{{ $errors->first('payment') }}</x-ui.notice>
    @endif
    @if($payments->isEmpty())
        <p class="m-0 px-4 py-3 text-body-s text-ink-2">{{ __('members::admin.payments.none') }}</p>
    @else
        <x-ui.table :caption="__('members::admin.payments.list_title')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('members::admin.payments.paid_at') }}</th>
                    <th scope="col">{{ __('members::admin.dues.amount') }}</th>
                    <th scope="col">{{ __('members::admin.payments.covered') }}</th>
                    <th scope="col">{{ __('members::admin.payments.status') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('members::admin.payments.cancel') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach($payments as $payment)
                @php
                    $covered = $payment->dues->toBase()->map(fn ($due) => $due->fiscalYear->name)
                        ->concat($payment->householdDues->toBase()->map(fn ($due) => $due->fiscalYear->name.' · '.__('members::admin.payments.household_payment', ['household' => $due->household->label()])))
                        ->implode(', ');
                    $locked = $payment->dues->contains(fn ($due) => $due->fiscalYear->isClosed())
                        || $payment->householdDues->contains(fn ($due) => $due->fiscalYear->isClosed());
                    $amount = MembersFormat::money($payment->amount);
                @endphp
                <tr data-payment="{{ $payment->id }}">
                    <td class="ds-cell--title tabular-nums" data-label="{{ __('members::admin.payments.paid_at') }}">{{ $payment->paid_at->format('d.m.Y') }}</td>
                    <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.amount') }}">{{ $amount }}</td>
                    {{-- One wrapper per cell: in the phone stack layout a cell is a label/value row. --}}
                    <td data-label="{{ __('members::admin.payments.covered') }}">
                        <div class="flex flex-col gap-1">
                            <span>{{ $covered !== '' ? $covered : '–' }}</span>
                            @if($payment->note)<span class="mbr-due-info__extra">{{ $payment->note }}</span>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('members::admin.payments.status') }}">
                        @if($payment->isCancelled())
                            <div class="flex flex-col items-start gap-1">
                                <x-ui.badge tone="neutral">{{ __('members::admin.payments.status_cancelled') }}</x-ui.badge>
                                <span class="mbr-due-info__extra">{{ __('members::admin.payments.cancelled_by', ['date' => $payment->cancelled_at->format('d.m.Y'), 'user' => $payment->cancelledByUser->alias ?? '—']) }}</span>
                                @if($payment->cancel_reason)<span class="mbr-due-info__extra">{{ $payment->cancel_reason }}</span>@endif
                            </div>
                        @else
                            <x-ui.badge tone="good">{{ __('members::admin.payments.status_active') }}</x-ui.badge>
                        @endif
                    </td>
                    <td data-label="{{ __('members::admin.payments.cancel') }}">
                        @if($payment->isCancelled())
                            <span class="text-ink-3">–</span>
                        @else
                            @if($locked)
                                <span class="mbr-due-info__extra">{{ __('members::admin.payments.cancel_locked') }}</span>
                            @else
                                <x-ui.button variant="secondary" :block="false"
                                    data-cancel-action="{{ route('members.payments.cancel', ['member' => $member->id, 'payment' => $payment->id]) }}"
                                    data-cancel-text="{{ __('members::admin.payments.cancel_text', ['date' => $payment->paid_at->format('d.m.Y'), 'amount' => $amount]) }}"
                                    x-on:click="action = $el.dataset.cancelAction; text = $el.dataset.cancelText; cancelling = true">{{ __('members::admin.payments.cancel') }}</x-ui.button>
                                {{-- Plain form for the action URL (also keeps the target visible without JavaScript). --}}
                                <form method="POST" action="{{ route('members.payments.cancel', ['member' => $member->id, 'payment' => $payment->id]) }}" hidden>@csrf</form>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-ui.card>

{{-- The modal (with its danger button) only exists while there is something to cancel. --}}
@if($payments->contains(fn ($payment) => ! $payment->isCancelled()))
<x-ui.modal id="payment-cancel" show="cancelling" :label="__('members::admin.payments.cancel_title')">
    <x-slot:title>{{ __('members::admin.payments.cancel_title') }}</x-slot:title>
    <form method="POST" id="payment-cancel-form" x-bind:action="action" class="flex flex-col gap-4">
        @csrf
        <p class="m-0 text-ink" x-text="text"></p>
        <x-ui.field :label="__('members::admin.payments.cancel_reason')" for="payment-cancel-reason">
            <x-ui.textarea id="payment-cancel-reason" name="reason" rows="2" maxlength="500" />
        </x-ui.field>
    </form>
    <x-slot:footer>
        <x-ui.button variant="secondary" :block="false" x-on:click="cancelling = false">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" form="payment-cancel-form" variant="danger" :block="false" data-modal-initial>{{ __('members::admin.payments.cancel_confirm') }}</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
@endif
</div>
