{{--
    Preview of "Durch Haushaltsbeitrag ersetzen" for $previewYear ($preview: ReplacementPreview). Nothing changes
    until the confirmation form is sent; paid or partly paid own dues are listed as untouched.
--}}
@php
    use App\Modules\Members\Support\MembersFormat;
@endphp
<x-ui.card :title="__('members::admin.households.replace.preview_title', ['year' => $previewYear->name])" data-testid="household-replace-preview">
    <div class="flex flex-col gap-3">
        @if($preview->amount !== null && $preview->possible())
            <p class="m-0 font-semibold text-ink">
                {{ $preview->existingDue
                    ? __('members::admin.households.replace.household_due_existing', ['amount' => MembersFormat::money($preview->amount)])
                    : __('members::admin.households.replace.household_due_new', ['amount' => MembersFormat::money($preview->amount)]) }}
            </p>
        @endif

        @if($preview->possible())
            <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.households.replace.will_delete') }}</p>
            <ul class="m-0 flex flex-col gap-1">
                @foreach($preview->replaceable as $due)
                    <li>{{ $due->member->fullName() }} · {{ MembersFormat::money($due->amount) }}</li>
                @endforeach
            </ul>
        @endif

        @if($preview->untouched->isNotEmpty())
            <x-ui.notice tone="neutral">
                <p class="m-0">{{ __('members::admin.households.replace.untouched_hint') }}</p>
                <ul class="m-0 flex flex-col gap-1">
                    @foreach($preview->untouched as $due)
                        <li>{{ $due->member->fullName() }} · {{ MembersFormat::money($due->amount) }}</li>
                    @endforeach
                </ul>
            </x-ui.notice>
        @endif

        @if($preview->possible())
            <form method="POST" action="{{ route('members.households.replace-dues', $household->id) }}" class="flex flex-col items-start gap-3">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $previewYear->id }}">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="confirm" value="1" required>
                    <span>{{ __('members::admin.households.replace.confirm_label') }}</span>
                </label>
                @if($errors->has('confirm') || $errors->has('fiscal_year_id'))
                    <p class="ds-field__error">{{ $errors->first('confirm') ?: $errors->first('fiscal_year_id') }}</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" :block="false" :href="route('members.households.show', $household->id)">{{ __('members::admin.common.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" :block="false">{{ __('members::admin.households.replace.confirm') }}</x-ui.button>
                </div>
            </form>
        @else
            <x-ui.notice tone="attention">{{ $preview->blockReason }}</x-ui.notice>
            <div>
                <x-ui.button variant="secondary" :block="false" :href="route('members.households.show', $household->id)">{{ __('members::admin.households.back_to_household') }}</x-ui.button>
            </div>
        @endif
    </div>
</x-ui.card>
