{{--
    Module tabs of an event (tabs on wide screens, pill chips on phones, see x-ui.tabs).
    Needs: $event, $active; optional $adminView (default false), $canAdmin (default false).
    A completed round carries a check icon (never a check character) and a screen-reader note.
--}}
@php
    use App\Modules\MixedTournament\Support\MixedTabs;
    use Illuminate\Support\Facades\Blade;
    use Illuminate\Support\HtmlString;

    $check = Blade::render('<x-ui.icon name="check" :size="16" />');
    $items = array_map(static fn (array $tab): array => [
        'label' => $tab['done']
            ? new HtmlString(e($tab['label']).$check.'<span class="sr-only">'.e(__('mixed::common.round_done')).'</span>')
            : $tab['label'],
        'url' => $tab['href'],
        'active' => $tab['active'],
    ], MixedTabs::items($event, $active, $adminView ?? false, $canAdmin ?? false));
@endphp
{{-- Phones: the tab row scrolls; bring the active tab into view. --}}
<x-ui.tabs class="relative" x-data x-init="const a = $el.querySelector('[aria-current]'); if (a) { $el.scrollLeft = a.offsetLeft - 16 }" :label="__('mixed::common.tabs_label')" :items="$items" />
