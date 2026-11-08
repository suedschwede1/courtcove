{{--
    Accounts of departed members: admin/assistant accounts that stay active (warning) and re-entered members whose
    account was blocked on departure (one-click reactivation). Needs $overview (MemberDepartureAccounts::overview()).
    $withActions shows the reactivation buttons (dashboard); the member list only points to the member.
--}}
@php $withActions = $withActions ?? false; @endphp
@if($overview['protected']->isNotEmpty())
    <x-ui.notice tone="attention" data-testid="departure-protected">
        <ul class="mbr-changes">
            @foreach($overview['protected'] as $protectedMember)
                <li class="mbr-change">
                    <p class="mbr-change__what"><a href="{{ route('members.edit', $protectedMember) }}" class="mbr-change__name">{{ $protectedMember->lastname }}, {{ $protectedMember->firstname }}</a>: {{ __('members::admin.departure.protected_short') }}</p>
                </li>
            @endforeach
        </ul>
    </x-ui.notice>
@endif
@if($overview['reactivatable']->isNotEmpty())
    <x-ui.notice tone="neutral" data-testid="departure-reentered">
        <ul class="mbr-changes">
            @foreach($overview['reactivatable'] as $reentered)
                <li class="mbr-change">
                    <p class="mbr-change__what"><a href="{{ route('members.edit', $reentered) }}" class="mbr-change__name">{{ $reentered->lastname }}, {{ $reentered->firstname }}</a> {{ __('members::admin.departure.reentered_short') }}</p>
                    @if($withActions)
                        <form method="POST" action="{{ route('members.account.reactivate', $reentered->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" :block="false">{{ __('members::admin.departure.reactivate') }}</x-ui.button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.notice>
@endif
