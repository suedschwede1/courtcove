@extends('layouts.app')
@section('title', __('booking.calendar.title', ['date' => $date->format('d.m.Y')]))

@section('calendar-system-info')
@php
    $operator = [
        'name' => trim((string) \App\Models\Option::getValue('client.name.full', '')),
        'email' => trim((string) \App\Models\Option::getValue('client.contact.email', '')),
        'phone' => trim((string) \App\Models\Option::getValue('client.contact.phone', '')),
        'website' => trim((string) \App\Models\Option::getValue('client.website', '')),
        'contact' => trim((string) \App\Models\Option::getValue('client.website.contact', '')),
        'imprint' => trim((string) \App\Models\Option::getValue('client.website.imprint', '')),
        'privacy' => trim((string) \App\Models\Option::getValue('client.website.privacy', '')),
    ];
@endphp
<div class="help-panel__grid">
    <section class="help-card">
        <p class="help-card__eyebrow">{{ __('booking.calendar.system_eyebrow') }}</p>
        <h2 class="help-card__title">{{ __('booking.calendar.information') }}</h2>
        <p class="help-card__text">
            {{ __('booking.calendar.system_text') }}
        </p>
        <ul class="help-card__list">
            @foreach(__('booking.calendar.system_items') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>

    {{-- The legend lives here, not above the grid, so the grid gets the full screen height. --}}
    <section class="help-card">
        <h2 class="help-card__title">{{ __('booking.calendar.legend.label') }}</h2>
        <x-calendar.legend :logged-in="$isLoggedIn" />
    </section>

    {{-- The calendar page has no footer: contact, imprint and privacy links are listed here. --}}
    @if(collect($operator)->contains(fn ($value) => $value !== ''))
        <section class="help-card">
            <p class="help-card__eyebrow">{{ __('booking.calendar.operator_eyebrow') }}</p>
            @if($operator['name'] !== '')
                <h2 class="help-card__title">{{ $operator['name'] }}</h2>
            @endif
            <ul class="help-card__list">
                @if($operator['email'] !== '')
                    <li>{{ __('booking.calendar.operator_email') }}: <a href="mailto:{{ $operator['email'] }}">{{ $operator['email'] }}</a></li>
                @endif
                @if($operator['phone'] !== '')
                    <li>{{ __('booking.calendar.operator_phone') }}: {{ $operator['phone'] }}</li>
                @endif
                @if($operator['website'] !== '')
                    <li>{{ __('booking.calendar.operator_website') }}: <a href="{{ $operator['website'] }}" target="_blank" rel="noreferrer">{{ $operator['website'] }}</a></li>
                @endif
                @if($operator['contact'] !== '')
                    <li>{{ __('booking.calendar.operator_contact') }}: <a href="{{ $operator['contact'] }}" target="_blank" rel="noreferrer">{{ __('booking.calendar.operator_contact_link') }}</a></li>
                @endif
                @if($operator['imprint'] !== '')
                    <li>{{ __('booking.calendar.operator_imprint') }}: <a href="{{ $operator['imprint'] }}" target="_blank" rel="noreferrer">{{ __('booking.calendar.operator_imprint_link') }}</a></li>
                @endif
                @if($operator['privacy'] !== '')
                    <li>{{ __('booking.calendar.operator_privacy') }}: <a href="{{ $operator['privacy'] }}" target="_blank" rel="noreferrer">{{ __('booking.calendar.operator_privacy_link') }}</a></li>
                @endif
            </ul>
        </section>
    @endif
</div>
@endsection

@section('calendar-help')
<div class="help-panel__grid">
    @auth
        <section class="help-card">
            <p class="help-card__eyebrow">{{ __('booking.calendar.my_area') }}</p>
            <h2 class="help-card__title">{{ $authUser->name }}</h2>
                        <p class="help-card__text">
                {{ __('booking.calendar.member_text') }}
            </p>
            <div class="help-card__status">
                <span class="help-card__status-label">{{ __('booking.calendar.status') }}</span>
                <strong>{{ __('booking.calendar.member_logged_in') }}</strong>
            </div>
        </section>
    @endauth

    <section class="help-card">
        <p class="help-card__eyebrow">{{ __('booking.nav.help') }}</p>
        <h2 class="help-card__title">{{ __('booking.calendar.help_heading') }}</h2>
                <ul class="help-card__list">
            @foreach(__('booking.calendar.help_items') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
</div>
@endsection

@section('content')
@php
    $hasInfoPanel = $__env->hasSection('calendar-system-info');
    $hasHelpPanel = $__env->hasSection('calendar-help');
@endphp
<div class="cal-page" x-data="{}">
    <div class="cal-toolbar no-print">
        <h1 class="cal-toolbar__title">
            <span class="cal-toolbar__weekday">{{ $date->isoFormat('dddd') }}</span><span class="cal-toolbar__sep">, </span><span class="cal-toolbar__date">{{ $date->isoFormat('LL') }}</span>
            @if($date->isToday())
                <span class="cal-toolbar__today-hint"><span aria-hidden="true"> &middot; </span>{{ __('booking.nav.today') }}</span>
            @endif
        </h1>

        <nav id="calendar-date-nav" class="cal-toolbar__nav" aria-label="{{ __('booking.calendar.date_navigation') }}">
            <x-ui.icon-button icon="chevron-left" :href="route('calendar.index', ['date' => $date->copy()->subDay()->format('Y-m-d')])" :label="__('booking.nav.previous_day')" data-cal-nav="prev" />
            <x-ui.button variant="secondary" :block="false" :href="route('calendar.index')">{{ __('booking.nav.today') }}</x-ui.button>
            <form method="GET" action="{{ route('calendar.index') }}" class="cal-toolbar__date-form">
                <label for="c-date" class="cal-toolbar__date-field">
                    <x-ui.icon name="calendar" :size="18" />
                    <span class="sr-only">{{ __('booking.nav.choose_date') }}</span>
                    <input type="date" id="c-date" name="date" value="{{ $date->format('Y-m-d') }}" class="cal-toolbar__date-input" x-on:change="$el.form.submit()">
                </label>
            </form>
            <x-ui.icon-button icon="chevron-right" :href="route('calendar.index', ['date' => $date->copy()->addDay()->format('Y-m-d')])" :label="__('booking.nav.next_day')" data-cal-nav="next" />
        </nav>
    </div>

    @if($hasInfoPanel || $hasHelpPanel)
        <div class="cal-toolbar__panels no-print">
            <span class="cal-toolbar__divider" aria-hidden="true"></span>
            @if($hasInfoPanel)
                <x-ui.button variant="text" :block="false" data-panel-toggle="system-info-panel" aria-controls="system-info-panel">{{ __('booking.nav.info') }}</x-ui.button>
            @endif
            @if($hasHelpPanel)
                <x-ui.button variant="text" :block="false" data-panel-toggle="help-panel" aria-controls="help-panel">{{ __('booking.nav.help') }}</x-ui.button>
            @endif
        </div>
    @endif

    <div class="cal-page__board">
        <x-calendar.grid
            :dates="$dates"
            :squares="$squares"
            :date-labels="$dateLabels"
            :reservations-by-slot="$reservationsBySlot"
            :event-blocks="$eventBlocks"
            :today="$today"
            :now="$now"
            :is-logged-in="$isLoggedIn"
            :is-admin="$isAdmin"
            :auth-user-id="$authUserId"
            :can-admin-events="$canAdminEvents"
            :date="$date"
        />
    </div>
</div>

<x-calendar.modals :date="$date" :squares="$squares" />
@endsection




