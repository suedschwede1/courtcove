{{--
    Bar of a detail page (challenge, player, confirm): back chevron, ladder context and the page title.
    The mockup shows no ladder header, chips or tabs above these cards.
    Needs: $ladder, $title.
--}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;
@endphp
<div class="flex items-center gap-2">
    <x-ui.icon-button icon="chevron-left" variant="text" :href="PyramidLinks::ladder((int) $ladder->id)" :label="__('pyramid::ui.detail.back')" />
    <div class="flex min-w-0 flex-col">
        <p class="text-label uppercase text-ink-3">{{ $ladder->title }} · {{ __('pyramid::ui.meta_season', ['season' => (int) $ladder->season]) }}</p>
        <h1 class="font-display text-display-l text-ink">{{ $title }}</h1>
    </div>
</div>
