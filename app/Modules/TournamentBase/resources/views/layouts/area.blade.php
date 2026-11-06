{{--
    Frame of every page of the tournament area (Pyramid, Mixed, championship, draws; player, public and admin pages):
    the area shell (layouts.admin) with the tournament sidebar (TournamentAreaNavigation: group "Turniere" with the
    modules, below it the current module's entries). The phone bar shows "Turniere" and the module name unless the
    page sets admin-title. Pages fill admin-content (or a module layout does); print views do not use this frame.
--}}
@php
    $tournamentArea = app(\App\Modules\TournamentBase\Support\TournamentAreaNavigation::class);
@endphp
@extends('layouts.admin')

@section('layout-title')
{!! trim($__env->yieldContent('title')) ?: trim($__env->yieldContent('admin-title')) !!} - {{ __('tournament-base::common.nav.area') }}
@endsection

@section('admin-eyebrow', __('tournament-base::common.nav.area'))

@section('admin-title', $tournamentArea->moduleLabel(request()))

@section('admin-sidebar')
    <x-layout.admin-sidebar :area="$tournamentArea" />
@endsection
