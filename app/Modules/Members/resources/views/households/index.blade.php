@extends('members::layout')
@section('members-title', __('members::admin.households.title'))
@section('members-content')
@php
    use App\Modules\Members\Models\MemberHousehold;
    use App\Modules\Members\Support\MembersFormat;
    use App\Modules\Members\Support\StatusTone;
    use Illuminate\Support\Facades\Route;

    $dueLabels = [
        'bezahlt' => __('members::admin.dues.status_bezahlt'),
        'ausstehend' => __('members::admin.dues.status_ausstehend'),
        'ueberfaellig' => __('members::admin.dues.status_ueberfaellig'),
    ];
    $dueHeader = $fiscalYear
        ? __('members::admin.households.col_due', ['year' => $fiscalYear->name])
        : __('members::admin.dues.title');
    $total = $households->total();
    $creating = $creating || $errors->hasAny(['name', 'fee_category_id']);
    $shortName = fn ($member) => trim($member->firstname.' '.mb_substr((string) $member->lastname, 0, 1).'.');
    // Per row: short names of the billable members (payer marked), dormant count and the current household due.
    $rows = $households->getCollection()->map(function (MemberHousehold $household) use ($shortName) {
        $billable = $household->members->filter(fn ($m) => in_array($m->status, MemberHousehold::BILLABLE_STATUSES, true) && $m->anonymized_at === null);

        return [
            'household' => $household,
            'names' => $billable->map(fn ($m) => ['name' => $shortName($m), 'payer' => $m->id === $household->payer_member_id])->values(),
            'dormant' => $billable->where('status', 'ruhend')->count(),
            'billable' => $billable->count(),
            'due' => $household->dues->first(),
        ];
    });
@endphp
<x-members::page-header :title="__('members::admin.households.title')">
    <x-slot:actions>
        {{-- The Members area has no tab rows (sidebar navigation); "Mögliche Familien" is a header link once it exists. --}}
        @if(Route::has('members.households.suggestions'))
            <x-ui.button variant="secondary" :block="false" :href="route('members.households.suggestions')">{{ __('members::admin.households.tab_suggestions') }}</x-ui.button>
        @endif
        <x-ui.button :block="false" icon="plus" :href="route('members.households.index', ['anlegen' => 1]).'#hh-create'" class="mbr-phone-hidden">{{ __('members::admin.households.create') }}</x-ui.button>
    </x-slot:actions>
    @unless($creating)
        <x-slot:phoneAction>
            <x-ui.button :href="route('members.households.index', ['anlegen' => 1]).'#hh-create'">{{ __('members::admin.households.create') }}</x-ui.button>
        </x-slot:phoneAction>
    @endunless
</x-members::page-header>

@if($creating)
    <x-ui.card id="hh-create" :title="__('members::admin.households.create_title')">
        <form method="POST" action="{{ route('members.households.store') }}" class="mbr-filters mbr-compose">
            @csrf
            <x-ui.field class="mbr-filters__field" :label="__('members::admin.households.name')" for="name" :help="__('members::admin.households.name_help')" :error="$errors->first('name')">
                <x-ui.input maxlength="128" :value="\App\Support\Ui\FormValue::string(old('name'))" />
            </x-ui.field>
            <x-ui.field class="mbr-filters__field" :label="__('members::admin.households.tariff')" for="fee_category_id" :help="__('members::admin.households.tariff_help')" :error="$errors->first('fee_category_id')">
                <x-ui.select :options="$tariffs->pluck('name', 'id')->all()" :placeholder="__('members::admin.households.tariff_none')" />
            </x-ui.field>
            <x-ui.button type="submit" :block="false">{{ __('members::admin.households.create') }}</x-ui.button>
        </form>
    </x-ui.card>
@endif

{{-- Search and status chips (server side; the chips keep the search, the pager keeps both). --}}
<form method="GET" action="{{ route('members.households.index') }}" class="mbr-list-filters" role="search">
    @if($status !== 'aktiv')
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <div class="mbr-search">
        <label for="hh-search" class="sr-only">{{ __('members::admin.households.search_label') }}</label>
        <x-ui.icon name="search" :size="18" class="mbr-search__icon" />
        <input type="search" name="search" id="hh-search" value="{{ $search }}" maxlength="100"
            placeholder="{{ __('members::admin.households.search_placeholder') }}" class="ds-input mbr-search__input">
    </div>
    <div class="mbr-chips" role="group" aria-label="{{ __('members::admin.households.filter_label') }}">
        @foreach(['aktiv' => __('members::admin.households.chip_active'), 'aufgeloest' => __('members::admin.households.chip_dissolved')] as $key => $label)
            @php $active = $status === $key; @endphp
            <x-ui.chip :href="route('members.households.index', array_filter(['search' => $search, 'status' => $key === 'aktiv' ? null : $key]))" :active="$active">{{ $active ? __('members::admin.members.chip_count', ['label' => $label, 'count' => $total]) : $label }}</x-ui.chip>
        @endforeach
    </div>
</form>

<x-ui.card class="mbr-table-card" padding="none" as="section" aria-label="{{ __('members::admin.households.list_label') }}">
    @if($total === 0)
        <p class="mbr-list-empty">{{ __('members::admin.households.empty') }}</p>
    @else
        {{-- Desktop and tablet: table; phones get the cards below. --}}
        <div class="mbr-phone-hidden">
        <x-ui.table :caption="__('members::admin.households.title')" :stack="false" class="mbr-list-table">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('members::admin.households.col_household') }}</th>
                    <th scope="col">{{ __('members::admin.households.col_members') }}</th>
                    <th scope="col">{{ __('members::admin.households.col_tariff') }}</th>
                    <th scope="col">{{ $dueHeader }}</th>
                    <th scope="col">{{ __('members::admin.households.col_status') }}</th>
                    <th scope="col" class="mbr-cell-open"><span class="sr-only">{{ __('members::admin.common.actions') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach($rows as $row)
                @php $household = $row['household']; $due = $row['due']; @endphp
                <tr>
                    <td class="ds-cell--title">
                        @if(Route::has('members.households.show'))
                            <a href="{{ route('members.households.show', $household->id) }}" class="mbr-row-link">{{ $household->label() }}</a>
                        @else
                            {{ $household->label() }}
                        @endif
                    </td>
                    <td class="text-ink-2">
                        @forelse($row['names'] as $i => $entry)
                            {{ $i > 0 ? ' · ' : '' }}{{ $entry['name'] }}@if($entry['payer']) <span class="ds-badge ds-badge--neutral" title="{{ __('members::admin.households.payer') }}">{{ __('members::admin.households.payer_marker') }}</span>@endif
                        @empty
                            –
                        @endforelse
                        @if($row['dormant'] > 0)
                            <span class="text-ink-3">{{ __('members::admin.households.dormant_count', ['count' => $row['dormant']]) }}</span>
                        @endif
                    </td>
                    <td class="text-ink-2">{{ $household->feeCategory->name ?? '–' }}</td>
                    <td class="tabular-nums whitespace-nowrap">{{ $due ? MembersFormat::money($due->amount) : '–' }}</td>
                    <td>
                        @if($household->isDissolved())
                            <x-ui.badge tone="neutral">{{ __('members::admin.households.dissolved_badge') }}</x-ui.badge>
                        @elseif($due)
                            <x-ui.badge :tone="StatusTone::due($due->payments_exists ? 'bezahlt' : $due->displayStatus())">{{ $dueLabels[$due->payments_exists ? 'bezahlt' : $due->displayStatus()] ?? $due->displayStatus() }}</x-ui.badge>
                        @elseif($row['billable'] < MemberHousehold::MIN_BILLABLE_MEMBERS)
                            <x-ui.badge tone="attention">{{ $row['billable'] === 1 ? __('members::admin.households.only_one_billable') : __('members::admin.households.no_billable') }}</x-ui.badge>
                        @else
                            <span class="text-ink-2">–</span>
                        @endif
                    </td>
                    <td class="mbr-cell-open">
                        @if(Route::has('members.households.show'))
                            <a href="{{ route('members.households.show', $household->id) }}" class="mbr-open-link" aria-label="{{ __('members::admin.households.open', ['name' => $household->label()]) }}"><x-ui.icon name="chevron-right" :size="18" /></a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        </div>

        {{-- Phones: compact cards that open the household. --}}
        <ul class="mbr-member-cards mbr-phone-only" aria-label="{{ __('members::admin.households.list_label') }}">
            @foreach($rows as $row)
                @php $household = $row['household']; $due = $row['due']; @endphp
                <li>
                    <a href="{{ Route::has('members.households.show') ? route('members.households.show', $household->id) : route('members.households.index') }}" class="mbr-member-card">
                        <span class="mbr-member-card__text">
                            <span class="mbr-member-card__name">{{ $household->label() }}</span>
                            <span class="mbr-member-card__meta">{{ $row['names']->pluck('name')->implode(' · ') ?: '–' }}@if($row['dormant'] > 0) {{ __('members::admin.households.dormant_count', ['count' => $row['dormant']]) }}@endif</span>
                            <span class="mbr-member-card__meta">{{ $household->feeCategory->name ?? '–' }}@if($due) · {{ MembersFormat::money($due->amount) }} ({{ $fiscalYear?->name }})@endif</span>
                        </span>
                        <span class="mbr-member-card__badges">
                            @if($household->isDissolved())
                                <x-ui.badge tone="neutral">{{ __('members::admin.households.dissolved_badge') }}</x-ui.badge>
                            @elseif($due)
                                <x-ui.badge :tone="StatusTone::due($due->payments_exists ? 'bezahlt' : $due->displayStatus())">{{ $dueLabels[$due->payments_exists ? 'bezahlt' : $due->displayStatus()] ?? $due->displayStatus() }}</x-ui.badge>
                            @elseif($row['billable'] < MemberHousehold::MIN_BILLABLE_MEMBERS)
                                <x-ui.badge tone="attention">{{ $row['billable'] === 1 ? __('members::admin.households.only_one_billable') : __('members::admin.households.no_billable') }}</x-ui.badge>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mbr-table-foot mbr-list-foot">
        <span>{{ __('members::admin.households.range', ['from' => $households->firstItem() ?? 0, 'to' => $households->lastItem() ?? 0, 'total' => $total]) }}</span>
        <x-ui.pagination :paginator="$households" />
    </div>
</x-ui.card>
@endsection
