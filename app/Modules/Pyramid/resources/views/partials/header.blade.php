{{--
    Title, status, meta line, ladder switcher and module tabs.
    Needs: $ladder, $ladders (all ladders), $active (ranking|challenges|rules|admin), $canAdmin.
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

    $tabs = [
        ['label' => __('pyramid::ui.tab_ranking'), 'href' => PyramidLinks::ladder((int) $ladder->id), 'active' => $active === 'ranking'],
        ['label' => __('pyramid::ui.tab_challenges'), 'href' => PyramidLinks::challenges(), 'active' => $active === 'challenges'],
        ['label' => __('pyramid::ui.tab_rules'), 'href' => PyramidLinks::rules(), 'active' => $active === 'rules'],
    ];
    if ($canAdmin) {
        $tabs[] = ['label' => __('pyramid::ui.tab_admin'), 'href' => PyramidLinks::adminLadder((int) $ladder->id), 'active' => $active === 'admin'];
    }
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

    @include('tournament-base::partials.module-tabs', ['items' => $tabs, 'label' => __('pyramid::ui.tabs_label')])
</div>
