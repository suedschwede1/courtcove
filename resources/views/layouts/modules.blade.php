{{--
    Frame of every page of the module area (the module overview and all module pages: Pyramid, championship,
    draws, Mixed and every module that registers itself in the ModuleRegistry): the area shell (layouts.admin)
    with the module sidebar (ModuleAreaNavigation: on a module's pages only that module's entries, below them its
    administration entries; elsewhere the groups of all modules). The phone bar shows the module name unless the
    page sets admin-title. Pages fill admin-content (or a module layout does); print views do not use this frame.
--}}
@php
    $moduleArea = app(\App\Support\Modules\ModuleAreaNavigation::class);
@endphp
@extends('layouts.admin')

@section('layout-title')
{!! trim($__env->yieldContent('title')) ?: trim($__env->yieldContent('admin-title')) !!} - {{ __('modules.area') }}
@endsection

@section('admin-eyebrow', __('modules.area'))

@section('admin-title', $moduleArea->moduleLabel(request()))

@section('admin-sidebar')
    <x-layout.admin-sidebar :area="$moduleArea" />
@endsection
