@extends('layouts.admin')
@section('admin-title', __('booking.admin.squares.edit_title'))
@section('admin-back', route('admin.squares.index'))
@section('admin-content')
<div class="ds-admin-stack ds-admin-form-page">
    <x-admin.page-header :title="__('booking.admin.squares.edit_title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.squares.index')" :back-label="__('booking.admin.squares.title')" />

    <form method="POST" action="{{ route('admin.squares.update', $square) }}" class="ds-admin-stack">
        @method('PUT')
        @include('admin.squares._form', ['form' => $form, 'square' => $square])
        <div class="ds-admin-actions">
            <div class="ds-admin-actions__group ds-admin-actions__group--main">
                <x-ui.button :href="route('admin.squares.index')" variant="secondary" :block="false">{{ __('booking.admin.common.abort') }}</x-ui.button>
                <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection
