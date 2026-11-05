{{-- Closing the season. Needs: $ladder. --}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.season.title')" class="border-danger">
    <form method="POST" action="{{ PyramidLinks::adminLadderAction('close', (int) $ladder->id) }}">
        @csrf
        <x-ui.button type="submit" variant="danger">{{ __('pyramid::admin.ladder.season.close') }}</x-ui.button>
    </form>
</x-ui.card>
