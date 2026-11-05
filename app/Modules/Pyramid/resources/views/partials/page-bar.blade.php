{{--
    Bar of a detail page (challenge, player, confirm). The mockup shows no ladder header, chips or tabs above these cards.
    Phones: the phone top bar (back arrow and page title, as in the phone mockups) replaces the site header.
    From 48rem: the desktop head with back chevron, ladder context and the page title.
    Needs: $ladder, $title.
--}}
@php
    use App\Modules\Pyramid\Support\PyramidLinks;

    $backUrl = PyramidLinks::ladder((int) $ladder->id);
@endphp
@section('phone-bar')
    <x-layout.phone-bar :title="$title" :back="$backUrl" :back-label="__('pyramid::ui.detail.back')" heading />
@endsection
<div class="flex items-center gap-2 max-md:hidden">
    <x-ui.icon-button icon="chevron-left" variant="text" :href="$backUrl" :label="__('pyramid::ui.detail.back')" />
    <div class="flex min-w-0 flex-col">
        <p class="text-label uppercase text-ink-3">{{ $ladder->title }} · {{ __('pyramid::ui.meta_season', ['season' => (int) $ladder->season]) }}</p>
        <h1 class="font-display text-display-l text-ink">{{ $title }}</h1>
    </div>
</div>
