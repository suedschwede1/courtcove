@extends('layouts.app')
@section('title', __('booking.calendar.title', ['date' => $date->format('d.m.Y')]))

@push('header-nav')
<a href="{{ route('calendar.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}"
   class="ui-calendar-nav-btn ui-calendar-nav-btn--arrow"
   title="{{ __('booking.nav.previous_day') }}">&#8249;</a>

<a href="{{ route('calendar.index') }}"
   class="ui-calendar-nav-btn ui-calendar-nav-btn--today">{{ __('booking.nav.today') }}</a>

<form method="GET" action="{{ route('calendar.index') }}" class="ui-calendar-date-form">
    <label for="c-date" class="sr-only">{{ __('booking.nav.choose_date') }}</label>
    <div class="relative">
        <input type="date" name="date" id="c-date"
               value="{{ $date->format('Y-m-d') }}"
               class="ui-calendar-date-input"
               aria-label="{{ __('booking.nav.choose_date') }}"
               onchange="this.form.submit()">
    </div>
</form>

<a href="{{ route('calendar.index', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}"
   class="ui-calendar-nav-btn ui-calendar-nav-btn--arrow"
   title="{{ __('booking.nav.next_day') }}">&#8250;</a>
@endpush

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
<div class="help-panel__grid help-panel__grid--single">
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

        @if(collect($operator)->contains(fn ($value) => $value !== ''))
            <p class="help-card__eyebrow" style="margin-top: 1.5rem;">{{ __('booking.calendar.operator_eyebrow') }}</p>
            @if($operator['name'] !== '')
                <h3 class="help-card__title" style="font-size: 1.5rem; margin-top: 0.35rem;">{{ $operator['name'] }}</h3>
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
        @endif
    </section>
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
<div class="calendar-layout calendar-layout--mobile-safe" x-data="{}">
    <div class="calendar-wrap">
        <x-calendar.grid
            :dates="$dates"
            :squares="$squares"
            :date-labels="$dateLabels"
            :reservations-by-slot="$reservationsBySlot"
            :event-blocks="$eventBlocks"
            :event-skip="$eventSkip"
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




