@props(['name', 'value', 'label', 'type' => 'radio', 'checked' => false])
<label class="ds-option"><input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" class="ds-option__input"{!! $checked ? ' checked' : '' !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}><span class="ds-option__label">{{ $label }}</span></label>
