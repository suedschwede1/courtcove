{{-- No championship exists yet (ZF2 answered with an error page). --}}
@extends('tournament-base::layouts.module')

@section('title', __('tournament::common.empty.title'))

@section('module-content')
    <x-ui.notice tone="neutral">{{ __('tournament::common.empty.text') }}</x-ui.notice>
@endsection
