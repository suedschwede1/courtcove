@php
    $dateFields = ['birthdate', 'joined_at', 'left_at'];
    $fieldLabels = [
        'member_number' => __('members::admin.members.member_number'),
        'nuliga_id' => __('members::admin.members.nuliga_id'),
        'firstname' => __('members::admin.members.firstname'),
        'lastname' => __('members::admin.members.lastname'),
        'birthdate' => __('members::admin.members.birthdate'),
        'gender' => __('members::admin.members.gender'),
        'email' => __('members::admin.members.email'),
        'phone' => __('members::admin.members.phone'),
        'street' => __('members::admin.members.street'),
        'zip' => __('members::admin.members.zip'),
        'city' => __('members::admin.members.city'),
        'fee_category_id' => __('members::admin.members.fee_category'),
        'joined_at' => __('members::admin.members.joined_at'),
        'left_at' => __('members::admin.members.left_at'),
        'user_id' => __('members::admin.members.user_account'),
    ];
    $formatHistoryValue = function ($field, $value) use ($dateFields) {
        if ($value === null || $value === '') {
            return '—';
        }
        if (in_array($field, $dateFields, true)) {
            return \Illuminate\Support\Carbon::parse($value)->format('d.m.Y');
        }
        if ($field === 'gender') {
            return match ($value) {
                'm' => __('members::admin.members.gender_m'),
                'w' => __('members::admin.members.gender_w'),
                'd' => __('members::admin.members.gender_d'),
                default => $value,
            };
        }
        return (string) $value;
    };
@endphp
<x-ui.card :title="__('members::admin.history.title')">
    @forelse($changes as $change)
        <div class="mbr-stack border-t border-line py-3 first:border-t-0 first:pt-0 last:pb-0">
            <p class="text-body-s text-ink-2">
                {{ $change->created_at->format('d.m.Y H:i') }} — {{ $change->adminUser->alias ?? '—' }}
            </p>
            @if($change->action === 'created')
                <p>{{ __('members::admin.history.created') }}</p>
            @else
                @php $fields = $change->changedFields(); @endphp
                @if($fields === [])
                    <p class="text-body-s text-ink-2">{{ __('members::admin.history.no_changes') }}</p>
                @else
                    <ul class="mbr-stack">
                        @foreach($fields as $fieldChange)
                            <li>{{ __('members::admin.history.change', [
                                'field' => $fieldLabels[$fieldChange['field']] ?? $fieldChange['field'],
                                'old' => $formatHistoryValue($fieldChange['field'], $fieldChange['old']),
                                'new' => $formatHistoryValue($fieldChange['field'], $fieldChange['new']),
                            ]) }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    @empty
        <p class="text-body-s text-ink-2">{{ __('members::admin.history.empty') }}</p>
    @endforelse
</x-ui.card>
