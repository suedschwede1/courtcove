{{--
    Title, status, meta line and ladder switcher. The module navigation (Rangliste, Forderungen, Regeln, Verwaltung)
    is the tournament sidebar (PyramidNavigation).
    Needs: $ladder, $ladders (all ladders).
    Optional: $playerCount and $openCount for the full meta line (season only without them).
--}}
@php
    use App\Modules\Pyramid\Support\LadderChips;
    use App\Modules\Pyramid\Support\PyramidLinks;

    $statusTone = ['setup' => 'neutral', 'running' => 'good', 'closed' => 'neutral'][$ladder->status] ?? 'neutral';
    $statusLabel = in_array($ladder->status, ['setup', 'running', 'closed'], true)
        ? __('pyramid::ui.status.'.$ladder->status)
        : $ladder->status;
    $meta = isset($playerCount, $openCount)
        ? __('pyramid::ui.meta', ['season' => (int) $ladder->season, 'count' => $playerCount, 'open' => $openCount])
        : __('pyramid::ui.meta_season', ['season' => (int) $ladder->season]);
@endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-col gap-1.5">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ $ladder->title }}</h1>
                <x-ui.badge :tone="$statusTone">{{ $statusLabel }}</x-ui.badge>
            </div>
            <p class="text-ink-2">{{ $meta }}</p>
        </div>

        @if (count($ladders) > 1)
            <div role="group" aria-label="{{ __('pyramid::ui.ladders_label') }}" class="flex flex-wrap gap-2">
                @foreach (LadderChips::for($ladders) as $chip)
                    <x-ui.chip :href="PyramidLinks::ladder((int) $chip['ladder']->id)" :active="(int) $chip['ladder']->id === (int) $ladder->id" :title="$chip['ladder']->title">{{ $chip['label'] }}</x-ui.chip>
                @endforeach
            </div>
        @endif
    </div>
</div>
