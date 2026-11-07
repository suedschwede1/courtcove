{{--
    Card "Anschlussmitglied · optional" on the member page, visible on both sides: the Anschlussmitglied sees
    "Verbunden mit: <Vollzahler>", the Vollzahler sees "Anschlussmitglied: <name>". Warnings describe invalid states
    (nothing is changed automatically). Needs $member, $connectionWarnings, $connectionCandidates.
--}}
<x-ui.card :title="__('members::admin.connection.card_title')" data-testid="member-connection-card">
    <div class="mbr-account-card">
        <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.connection.help') }}</p>
        @foreach($connectionWarnings as $warning)
            <x-ui.notice tone="attention">{{ $warning }}</x-ui.notice>
        @endforeach
        @if($errors->has('connection') || $errors->has('vollzahler_id'))
            <p class="ds-field__error">{{ $errors->first('connection') ?: $errors->first('vollzahler_id') }}</p>
        @endif

        @if($member->connectedTo)
            <p class="m-0 text-ink">
                <span class="text-ink-2">{{ __('members::admin.connection.connected_to') }}:</span>
                <a href="{{ route('members.edit', $member->connectedTo) }}" class="mbr-row-link">{{ $member->connectedTo->fullName() }}</a>
            </p>
            <form method="POST" action="{{ route('members.connection.destroy', $member->id) }}">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.connection.disconnect') }}</x-ui.button>
            </form>
        @endif

        @foreach($member->anschlussMembers as $partner)
            <p class="m-0 text-ink">
                <span class="text-ink-2">{{ __('members::admin.connection.anschluss_member') }}:</span>
                <a href="{{ route('members.edit', $partner) }}" class="mbr-row-link">{{ $partner->fullName() }}</a>
            </p>
        @endforeach

        @if(! $member->connectedTo && $member->anschlussMembers->isEmpty())
            <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.connection.none') }}</p>
            @if($member->feeCategory?->isAnschluss() && $connectionCandidates->isNotEmpty())
                <form method="POST" action="{{ route('members.connection.store', $member->id) }}" class="flex flex-col items-start gap-3">
                    @csrf
                    <x-ui.field :label="__('members::admin.connection.choose')" for="vollzahler_id" class="w-full">
                        <x-ui.select :options="$connectionCandidates->mapWithKeys(fn ($m) => [$m->id => $m->lastname.', '.$m->firstname.' ('.__('members::admin.members.number_prefix', ['number' => $m->member_number]).')'])->all()" placeholder="–" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.connection.connect') }}</x-ui.button>
                </form>
            @endif
        @endif
    </div>
</x-ui.card>
