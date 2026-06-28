@extends('layouts.admin')
@section('admin-title', 'Konfiguration')
@section('admin-content')
<h1>{{ __('booking.admin.config') }}</h1>
<form method="POST" action="{{ route('admin.config.update') }}">
    @method('PUT')
    @csrf
    <label>{{ __('booking.admin.system_name') }}
        <input type="text" name="system_name" value="{{ $values['system_name'] }}">
    </label>
    <label>{{ __('booking.admin.client_name_full') }}
        <input type="text" name="client_name_full" value="{{ $values['client_name_full'] }}">
    </label>
    <label>{{ __('booking.admin.contact_email') }}
        <input type="email" name="contact_email" value="{{ $values['contact_email'] }}">
    </label>
    <label>{{ __('booking.admin.calendar_days') }}
        <input type="number" name="calendar_days" min="1" max="31" value="{{ $values['calendar_days'] }}">
    </label>
    <label>{{ __('booking.admin.registration') }}
        <select name="registration">
            <option value="0" @selected((string) $values['registration'] === '0')>{{ __('booking.admin.no') }}</option>
            <option value="1" @selected((string) $values['registration'] === '1')>{{ __('booking.admin.yes') }}</option>
        </select>
    </label>
    <label>{{ __('booking.admin.maintenance') }}
        <select name="maintenance">
            <option value="0" @selected((string) $values['maintenance'] === '0')>{{ __('booking.admin.off') }}</option>
            <option value="1" @selected((string) $values['maintenance'] === '1')>{{ __('booking.admin.on') }}</option>
        </select>
    </label>
    <button type="submit" class="default-button">{{ __('booking.admin.save') }}</button>
</form>
@endsection
