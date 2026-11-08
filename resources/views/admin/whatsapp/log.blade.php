@extends('layouts.admin')
@section('admin-title', __('booking.admin.whatsapp.log.title'))
@section('admin-content')
@php
    $statusTone = [
        'delivered' => 'good', 'read' => 'good',
        'failed' => 'problem', 'undelivered' => 'problem',
        'skipped' => 'attention',
    ];
    // Chip link: the current filters with one of them replaced (null = all); the page number is dropped.
    $chipUrl = fn (string $key, ?string $value) => route('admin.whatsapp.log', array_filter(array_merge($filters, [$key => $value]), fn ($v) => $v !== null));
    $de = app()->getLocale() === 'de';
    $money = fn (int $micros) => '≈ '.number_format($micros / 1_000_000, 2, $de ? ',' : '.', $de ? '.' : ',').' EUR';
@endphp
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.whatsapp.log.title')" :eyebrow="__('booking.admin.nav.eyebrow')" :back-url="route('admin.whatsapp.edit')" :back-label="__('booking.admin.whatsapp.title')">
        <p class="ds-admin-note">{{ __('booking.admin.whatsapp.log.intro') }}</p>
    </x-admin.page-header>

    <x-ui.card padding="lg">
        <p class="ds-admin-note">{{ __('booking.admin.whatsapp.log.filter_status') }}</p>
        <div class="ds-admin-badges">
            <x-ui.chip :href="$chipUrl('status', null)" :active="$filters['status'] === null">{{ __('booking.admin.search_status_all') }}</x-ui.chip>
            @foreach($statuses as $status)
                <x-ui.chip :href="$chipUrl('status', $status)" :active="$filters['status'] === $status">{{ __('booking.admin.whatsapp.log.status.'.$status) }}</x-ui.chip>
            @endforeach
        </div>
        <p class="ds-admin-note">{{ __('booking.admin.whatsapp.log.filter_type') }}</p>
        <div class="ds-admin-badges">
            <x-ui.chip :href="$chipUrl('type', null)" :active="$filters['type'] === null">{{ __('booking.admin.whatsapp.log.all_types') }}</x-ui.chip>
            @foreach($types as $type)
                <x-ui.chip :href="$chipUrl('type', $type['value'])" :active="$filters['type'] === $type['value']">{{ __('booking.admin.whatsapp.log.types.'.$type['key']) }}</x-ui.chip>
            @endforeach
        </div>
        <p class="ds-admin-note">{{ __('booking.admin.whatsapp.log.filter_month') }}</p>
        <div class="ds-admin-badges">
            <x-ui.chip :href="$chipUrl('month', null)" :active="$filters['month'] === null">{{ __('booking.admin.whatsapp.log.all_months') }}</x-ui.chip>
            @foreach($months as $value => $label)
                <x-ui.chip :href="$chipUrl('month', $value)" :active="$filters['month'] === $value">{{ $label }}</x-ui.chip>
            @endforeach
        </div>
    </x-ui.card>

    @if($entries->isEmpty())
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.whatsapp.log.empty') }}</p></x-ui.card>
    @else
        <x-ui.card padding="none" class="ds-admin-rows" :title="__('booking.admin.whatsapp.log.title')">
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
                            ? __('booking.admin.whatsapp.skip_reasons.'.$entry->skip_reason)
                            : $entry->error_code;
                        $micros = $entry->cost_reported_micros ?? $entry->cost_estimate_micros;
                    @endphp
                    <tr>
                        <td class="ds-cell--title" data-label="{{ __('booking.admin.whatsapp.log.col_time') }}">{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_type') }}">{{ $typeCase !== null ? __('booking.admin.whatsapp.log.types.'.$typeCase->templateKey()) : $entry->type }}</td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_user') }}">
                            @if($alias !== null)
                                <a href="{{ route('admin.users.edit', $entry->user_id) }}" class="ds-admin-link">{{ $alias }}</a>
                            @else
                                —
                            @endif
                            @if($entry->phone_last2 !== null)
                                <span class="ds-admin-note">… {{ $entry->phone_last2 }}</span>
                            @endif
                        </td>
                        <td data-label="{{ __('booking.admin.whatsapp.log.col_status') }}">
                            <x-ui.badge :tone="$statusTone[$entry->status] ?? 'neutral'">{{ __('booking.admin.whatsapp.log.status.'.$entry->status) }}</x-ui.badge>
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
