@extends('members::layout')
@section('admin-title', __('members::admin.dsgvo.title'))
@section('admin-content')
<div class="ui-page">
    <div class="ui-page-header"><h1>{{ __('members::admin.dsgvo.title') }}</h1></div>

    <div class="ui-card">
        <div class="ui-card-body">
            <form method="POST" action="{{ route('members.dsgvo.update') }}" class="ui-stack">
                @csrf
                <div class="ui-field max-w-[16rem]">
                    <label class="ui-label" for="dsgvo-retention-years">{{ __('members::admin.dsgvo.retention_years') }}</label>
                    <input id="dsgvo-retention-years" type="number" min="1" max="100" name="retention_years" value="{{ old('retention_years', $retentionYears) }}" required class="ui-input">
                    <p class="ui-kpi-meta">{{ __('members::admin.dsgvo.retention_years_hint') }}</p>
                    @error('retention_years')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <button type="submit" class="ui-btn ui-btn-primary">{{ __('members::admin.dsgvo.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
