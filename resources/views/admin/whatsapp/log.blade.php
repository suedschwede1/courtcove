@extends('layouts.admin')
@section('admin-title', __('booking.admin.whatsapp.log.title'))
@section('admin-content')
@php
    $statusTone = [
        'delivered' => 'good', 'read' => 'good',
        'failed' => 'problem', 'undelivered' => 'problem',
        'skipped' => 'attention',
    ];
    // Known keys translate; an unknown value (old or foreign data) is shown as the raw value, never as a language key.
    $tr = fn (string $key, ?string $raw) => \Illuminate\Support\Facades\Lang::has($key) ? __($key) : (string) $raw;
    $statusOptions = collect($statuses)->mapWithKeys(fn ($st) => [$st => $tr('booking.admin.whatsapp.log.status.'.$st, $st)])->all();
    $typeOptions = collect($types)->mapWithKeys(fn ($t) => [$t['value'] => $tr('booking.admin.whatsapp.log.types.'.$t['key'], $t['value'])])->all();
    $de = app()->getLocale() === 'de';
    $money = fn (int $micros) => '≈ '.number_format($micros / 1_000_000, 2, $de ? ',' : '.', $de ? '.' : ',').' EUR';
@endphp
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.whatsapp.log.title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.whatsapp.edit')" :back-label="__('booking.admin.whatsapp.title')">
        <p class="ds-admin-note">{{ __('booking.admin.whatsapp.log.intro') }}</p>
    </x-admin.page-header>

    <x-ui.card padding="lg">
        <form method="GET" action="{{ route('admin.whatsapp.log') }}" class="ds-admin-filter">
            <x-ui.field :label="__('booking.admin.whatsapp.log.filter_status')" for="wa-log-status">
                <x-ui.select name="status" id="wa-log-status" :options="$statusOptions" :selected="$filters['status'] ?? ''" :placeholder="__('booking.admin.search_status_all')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.whatsapp.log.filter_type')" for="wa-log-type">
                <x-ui.select name="type" id="wa-log-type" :options="$typeOptions" :selected="$filters['type'] ?? ''" :placeholder="__('booking.admin.whatsapp.log.all_types')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.whatsapp.log.filter_month')" for="wa-log-month">
                <x-ui.select name="month" id="wa-log-month" :options="$months" :selected="$filters['month'] ?? ''" :placeholder="__('booking.admin.whatsapp.log.all_months')" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.filter') }}</x-ui.button>
        </form>
    </x-ui.card>

    @if($entries->isEmpty())
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.whatsapp.log.empty') }}</p></x-ui.card>
    @else
        <x-ui.card padding="none" class="ds-admin-rows ds-admin-wa-log">
            <x-ui.table :caption="__('booking.admin.whatsapp.log.title')">
                <x-slot:head>
                    <tr>
                        <th scope="col">{{ __('booking.admin.whatsapp.log.col_time') }}</th>
                        <th scope="col">{{ __('booking.admin.whatsapp.log.col_type') }}</th>
                        <th scope="col">{{ __('booking.admin.whatsapp.log.col_user') }}</th>
                        <th scope="col">{{ __('booking.admin.whatsapp.log.col_status') }}</th>
                        <th scope="col">{{ __('booking.admin.whatsapp.log.col_reason') }}</th>
                        <th scope="col">{{ __('booking.admin.whatsapp.log.col_cost') }}</th>
                    </tr>
                </x-slot:head>
                @foreach($entries as $entry)
                    @php
                        $typeCase = \App\Notifications\Types\NotificationType::tryFrom($entry->type);
                        $alias = $entry->user_id !== null ? ($users[$entry->user_id] ?? null) : null;
                        $reason = $entry->skip_reason !== null
                            ? $tr('booking.admin.whatsapp.skip_reasons.'.$entry->skip_reason, $entry->skip_reason)
                            : $entry->error_code;
                        $micros = $entry->cost_reported_micros ?? $entry->cost_estimate_micros;
                    @endphp
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.whatsapp.log.col_time') }}">{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_type') }}">{{ $typeCase !== null ? $tr('booking.admin.whatsapp.log.types.'.$typeCase->templateKey(), $entry->type) : $entry->type }}</td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_user') }}"><span class="ds-admin-wa-user">
                            @if($alias !== null)
                                <a href="{{ route('admin.users.edit', $entry->user_id) }}" class="ds-admin-link">{{ $alias }}</a>
                            @else
                                —
                            @endif
                            @if($entry->phone_last2 !== null)
                                <span class="ds-admin-note">… {{ $entry->phone_last2 }}</span>
                            @endif
                        </span></td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_status') }}">
                            <x-ui.badge :tone="$statusTone[$entry->status] ?? 'neutral'">{{ $tr('booking.admin.whatsapp.log.status.'.$entry->status, $entry->status) }}</x-ui.badge>
                        </td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_reason') }}">{{ $reason ?? '—' }}</td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_cost') }}">{{ $micros !== null ? $money($micros) : '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.pagination :paginator="$entries" />
</div>
@endsection
