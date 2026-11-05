{{-- Copy participants of another ladder to this waiting list. Needs: $ladder, $otherLadders. --}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;

    $sources = [];
    foreach ($otherLadders as $other) {
        $sources[(int) $other->id] = __('pyramid::admin.ladder.copy.option', ['title' => $other->title, 'season' => (int) $other->season]);
    }
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.copy.title')">
    <form method="POST" action="{{ PyramidLinks::adminLadderAction('copy-players', (int) $ladder->id) }}" class="flex flex-col gap-3">
        @csrf
        <p class="text-body-s text-ink-2">{{ __('pyramid::admin.ladder.copy.hint') }}</p>
        <x-ui.field for="pyramid-copy-source" :label="__('pyramid::admin.ladder.copy.source')">
            <x-ui.select name="sourceLadderId" id="pyramid-copy-source" :options="$sources" />
        </x-ui.field>
        <x-ui.button type="submit">{{ __('pyramid::admin.ladder.copy.submit') }}</x-ui.button>
    </form>
</x-ui.card>
