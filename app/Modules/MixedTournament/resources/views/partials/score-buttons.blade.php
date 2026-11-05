{{--
    The five fixed results of a match (old round.phtml score line: never free number input, always exactly
    four games). One POST form per result; the stored one is primary with aria-pressed. The forms carry
    data-scroll-save, so mixed::partials.scroll-restore brings the page back to this card after the redirect.
    Five equal columns that fit 375 px: the buttons fill their cell and drop the side padding.
    Needs: $match (MixedMatch), $current ("3:1" or null).
--}}
@php
    use App\Modules\MixedTournament\Domain\RoundService;
@endphp
<div class="grid grid-cols-5 gap-2" role="group" aria-label="{{ __('mixed::common.result') }}">
    @foreach (RoundService::RESULTS as $result)
        <form method="POST" action="{{ route('mixed.admin.score', ['round' => (int) $match->round_id]) }}" class="min-w-0" data-scroll-save>
            @csrf
            <input type="hidden" name="match_id" value="{{ (int) $match->id }}">
            <input type="hidden" name="result" value="{{ $result }}">
            <x-ui.button type="submit" :variant="$current === $result ? 'primary' : 'secondary'" aria-pressed="{{ $current === $result ? 'true' : 'false' }}" class="w-full min-w-0 px-0 tabular-nums">{{ $result }}</x-ui.button>
        </form>
    @endforeach
</div>
