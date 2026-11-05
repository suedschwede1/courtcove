{{--
    Change history on the member detail page. Needs: $changes, $member. Values of personal fields are only
    shown while the member is not anonymised (GDPR); see MemberChangeSummary.
--}}
@php
    use App\Modules\Members\Models\FeeCategory;
    use App\Modules\Members\Support\MemberChangeSummary;

    $revealPersonal = $member->anonymized_at === null;
    $categoryNames = FeeCategory::query()->pluck('name', 'id')->all();
@endphp
<x-ui.card :title="__('members::admin.history.title')">
    @forelse($changes as $change)
        <div class="mbr-stack border-t border-line py-3 first:border-t-0 first:pt-0 last:pb-0">
            <p class="text-body-s text-ink-2">
                {{ $change->created_at->format('d.m.Y H:i') }} — {{ $change->adminUser->alias ?? '—' }}
            </p>
            @php $lines = MemberChangeSummary::lines($change, $revealPersonal, $categoryNames); @endphp
            @if(count($lines) === 1)
                <p>{{ $lines[0] }}</p>
            @else
                <ul class="mbr-stack">
                    @foreach($lines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @empty
        <p class="text-body-s text-ink-2">{{ __('members::admin.history.empty') }}</p>
    @endforelse
</x-ui.card>
