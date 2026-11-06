{{--
    Frame of a tournament module player page inside the tournament area shell (tournament-base::layouts.area: the
    sidebar is the module navigation, there are no module tabs): page title with eyebrow, banners, the module flash
    list and the content (success, error and info flashes come from the shell). Sections: title (also the browser title), eyebrow, intro (optional), module-banner
    (optional, e.g. tournament-base::partials.test-banner), module-content.
--}}
@extends('tournament-base::layouts.area')

@section('admin-content')
@php
    // @section('title', $value) stores the escaped value; the page header escapes again, so decode first.
    $moduleText = static fn (string $name): string => html_entity_decode(trim($__env->yieldContent($name)), ENT_QUOTES | ENT_HTML5);
@endphp
<div class="mx-auto flex w-full min-w-0 max-w-page flex-col gap-4">
    <x-ui.page-header :title="$moduleText('title')" :eyebrow="$moduleText('eyebrow')" :intro="$moduleText('intro')" />
    @yield('module-banner')
    @include('tournament-base::partials.flash')
    @yield('module-content')
</div>
@endsection
