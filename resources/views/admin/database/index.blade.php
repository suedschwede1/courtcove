@extends('layouts.admin')
@section('admin-title', __('booking.admin.database.title'))
@section('admin-content')
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.database.title')" :eyebrow="__('booking.admin.nav.eyebrow')" />

    @if(session('output'))
        <x-ui.card :title="__('booking.admin.database.output_heading')" padding="lg" class="ds-admin-form-card">
            <pre class="ds-admin-output">{{ session('output') }}</pre>
        </x-ui.card>
    @endif

    <x-ui.card padding="none" class="ds-admin-db ds-admin-rows" :title="__('booking.admin.database.migrations_heading')">
        @if($hasPending)
            <form method="POST" action="{{ route('admin.database.migrate') }}" class="ds-admin-panel ds-admin-migrate" onsubmit="return confirm({{ Js::from(__('booking.admin.database.migrate_confirm')) }})">
                @csrf
                <x-ui.field :label="__('booking.admin.database.migrate_confirmation_label')" for="db-migrate-confirmation" :help="__('booking.admin.database.migrate_confirmation_help', ['value' => $migrationConfirmationValue])" :error="$errors->first('confirmation')">
                    <x-ui.input id="db-migrate-confirmation" name="confirmation" :value="old('confirmation')" :placeholder="__('booking.admin.database.migrate_confirmation_placeholder')" autocomplete="off" spellcheck="false" />
                </x-ui.field>
                <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.database.migrate_button') }}</x-ui.button>
            </form>
        @endif
        @if(empty($migrations))
            <p class="ds-admin-note">{{ __('booking.admin.database.no_pending') }}</p>
        @else
            <x-ui.table :caption="__('booking.admin.database.migrations_heading')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.database.migration_name') }}</th>
                        <th scope="col">{{ __('booking.admin.database.migration_status') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($migrations as $migration)
                    <tr>
                        <td class="ds-cell--title ds-admin-mono" data-label="{{ __('booking.admin.database.migration_name') }}">{{ $migration['name'] }}</td>
                        <td data-label="{{ __('booking.admin.database.migration_status') }}">
                            @if($migration['ran'])
                                <x-ui.badge tone="good">{{ __('booking.admin.database.status_ran') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="attention">{{ __('booking.admin.database.status_pending') }}</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card padding="none" class="ds-admin-db ds-admin-rows" :title="__('booking.admin.database.tables_heading')">
        <x-ui.table :caption="__('booking.admin.database.tables_heading')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('booking.admin.database.table_name') }}</th>
                    <th scope="col">{{ __('booking.admin.database.table_exists') }}</th>
                    <th scope="col">{{ __('booking.admin.database.table_missing_columns') }}</th>
                    <th scope="col">{{ __('booking.admin.database.repair_heading') }}</th>
                </tr>
            </x-slot:head>
            @foreach($tables as $table)
                <tr>
                    <td class="ds-cell--title ds-admin-mono" data-label="{{ __('booking.admin.database.table_name') }}">{{ $table['table'] }}</td>
                    <td data-label="{{ __('booking.admin.database.table_exists') }}">
                        @if($table['exists'])
                            <x-ui.badge tone="good">{{ __('booking.admin.database.exists_yes') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="problem">{{ __('booking.admin.database.exists_no') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="ds-admin-mono" data-label="{{ __('booking.admin.database.table_missing_columns') }}">{{ $table['missing_columns'] !== [] ? implode(', ', $table['missing_columns']) : '—' }}</td>
                    <td data-label="{{ __('booking.admin.database.repair_heading') }}">
                        @if(!$table['exists'] || $table['missing_columns'] !== [])
                            <div class="ds-admin-repair">
                                <p class="ds-admin-note">
                                    @if(!$table['exists'])
                                        {{ __('booking.admin.database.repair_preview_create') }}
                                    @else
                                        {{ __('booking.admin.database.repair_preview_columns', ['columns' => implode(', ', $table['missing_columns'])]) }}
                                    @endif
                                </p>
                                <form method="POST" action="{{ route('admin.database.repair', $table['table']) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.database.repair_confirm')) }})">
                                    @csrf
                                    <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.database.repair_button') }}</x-ui.button>
                                </form>
                            </div>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.card padding="none" class="ds-admin-db ds-admin-rows" :title="__('booking.admin.database.drift_heading')">
        @if(empty($drift))
            <p class="ds-admin-note">{{ __('booking.admin.database.drift_none') }}</p>
        @else
            <x-ui.table :caption="__('booking.admin.database.drift_heading')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.database.table_name') }}</th>
                        <th scope="col">{{ __('booking.admin.database.drift_finding') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($drift as $finding)
                    <tr>
                        <td class="ds-cell--title ds-admin-mono" data-label="{{ __('booking.admin.database.table_name') }}">{{ $finding['table'] }}</td>
                        <td data-label="{{ __('booking.admin.database.drift_finding') }}">
                            @switch($finding['kind'])
                                @case('extra_column')
                                    {{ __('booking.admin.database.drift_extra_column', ['column' => $finding['column']]) }}
                                    @break
                                @case('type_mismatch')
                                    {{ __('booking.admin.database.drift_type_mismatch', ['column' => $finding['column'], 'expected' => $finding['expected'], 'actual' => $finding['actual']]) }}
                                    @break
                                @case('missing_index')
                                    {{ __('booking.admin.database.drift_missing_index', ['signature' => $finding['signature']]) }}
                                    @break
                                @case('extra_index')
                                    {{ __('booking.admin.database.drift_extra_index', ['signature' => $finding['signature']]) }}
                                    @break
                                @case('drift_check_failed')
                                    {{ __('booking.admin.database.drift_check_failed', ['message' => $finding['message']]) }}
                                    @break
                            @endswitch
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card padding="none" class="ds-admin-db ds-admin-rows ds-admin-lists" :title="__('booking.admin.database.backups_heading')">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.database.backups.create') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" :block="false" icon="plus">{{ __('booking.admin.database.backup_create_button') }}</x-ui.button>
            </form>
        </x-slot:actions>
        @if(empty($backups))
            <p class="ds-admin-note">{{ __('booking.admin.database.backup_none') }}</p>
        @else
            <x-ui.table :caption="__('booking.admin.database.backups_heading')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.database.backup_filename') }}</th>
                        <th scope="col">{{ __('booking.admin.database.backup_size') }}</th>
                        <th scope="col">{{ __('booking.admin.database.backup_created_at') }}</th>
                        <th scope="col" class="ds-admin-actions-cell">{{ __('booking.admin.database.backup_actions') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($backups as $backup)
                    <tr>
                        <td class="ds-cell--title ds-admin-mono" data-label="{{ __('booking.admin.database.backup_filename') }}">{{ $backup['filename'] }}</td>
                        <td data-label="{{ __('booking.admin.database.backup_size') }}">{{ number_format($backup['sizeBytes'] / 1024, 1) }} KB</td>
                        <td data-label="{{ __('booking.admin.database.backup_created_at') }}">{{ $backup['createdAt']->format('d.m.Y H:i:s') }}</td>
                        <td class="ds-admin-actions-cell" data-label="{{ __('booking.admin.database.backup_actions') }}">
                            <div class="ds-admin-row-actions">
                                <x-ui.button :href="route('admin.database.backups.download', $backup['filename'])" variant="secondary" :block="false">{{ __('booking.admin.database.backup_download') }}</x-ui.button>
                                <form method="POST" action="{{ route('admin.database.backups.delete', $backup['filename']) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.database.backup_delete_confirm')) }})">
                                    @csrf
                                    <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.database.backup_delete') }}</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.database.backups.restore', $backup['filename']) }}" class="ds-admin-restore" onsubmit="return confirm({{ Js::from(__('booking.admin.database.backup_restore_warning')) }})">
                                    @csrf
                                    <input id="db-restore-{{ $loop->index }}" type="text" name="confirmation" class="ds-input ds-admin-restore__input" placeholder="{{ __('booking.admin.database.backup_restore_confirm_placeholder') }}" aria-label="{{ __('booking.admin.database.migrate_confirmation_label') }}: {{ $backup['filename'] }}" autocomplete="off" spellcheck="false">
                                    <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.database.backup_restore') }}</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
@endsection
