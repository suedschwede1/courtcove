@props(['tone' => null, 'status' => null])
@php
    if ($tone === null && $status === null) {
        throw new \InvalidArgumentException('Badge needs a tone or a status.');
    }
    $toneEnum = $tone !== null ? \App\Support\Ui\BadgeTone::from($tone) : \App\Support\Ui\BadgeTone::forStatus($status);
    $toneClass = [
        'good' => 'ds-badge--good',
        'neutral' => 'ds-badge--neutral',
        'attention' => 'ds-badge--attention',
        'problem' => 'ds-badge--problem',
        'you' => 'ds-badge--you',
    ][$toneEnum->value];
    $label = trim((string) $slot) !== '' ? $slot : __('booking.ui.status.'.$status);
@endphp
<span {{ $attributes->class(['ds-badge', $toneClass]) }}>{{ $label }}</span>
