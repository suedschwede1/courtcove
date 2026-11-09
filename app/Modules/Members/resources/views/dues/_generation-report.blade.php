{{--
    Result of "Beiträge erzeugen" (DuesGenerationReport from the session flash): one sentence with the counts and,
    per reason, a disclosure with the linked names. Good tone when nothing was skipped, else attention.
--}}
@php
    use App\Modules\Members\Support\DuesGenerationReport;
@endphp
<x-ui.notice :tone="$report->skippedCount() === 0 ? 'good' : 'attention'" data-testid="dues-generation-report">
    @if(session('dues_report_preview'))<p>{{ __('members::admin.review.preview_hint') }}</p>@endif
    <p class="m-0">
        {{ __('members::admin.dues.report.summary', ['year' => $report->yearName, 'total' => $report->total(), 'members' => $report->createdMembers, 'households' => $report->createdHouseholds]) }}
        @if($report->createdFromMemberCategory > 0)
            {{ __('members::admin.dues.report.from_member_category', ['count' => $report->createdFromMemberCategory]) }}
        @endif
        @if($report->skippedCount() > 0)
            {{ __('members::admin.dues.report.skipped_total', ['count' => $report->skippedCount()]) }}
        @endif
    </p>
    @foreach(DuesGenerationReport::REASONS as $reason)
        @if($report->skipped[$reason] !== [])
            <x-ui.disclosure :summary="__('members::admin.dues.report.'.$reason, ['count' => count($report->skipped[$reason])])">
                <ul class="m-0 flex flex-col gap-1">
                    @foreach($report->skipped[$reason] as $row)
                        <li><a href="{{ $row['url'] }}" class="mbr-text-link">{{ $row['label'] }}</a></li>
                    @endforeach
                </ul>
            </x-ui.disclosure>
        @endif
    @endforeach
</x-ui.notice>
