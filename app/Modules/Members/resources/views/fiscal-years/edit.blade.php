@extends('members::layout')
@section('members-title', $fiscalYear->name)
@section('members-content')
{{--
    Edit one Geschäftsjahr, and close it (or reopen it, administrators only). A closed year is read-only:
    the form is shown disabled. Closing with open dues needs the confirmation checkbox (no JavaScript needed).
--}}
<x-members::page-header :title="$fiscalYear->name" />
@if($errors->has('fiscal_year'))
    <x-ui.notice tone="problem">{{ $errors->first('fiscal_year') }}</x-ui.notice>
@endif
<form method="POST" action="{{ route('members.fiscal-years.update', $fiscalYear) }}" class="ui-form-shell">
    @csrf
    @method('PUT')
    <fieldset @disabled($fiscalYear->isClosed())>
        @include('members::fiscal-years._form', ['fiscalYear' => $fiscalYear])
    </fieldset>
    <div class="flex gap-2 justify-end">
        <x-ui.button variant="secondary" :block="false" :href="route('members.fiscal-years.index')">{{ __('members::admin.common.cancel') }}</x-ui.button>
        @unless($fiscalYear->isClosed())
            <x-ui.button type="submit" :block="false">{{ __('members::admin.common.save') }}</x-ui.button>
        @endunless
    </div>
</form>

<x-ui.card :title="__('members::admin.fiscal_years.close_card')">
    <div class="flex flex-col items-start gap-3">
        @if($fiscalYear->isClosed())
            <x-ui.badge tone="neutral">{{ __('members::admin.fiscal_years.closed') }}</x-ui.badge>
            <p class="m-0">{{ __('members::admin.fiscal_years.closed_on', ['date' => $fiscalYear->abgeschlossen_am->format('d.m.Y')]) }}@if($closedBy) · {{ $closedBy->alias }}@endif</p>
            <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.fiscal_years.closed_help') }}</p>
            @if($canReopen)
                <form method="POST" action="{{ route('members.fiscal-years.reopen', $fiscalYear) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.fiscal_years.reopen_button') }}</x-ui.button>
                </form>
            @else
                <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.fiscal_years.reopen_admin_only') }}</p>
            @endif
        @else
            <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.fiscal_years.close_help') }}</p>
            <form method="POST" action="{{ route('members.fiscal-years.close', $fiscalYear) }}" class="flex flex-col items-start gap-3">
                @csrf
                @if($openDuesCount > 0)
                    <x-ui.notice tone="attention">{{ __('members::admin.fiscal_years.close_open_dues_warning', ['count' => $openDuesCount]) }}</x-ui.notice>
                    <label class="mbr-check">
                        <input type="checkbox" name="confirm_open_dues" value="1">
                        <span>{{ __('members::admin.fiscal_years.close_confirm_open') }}</span>
                    </label>
                    @if($errors->has('confirm_open_dues'))
                        <p class="ds-field__error">{{ $errors->first('confirm_open_dues') }}</p>
                    @endif
                @endif
                <x-ui.button type="submit" variant="danger" :block="false">{{ __('members::admin.fiscal_years.close_button') }}</x-ui.button>
            </form>
        @endif
    </div>
</x-ui.card>
@endsection
