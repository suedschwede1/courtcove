{{--
    Card "Anschlussmitglied · optional" on the member page, visible on both sides: the Anschlussmitglied sees
    "Verbunden mit: <Vollzahler>", the Vollzahler sees "Anschlussmitglied: <name>". Warnings describe invalid states
    (nothing is changed automatically); a link that ended because the Vollzahler departed is shown as a notice.
    Any member without a link can be connected to any current unconnected member (no category check, club decision
    2026-11-08); a connected pair can swap roles after a confirmation.
    Needs $member, $connectionWarnings, $connectionEndedNotice, $connectionCandidates.
--}}
@php
    $isCurrent = fn ($m) => in_array($m->status, ['aktiv', 'ruhend'], true) && $m->anonymized_at === null;
    $swapPartner = $member->connectedTo ?? ($member->anschlussMembers->count() === 1 ? $member->anschlussMembers->first() : null);
    $swapAnschluss = $member->connectedTo ? $member : $swapPartner;
    $swapVollzahler = $member->connectedTo ?? $member;
    // Offer the swap only where the service would do it: exactly two current persons, the Vollzahler with one partner.
    if ($swapPartner && (! $isCurrent($member) || ! $isCurrent($swapPartner) || $swapVollzahler->anschlussMembers()->count() !== 1)) {
        $swapPartner = null;
    }
@endphp
<x-ui.card :title="__('members::admin.connection.card_title')" data-testid="member-connection-card">
    <div class="mbr-account-card" x-data="{ swapping: false }">
        <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.connection.help') }}</p>
        @if($connectionEndedNotice)
            <x-ui.notice tone="neutral" data-testid="connection-ended-notice">{{ $connectionEndedNotice }}</x-ui.notice>
        @endif
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
        @endif

        @foreach($member->anschlussMembers as $partner)
            <p class="m-0 text-ink">
                <span class="text-ink-2">{{ __('members::admin.connection.anschluss_member') }}:</span>
                <a href="{{ route('members.edit', $partner) }}" class="mbr-row-link">{{ $partner->fullName() }}</a>
            </p>
        @endforeach

        @if($member->connectedTo || $swapPartner)
            <div class="flex flex-wrap gap-3">
                @if($swapPartner)
                    <x-ui.button variant="secondary" :block="false" x-on:click="swapping = true">{{ __('members::admin.connection.swap') }}</x-ui.button>
                @endif
                @if($member->connectedTo)
                    <form method="POST" action="{{ route('members.connection.destroy', $member->id) }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.connection.disconnect') }}</x-ui.button>
                    </form>
                @endif
            </div>
        @endif

        @if(! $member->connectedTo && $member->anschlussMembers->isEmpty())
            <p class="m-0 text-body-s text-ink-2">{{ __('members::admin.connection.none') }}</p>
            @if($connectionCandidates->isNotEmpty() && in_array($member->status, ['aktiv', 'ruhend'], true) && $member->anonymized_at === null)
                <form method="POST" action="{{ route('members.connection.store', $member->id) }}" class="flex flex-col items-start gap-3">
                    @csrf
                    <x-ui.field :label="__('members::admin.connection.choose')" for="vollzahler_id" class="w-full">
                        <x-ui.select :options="$connectionCandidates->mapWithKeys(fn ($m) => [$m->id => $m->lastname.', '.$m->firstname.' ('.__('members::admin.members.number_prefix', ['number' => $m->member_number]).')'.($m->feeCategory ? ' · '.$m->feeCategory->name : '')])->all()" placeholder="–" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.connection.connect') }}</x-ui.button>
                </form>
            @endif
        @endif

        @if($swapPartner)
            <x-ui.modal id="connection-swap" show="swapping" :label="__('members::admin.connection.swap_title')">
                <x-slot:title>{{ __('members::admin.connection.swap_title') }}</x-slot:title>
                <form method="POST" id="connection-swap-form" action="{{ route('members.connection.swap', $member->id) }}">
                    @csrf
                    <p class="m-0 text-ink">{{ __('members::admin.connection.swap_text', ['anschluss' => $swapAnschluss->fullName(), 'vollzahler' => $swapVollzahler->fullName()]) }}</p>
                </form>
                <x-slot:footer>
                    <x-ui.button variant="secondary" :block="false" x-on:click="swapping = false">{{ __('members::admin.common.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" form="connection-swap-form" :block="false" data-modal-initial>{{ __('members::admin.connection.swap_confirm') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endif
    </div>
</x-ui.card>
