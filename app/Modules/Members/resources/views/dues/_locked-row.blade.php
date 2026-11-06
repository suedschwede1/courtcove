{{--
    Read-only row of a due whose Geschäftsjahr is closed: same columns as the editable row, no inline
    editing, no selection. $context is 'list' (dues list) or 'member' (dues card on the member page).
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
<tr data-locked="true">
    @if($context === 'list')
        <td class="mbr-check-cell"></td>
        <td class="ds-cell--title" data-label="{{ __('members::admin.members.name') }}">{{ $due->member->fullName() }}</td>
        <td class="mbr-phone-hidden text-ink-2" data-label="{{ __('members::admin.dues.fiscal_year') }}">{{ $due->fiscalYear->labelWithState() }}</td>
        <td class="mbr-phone-hidden text-ink-2" data-label="{{ __('members::admin.members.fee_category') }}">{{ $due->feeCategory->name }}</td>
        <td class="tabular-nums" data-label="{{ __('members::admin.dues.amount') }}">{{ MembersFormat::money($due->amount) }}</td>
        <td class="tabular-nums" data-label="{{ __('members::admin.dues.outstanding') }}">{{ $due->outstandingAmount() === '0.00' ? '–' : MembersFormat::money($due->outstandingAmount()) }}</td>
    @else
        <td class="ds-cell--title" data-label="{{ __('members::admin.dues.fiscal_year') }}">{{ $due->fiscalYear->labelWithState() }}</td>
        <td class="text-ink-2" data-label="{{ __('members::admin.members.fee_category') }}">{{ $due->feeCategory->name }}</td>
        <td class="tabular-nums" data-label="{{ __('members::admin.dues.amount') }}">{{ MembersFormat::money($due->amount) }}</td>
    @endif
    <td data-label="{{ __('members::admin.dues.status') }}"><span class="ds-badge {{ $badgeClasses[$shownStatus] ?? '' }}">{{ $statusLabels[$shownStatus] ?? $shownStatus }}</span></td>
    <td class="tabular-nums" data-label="{{ __('members::admin.dues.bezahlt_am') }}">{{ $due->paidOnDisplay() }}</td>
    <td class="text-ink-2" data-label="{{ __('members::admin.dues.paid_through') }}">{{ $fiscalYears->firstWhere('id', $due->bezahlt_bis_fiscal_year_id)->name ?? '–' }}</td>
    <td class="tabular-nums" data-label="{{ __('members::admin.dues.teilbetrag') }}">{{ MembersFormat::optionalMoney($due->teilbetrag) }}</td>
    <td class="text-ink-2" data-label="{{ __('members::admin.dues.kommentar') }}">{{ $due->kommentar ?: '–' }}</td>
</tr>
