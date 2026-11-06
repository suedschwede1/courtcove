@extends('members::layout')
@section('members-title', __('members::admin.payments.new_payment').' — '.$member->fullName())
@section('members-content')
<x-members::page-header :title="__('members::admin.payments.new_payment').' — '.$member->fullName()" />

<form method="POST" action="{{ route('members.payments.store', $member) }}" class="ui-form-shell">
    @csrf
    <x-ui.card>
        <div class="grid gap-4 md:grid-cols-3">
            <x-ui.field :label="__('members::admin.payments.amount')" for="pf-amount" :error="$errors->first('amount')">
                <x-ui.input type="number" step="0.01" min="0.01" name="amount" />
            </x-ui.field>
            {{-- The payment date becomes "Bezahlt am" of every due this payment covers. --}}
            <x-ui.field :label="__('members::admin.dues.bezahlt_am')" for="pf-paid-at" :error="$errors->first('paid_at')">
                <x-ui.input type="date" name="paid_at" :value="old('paid_at', now()->toDateString())" :max="now()->toDateString()" :min="\App\Modules\Members\Models\MemberDue::PAID_ON_MIN" required />
            </x-ui.field>
            <x-ui.field :label="__('members::admin.payments.note')" for="pf-note" :error="$errors->first('note')">
                <x-ui.input name="note" />
            </x-ui.field>
        </div>
    </x-ui.card>
    <x-ui.card padding="none" :title="__('members::admin.payments.covers')">
        <x-ui.table :caption="__('members::admin.payments.covers')">
            <x-slot:head>
                <tr>
                    <th scope="col" class="w-10"></th>
                    <th scope="col">{{ __('members::admin.fiscal_years.title') }}</th>
                    <th scope="col">{{ __('members::admin.fee_categories.title') }}</th>
                    <th scope="col">{{ __('members::admin.payments.amount') }}</th>
                </tr>
            </x-slot:head>
            @foreach($openDues as $due)
                <tr>
                    <td><input type="checkbox" name="member_due_ids[]" value="{{ $due->id }}" aria-label="{{ __('members::admin.common.select_row', ['name' => $due->fiscalYear->name]) }}"></td>
                    <td class="ds-cell--title" data-label="{{ __('members::admin.fiscal_years.title') }}">{{ $due->fiscalYear->name }}</td>
                    <td class="text-ink-2" data-label="{{ __('members::admin.fee_categories.title') }}">{{ $due->feeCategory->name }}</td>
                    <td class="tabular-nums" data-label="{{ __('members::admin.payments.amount') }}">{{ \App\Modules\Members\Support\MembersFormat::money($due->amount) }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.edit', $member)">{{ __('members::admin.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
    </div>
</form>
@endsection
