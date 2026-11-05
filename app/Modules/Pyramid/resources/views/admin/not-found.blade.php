@extends('pyramid::admin.layout')

@section('title', __('pyramid::ui.not_found.title').' · '.__('pyramid::admin.index.title'))
@section('admin-title', __('pyramid::ui.not_found.title'))

{{--
    Friendly 404 of the Pyramid administration (rendered by PyramidNotFound, status 404). Needs: $message.
--}}
@section('pyramid-admin-content')
<x-ui.card>
    <div class="flex flex-col items-start gap-3">
        <p class="text-label uppercase text-ink-3">{{ __('pyramid::admin.index.eyebrow') }}</p>
        <h1 class="font-display text-display-l text-ink">{{ __('pyramid::ui.not_found.title') }}</h1>
        <p class="font-semibold text-ink">{{ $message }}</p>
        <x-ui.button :block="false" :href="route('pyramide.admin.index')">{{ __('pyramid::ui.not_found.to_admin') }}</x-ui.button>
    </div>
</x-ui.card>
@endsection
