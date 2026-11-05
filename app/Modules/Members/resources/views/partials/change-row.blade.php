{{--
    One member change as a list row: "Name: what changed" and "when · who". Needs: $change, $categoryNames.
    Never reveals personal values (lists across members; GDPR), see MemberChangeSummary.
--}}
@php
    $changedMember = $change->member;
    $name = $changedMember ? $changedMember->lastname.', '.$changedMember->firstname : '—';
    $summary = implode('; ', \App\Modules\Members\Support\MemberChangeSummary::lines($change, false, $categoryNames));
@endphp
<li class="mbr-change">
    <p class="mbr-change__what">@if($changedMember)<a href="{{ route('members.edit', $changedMember) }}" class="mbr-change__name">{{ $name }}</a>@else<span class="mbr-change__name">{{ $name }}</span>@endif: {{ $summary }}</p>
    <p class="mbr-change__meta">{{ $change->created_at->format('d.m.Y H:i') }} · {{ $change->adminUser->alias ?? __('members::admin.changes.system') }}</p>
</li>
