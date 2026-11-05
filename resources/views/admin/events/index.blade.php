@extends('layouts.admin')
@section('admin-title', __('booking.admin.events.title'))
@section('admin-content')
<div class="ds-admin-stack">
    <x-admin.page-header :title="__('booking.admin.events.title')" :eyebrow="__('booking.admin.nav.eyebrow')">
        <p class="ds-admin-note">{{ __('booking.admin.events.intro_index') }}</p>
        <x-slot:actions>
            <x-ui.button :href="route('admin.events.create')" icon="plus" :block="false" class="ds-admin-fab">{{ __('booking.admin.events.new_event') }}</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-ui.card padding="lg" :title="__('booking.admin.users.filter_heading')">
        <form method="GET" action="{{ route('admin.events.index') }}" class="ds-admin-filter">
            <x-ui.field :label="__('booking.admin.events.name')" for="events-q" class="ds-admin-filter__wide">
                <x-ui.input name="q" id="events-q" type="search" :value="$filters['q'] ?? ''" :placeholder="__('booking.admin.events.name').' ...'" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.events.court')" for="events-sid">
                <x-ui.select name="sid" id="events-sid" :options="$squares->pluck('display_name', 'sid')->all()" :selected="(string) ($filters['sid'] ?? '')" :placeholder="__('booking.admin.common.all_courts')" />
            </x-ui.field>
            <x-ui.field :label="__('booking.admin.events.date_start')" for="events-date">
                <x-ui.input name="date" id="events-date" type="date" :value="$filters['date'] ?? ''" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('booking.admin.common.filter') }}</x-ui.button>
        </form>
    </x-ui.card>

    @if($searched)
        @if($events->isEmpty())
            <x-ui.card :title="__('booking.admin.events.title')">
                <p class="ds-admin-note">{{ __('booking.admin.no_results') }}</p>
            </x-ui.card>
        @else
            <x-ui.card padding="none" class="ds-admin-rows ds-admin-lists" :title="__('booking.admin.events.title')">
                <x-slot:actions>
                    <span class="ds-admin-note">{{ __('booking.admin.events.results_count', ['count' => $events->count()]) }}</span>
                </x-slot:actions>
                <x-ui.table :caption="__('booking.admin.events.title')">
                    <x-slot:head>
                        <tr>
                            <th scope="col">{{ __('booking.admin.events.name') }}</th>
                            <th scope="col">{{ __('booking.admin.events.court') }}</th>
                            <th scope="col">{{ __('booking.admin.events.from') }}</th>
                            <th scope="col">{{ __('booking.admin.events.to') }}</th>
                            <th scope="col">{{ __('booking.admin.events.status') }}</th>
                            <th scope="col" class="ds-admin-actions-cell">{{ __('booking.admin.events.actions') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach($events as $event)
                        <tr>
                            <td class="ds-cell--title" data-label="{{ __('booking.admin.events.name') }}">{{ $event->meta->firstWhere('key', 'name')?->value ?? __('booking.account.empty_option') }}</td>
                            <td data-label="{{ __('booking.admin.events.court') }}">{{ $event->square?->display_name ?? __('booking.admin.events.all_courts') }}</td>
                            <td data-label="{{ __('booking.admin.events.from') }}">{{ $event->datetime_start?->format('d.m.Y H:i') }}</td>
                            <td data-label="{{ __('booking.admin.events.to') }}">{{ $event->datetime_end?->format('d.m.Y H:i') }}</td>
                            <td data-label="{{ __('booking.admin.events.status') }}"><x-ui.badge tone="neutral">{{ $event->status === 'enabled' ? __('booking.admin.events.status_enabled') : $event->status }}</x-ui.badge></td>
                            <td class="ds-admin-actions-cell" data-label="{{ __('booking.admin.events.actions') }}">
                                <div class="ds-admin-row-actions">
                                    <x-ui.button :href="route('admin.events.edit', $event)" variant="secondary" :block="false">{{ __('booking.admin.common.edit') }}</x-ui.button>
                                    <form method="POST" action="{{ route('admin.events.destroy', $event) }}" onsubmit="return confirm({{ Js::from(__('booking.admin.events.confirm_delete')) }})">
                                        @method('DELETE')
                                        @csrf
                                        <x-ui.button type="submit" variant="danger" :block="false">{{ __('booking.admin.common.delete') }}</x-ui.button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        @endif
    @else
        <x-ui.card><p class="ds-admin-note">{{ __('booking.admin.search_hint') }}</p></x-ui.card>
    @endif
</div>
@endsection
