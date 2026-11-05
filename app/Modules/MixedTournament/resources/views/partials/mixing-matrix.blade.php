{{--
    Durchmischung matrix: shared partnerships per participant pair, darker = more often.
    Levels (tokens only, gap 12): 0 ground/ink-3, 1 court-soft/court-ink, 2 court-soft/court-ink bold with an
    inset court outline, 3+ court/on-court; the own cell is chrome.
    A grid of fixed cells (role table) instead of x-ui.table: the cells are 36 px squares, not table rows.
    Needs: $matrix (MixingMatrix::build()).
--}}
@php
    $levelClass = [
        0 => 'bg-ground text-ink-3 font-medium',
        1 => 'bg-court-soft text-court-ink font-medium',
        2 => 'bg-court-soft text-court-ink font-bold ring-2 ring-inset ring-court',
        3 => 'bg-court text-on-court font-bold',
    ];
    $cell = 'flex h-8 w-9 shrink-0 items-center justify-center rounded-sm text-body-s tabular-nums';
@endphp
{{--
    relative: the sr-only texts are absolutely positioned and must not widen the page beyond the scroll box.
    tabindex + label: keyboard users can focus the box and scroll it with the arrow keys.
--}}
<div class="relative overflow-x-auto" role="region" tabindex="0" aria-label="{{ __('mixed::common.matrix_label') }}">
    <div role="table" aria-label="{{ __('mixed::common.matrix_label') }}" class="flex w-max flex-col gap-[3px] text-body-s">
        <div role="row" class="flex gap-[3px]">
            <span role="columnheader" class="w-28 shrink-0"><span class="sr-only">{{ __('mixed::common.col_name') }}</span></span>
            @foreach ($matrix['rows'] as $index => $row)
                <span role="columnheader" aria-label="{{ $row['label'] }}" title="{{ $row['label'] }}" class="w-9 shrink-0 truncate text-center font-bold text-ink-2"><span aria-hidden="true">{{ $matrix['heads'][$index] }}</span></span>
            @endforeach
        </div>
        @foreach ($matrix['rows'] as $row)
            <div role="row" class="flex gap-[3px]">
                <span role="rowheader" class="flex w-28 shrink-0 items-center gap-1 font-semibold text-ink">
                    <span class="truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <span class="shrink-0 font-normal text-ink-3" title="{{ __('mixed::common.matches_played') }}">({{ $matrix['matches_played'][$row['id']] ?? 0 }})</span>
                </span>
                @foreach ($row['cells'] as $c)
                    @if ($c['count'] === null)
                        <span role="cell" class="{{ $cell }} bg-chrome"><span class="sr-only">–</span></span>
                    @else
                        <span role="cell" class="{{ $cell }} {{ $levelClass[$c['level']] }}" title="{{ __('mixed::common.matrix_cell', ['a' => $row['label'], 'b' => $c['partner'], 'count' => $c['count']]) }}">{{ $c['count'] }}</span>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
</div>
