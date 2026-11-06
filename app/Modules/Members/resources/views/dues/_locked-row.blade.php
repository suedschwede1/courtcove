{{--
    Read-only row of a due whose Geschäftsjahr is closed: same columns as the editable row, no inline
    editing, no selection. $context is 'list' (dues list) or 'member' (compact dues card on the member page).
--}}
@php
    use App\Modules\Members\Support\MembersFormat;
    use App\Modules\Members\Support\StatusTone;

    $statusLabels = [
        'bezahlt' => __('members::admin.dues.status_bezahlt'),
        'ausstehend' => __('members::admin.dues.status_ausstehend'),
        'ueberfaellig' => __('members::admin.dues.status_ueberfaellig'),
    ];
    $shownStatus = $due->displayStatus();
    $badgeClasses = StatusTone::badgeClasses(StatusTone::DUE);
@endphp
@if($context === 'member')
{{-- Member dues card: the compact columns of the editable rows; the extras are shown read-only in the info cell. --}}
@php
    $extras = array_filter([
        $due->bezahlt_bis_fiscal_year_id !== null ? __('members::admin.dues.paid_through').' '.($fiscalYears->firstWhere('id', $due->bezahlt_bis_fiscal_year_id)->name ?? '–') : null,
        $due->teilbetrag !== null ? __('members::admin.dues.teilbetrag').' '.MembersFormat::money($due->teilbetrag) : null,
        $due->kommentar ?: null,
    ]);
@endphp
<tr class="mbr-due-line" data-locked="true">
    <td class="mbr-due-year ds-cell--title" data-label="{{ __('members::admin.dues.year_short') }}">{{ $due->fiscalYear->labelWithState() }}</td>
    <td class="mbr-due-info" data-label="{{ __('members::admin.dues.category_short') }}">{{ $due->feeCategory->name }}@foreach($extras as $extra)<span class="mbr-due-info__extra">{{ $extra }}</span>@endforeach</td>
    <td class="mbr-due-amount tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.amount') }}" data-amount>{{ MembersFormat::money($due->amount) }}</td>
    <td class="mbr-due-status" data-label="{{ __('members::admin.dues.status') }}"><span class="ds-badge {{ $badgeClasses[$shownStatus] ?? '' }}">{{ $statusLabels[$shownStatus] ?? $shownStatus }}</span></td>
    <td class="mbr-due-paid tabular-nums" data-label="{{ __('members::admin.dues.bezahlt_am') }}">{{ $due->paidOnDisplay() }}</td>
    <td class="mbr-due-toggle"></td>
</tr>
@else
<tr data-locked="true">
    <td class="mbr-check-cell"></td>
    <td class="ds-cell--title" data-label="{{ __('members::admin.members.name') }}">{{ $due->member->fullName() }}</td>
    <td class="mbr-phone-hidden text-ink-2" data-label="{{ __('members::admin.dues.fiscal_year') }}">{{ $due->fiscalYear->name }}</td>
    <td class="mbr-phone-hidden text-ink-2" data-label="{{ __('members::admin.members.fee_category') }}">{{ $due->feeCategory->name }}</td>
    <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.amount') }}">{{ MembersFormat::money($due->amount) }}</td>
    <td class="tabular-nums whitespace-nowrap" data-label="{{ __('members::admin.dues.outstanding') }}">{{ $due->outstandingAmount() === '0.00' ? '–' : MembersFormat::money($due->outstandingAmount()) }}</td>
    <td data-label="{{ __('members::admin.dues.status') }}"><span class="ds-badge {{ $badgeClasses[$shownStatus] ?? '' }}">{{ $statusLabels[$shownStatus] ?? $shownStatus }}</span></td>
    <td class="tabular-nums" data-label="{{ __('members::admin.dues.bezahlt_am') }}">{{ $due->paidOnDisplay() }}</td>
    <td class="text-ink-2" data-label="{{ __('members::admin.dues.paid_through') }}">{{ $fiscalYears->firstWhere('id', $due->bezahlt_bis_fiscal_year_id)->name ?? '–' }}</td>
    <td class="tabular-nums" data-label="{{ __('members::admin.dues.teilbetrag') }}">{{ MembersFormat::optionalMoney($due->teilbetrag) }}</td>
    <td class="text-ink-2" data-label="{{ __('members::admin.dues.kommentar') }}">{{ $due->kommentar ?: '–' }}</td>
</tr>
@endif
