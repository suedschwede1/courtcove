{{--
    Card "Haushalt · optional" on the member page (same pattern as "Benutzerkonto · optional"). Needs: $member,
    $household (or null), $households (current households for the select), $householdDue (household due covering the
    member in $householdYear, or null). Writes go to the household routes with from=member, so they come back here.
--}}
@php
    use App\Modules\Members\Models\MemberHousehold;
    use App\Modules\Members\Support\StatusTone;

    $dueLabels = [
        'bezahlt' => __('members::admin.dues.status_bezahlt'),
        'ausstehend' => __('members::admin.dues.status_ausstehend'),
        'ueberfaellig' => __('members::admin.dues.status_ueberfaellig'),
    ];
    $canJoin = in_array($member->status, MemberHousehold::BILLABLE_STATUSES, true) && $member->anonymized_at === null;
    $addUrl = route('members.households.members.add', ['household' => '__HOUSEHOLD__']);
@endphp
<x-ui.card :title="__('members::admin.households.card_title')">
    <div class="mbr-account-card">
        @if(session('household_hint'))
            <x-ui.notice tone="neutral">{{ session('household_hint') }}</x-ui.notice>
        @endif
        @if($errors->has('household'))
            <p class="ds-field__error">{{ $errors->first('household') }}</p>
        @endif

        @if($household)
            <p class="m-0 font-semibold text-ink">
                <a href="{{ route('members.households.show', $household->id) }}" class="mbr-row-link">{{ $household->label() }}</a>
                <span class="font-normal text-ink-2">({{ __('members::admin.households.members_count', ['count' => $household->members->count()]) }})</span>
            </p>
        @else
            <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.households.none_assigned') }}</p>
        @endif

        @if($householdDue)
            @php $status = $householdDue->payments_exists ? 'bezahlt' : $householdDue->displayStatus(); @endphp
            <p class="m-0 text-body-s text-ink-2">
                {{ __('members::admin.households.due_via_household', ['year' => $householdYear?->name]) }}
                @if($householdDue->household_id !== $household?->id)
                    ({{ $householdDue->household->label() }})
                @endif
                <x-ui.badge :tone="StatusTone::due($status)">{{ $dueLabels[$status] ?? $status }}</x-ui.badge>
            </p>
        @endif

        @if($household)
            <form method="POST" action="{{ route('members.households.members.remove', ['household' => $household->id, 'member' => $member->id]) }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="from" value="member">
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.households.leave') }}</x-ui.button>
            </form>
        @elseif($canJoin)
            @if($households->isNotEmpty())
                <form method="POST" x-data="{ household: '' }" x-bind:action="household ? @js($addUrl).replace('__HOUSEHOLD__', household) : ''" class="flex flex-col items-start gap-3" x-on:submit="if (!household) $event.preventDefault()">
                    @csrf
                    <input type="hidden" name="member_id" value="{{ $member->id }}">
                    <input type="hidden" name="from" value="member">
                    <x-ui.field :label="__('members::admin.households.choose')" for="member-household" class="w-full">
                        <select id="member-household" class="ds-select" x-model="household">
                            <option value="">–</option>
                            @foreach($households as $option)
                                <option value="{{ $option->id }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.households.assign') }}</x-ui.button>
                </form>
            @endif
            <form method="POST" action="{{ route('members.household.store-from-member', $member->id) }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" :block="false" icon="plus">{{ __('members::admin.households.create_new') }}</x-ui.button>
            </form>
        @endif
    </div>
</x-ui.card>
