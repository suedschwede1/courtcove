{{-- Closing the season. Needs: $ladder, $closeBlock (null or the German/English reason why closing is refused). --}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.season.title')" class="border-danger">
    <form method="POST" action="{{ PyramidLinks::adminLadderAction('close', (int) $ladder->id) }}" class="flex flex-col gap-3">
        @csrf
        @if ($closeBlock !== null)
            <x-ui.notice tone="attention">{{ $closeBlock }}</x-ui.notice>
        @endif
        <x-ui.button type="submit" variant="danger" :disabled="$closeBlock !== null">{{ __('pyramid::admin.ladder.season.close') }}</x-ui.button>
    </form>
</x-ui.card>
