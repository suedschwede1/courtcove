{{--
    Frame of a tournament module player page: public layout (header, feedback dialog), page gutter,
    page title with eyebrow, module tabs, banners, the module flash list and the content.
    Sections: title (also the browser title), eyebrow, intro (optional), tabs (optional, usually
    tournament-base::partials.module-tabs), module-banner (optional, e.g. tournament-base::partials.test-banner),
    module-content.
--}}
@extends('layouts.app')

@section('content')
@php
    // @section('title', $value) stores the escaped value; the page header escapes again, so decode first.
    $moduleText = static fn (string $name): string => html_entity_decode(trim($__env->yieldContent($name)), ENT_QUOTES | ENT_HTML5);
@endphp
<div class="mx-auto flex w-full max-w-page flex-col gap-4 px-4 pb-10 pt-5 md:px-6">
    <x-ui.page-header :title="$moduleText('title')" :eyebrow="$moduleText('eyebrow')" :intro="$moduleText('intro')" />
    @yield('tabs')
    @yield('module-banner')
    @include('tournament-base::partials.info-notice')
    @include('tournament-base::partials.flash')
    @yield('module-content')
</div>
@endsection
