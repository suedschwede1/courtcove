@props(['name' => null, 'value', 'label', 'type' => 'radio', 'checked' => false])
{{-- Without a name the input only drives the page (e.g. an Alpine toggle) and submits nothing. --}}
<label class="ds-option"><input type="{{ $type }}"@if ($name !== null) name="{{ $name }}"@endif value="{{ $value }}" class="ds-option__input"{!! $checked ? ' checked' : '' !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}><span class="ds-option__label">{{ $label }}</span></label>
