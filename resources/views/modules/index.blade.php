{{-- Module overview: one card per module the viewer may see, straight from the ModuleRegistry. --}}
@extends('layouts.modules')

@section('title', __('modules.overview'))
@section('admin-title', __('modules.area'))

@section('admin-content')
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('modules.overview')" :eyebrow="__('modules.area')">
        <p>{{ __('modules.intro') }}</p>
    </x-admin.page-header>

    @if($modules === [])
        <x-ui.notice tone="neutral">{{ __('modules.empty') }}</x-ui.notice>
    @else
        <div class="ds-module-grid">
            @foreach($modules as $module)
                <x-ui.card as="article" class="ds-admin-kpi" :title="$module->label()" id="module-{{ $module->key }}">
                    <div class="ds-admin-stack">
                        <x-ui.icon :name="$module->icon" :size="28" />
                        <p>{{ $module->summary() }}</p>
                        <x-ui.button :href="$module->entryUrl()" variant="secondary" :block="false" :aria-label="__('modules.open_module', ['name' => $module->label()])">{{ __('modules.open') }}</x-ui.button>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
@endsection
