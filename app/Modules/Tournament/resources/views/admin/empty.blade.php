{{-- Championship admin without any championship (ZF2 answered with an error page). --}}
@extends('tournament-base::layouts.area')

@section('admin-title', __('tournament::admin.index.title'))

@section('admin-content')
<div class="flex flex-col gap-5">
    <div class="flex flex-col gap-1.5">
        <p class="text-label uppercase text-ink-3">{{ __('tournament::admin.index.eyebrow') }}</p>
        <h1 class="font-display text-display-l text-ink md:text-display-xl">{{ __('tournament::common.empty.title') }}</h1>
    </div>
    <x-ui.notice tone="neutral">{{ __('tournament::common.empty.text') }}</x-ui.notice>
</div>
@endsection
