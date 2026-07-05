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
        return $value;
    };
@endphp
<div class="ui-card">
    <div class="ui-card-header"><h2>{{ __('members::admin.history.title') }}</h2></div>
    <div class="ui-card-body ui-stack">
        @forelse($changes as $change)
            <div class="ui-stack" style="border-bottom:1px solid #e7e3da; padding-bottom:12px; margin-bottom:12px;">
                <p class="ui-kpi-meta">
                    {{ $change->created_at->format('d.m.Y H:i') }} — {{ $change->adminUser->alias ?? '—' }}
                </p>
                @if($change->action === 'created')
                    <p>{{ __('members::admin.history.created') }}</p>
                @else
                    @php $fields = $change->changedFields(); @endphp
                    @if($fields === [])
                        <p class="ui-kpi-meta">{{ __('members::admin.history.no_changes') }}</p>
                    @else
                        <ul>
                            @foreach($fields as $fieldChange)
                                <li>
                                    {{ $fieldLabels[$fieldChange['field']] ?? $fieldChange['field'] }}:
                                    {{ $formatHistoryValue($fieldChange['field'], $fieldChange['old']) }}
                                    →
                                    {{ $formatHistoryValue($fieldChange['field'], $fieldChange['new']) }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        @empty
            <p class="ui-kpi-meta">{{ __('members::admin.history.empty') }}</p>
        @endforelse
    </div>
</div>
