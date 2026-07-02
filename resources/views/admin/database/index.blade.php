@extends('layouts.admin')
@section('admin-title', __('booking.admin.database.title'))
@section('admin-content')
<div class="flex flex-col gap-6">

    <h1 class="text-2xl font-bold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.database.title') }}</h1>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6] flex items-start justify-between gap-4">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.database.migrations_heading') }}</h2>
            @if($hasPending)
                <form method="POST" action="{{ route('admin.database.migrate') }}" class="flex w-full max-w-md flex-col gap-3" onsubmit="return confirm({{ Js::from(__('booking.admin.database.migrate_confirm')) }})">
                    @csrf
                    <div class="ui-field">
                        <label for="db-migrate-confirmation" class="ui-label">{{ __('booking.admin.database.migrate_confirmation_label') }}</label>
                        <input
                            id="db-migrate-confirmation"
                            type="text"
                            name="confirmation"
                            value="{{ old('confirmation') }}"
                            placeholder="{{ __('booking.admin.database.migrate_confirmation_placeholder') }}"
                            class="ui-input {{ $errors->has('confirmation') ? 'border-red-400 focus:ring-red-500' : '' }}"
                            autocomplete="off"
                            spellcheck="false">
                        <p class="text-xs text-[#6a6e73]">{{ __('booking.admin.database.migrate_confirmation_help', ['value' => $migrationConfirmationValue]) }}</p>
                        @error('confirmation')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-[#bf4316] hover:bg-[#9e3412] text-white text-sm font-medium px-4 py-2 rounded transition-colors">{{ __('booking.admin.database.migrate_button') }}</button>
                    </div>
                </form>
            @endif
        </div>
        <div class="px-6 py-5">
            @if(empty($migrations))
                <p class="text-sm text-[#6a6e73]">{{ __('booking.admin.database.no_pending') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#6a6e73] border-b border-[#f0ede6]">
                            <th class="py-2 pr-4">{{ __('booking.admin.database.migration_name') }}</th>
                            <th class="py-2">{{ __('booking.admin.database.migration_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($migrations as $migration)
                            <tr class="border-b border-[#f5f3ef]">
                                <td class="py-2 pr-4 font-mono text-xs">{{ $migration['name'] }}</td>
                                <td class="py-2">
                                    @if($migration['ran'])
                                        <span class="text-[#3e8635]">✓ {{ __('booking.admin.database.status_ran') }}</span>
                                    @else
                                        <span class="text-[#f0ab00]">⏳ {{ __('booking.admin.database.status_pending') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[#e0ddd7] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#f0ede6]">
            <h2 class="text-base font-semibold text-[#151515]" style="font-family: var(--font-display)">{{ __('booking.admin.database.tables_heading') }}</h2>
        </div>
        <div class="px-6 py-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-[#6a6e73] border-b border-[#f0ede6]">
                        <th class="py-2 pr-4">{{ __('booking.admin.database.table_name') }}</th>
                        <th class="py-2 pr-4">{{ __('booking.admin.database.table_exists') }}</th>
                        <th class="py-2">{{ __('booking.admin.database.table_missing_columns') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tables as $table)
                        <tr class="border-b border-[#f5f3ef]">
                            <td class="py-2 pr-4 font-mono text-xs">{{ $table['table'] }}</td>
                            <td class="py-2 pr-4">
                                @if($table['exists'])
                                    <span class="text-[#3e8635]">✓ {{ __('booking.admin.database.exists_yes') }}</span>
                                @else
                                    <span class="text-[#c9190b]">✕ {{ __('booking.admin.database.exists_no') }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-xs text-[#6a6e73]">
                                {{ $table['missing_columns'] !== [] ? implode(', ', $table['missing_columns']) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
