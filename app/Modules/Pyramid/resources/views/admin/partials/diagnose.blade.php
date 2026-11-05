{{-- Ranking invariant violated: the findings and the button that repairs them. Needs: $ladder, $problems (list of strings). --}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<x-ui.card :title="__('pyramid::admin.ladder.problems.title')" class="border-l-4 border-l-danger">
    <div class="flex flex-col gap-3">
        <ul class="flex list-disc flex-col gap-1 pl-5 text-ink-2">
            @foreach ($problems as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
        <form method="POST" action="{{ PyramidLinks::adminLadderAction('normalize', (int) $ladder->id) }}">
            @csrf
            <x-ui.button type="submit" :block="false">{{ __('pyramid::admin.ladder.problems.normalize') }}</x-ui.button>
        </form>
    </div>
</x-ui.card>
