@props(['name' => null, 'value', 'label', 'type' => 'radio', 'checked' => false, 'description' => null])
{{-- Without a name the input only drives the page (e.g. an Alpine toggle) and submits nothing.
     With a description (long texts such as a consent): the label is the bold core sentence, the description follows in
     normal weight, and the control sits at the top of the text instead of its middle. --}}
@if ($description === null || $description === '')
<label class="ds-option"><input type="{{ $type }}"@if ($name !== null) name="{{ $name }}"@endif value="{{ $value }}" class="ds-option__input"{!! $checked ? ' checked' : '' !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}><span class="ds-option__label">{{ $label }}</span></label>
@else
<label class="ds-option items-start"><input type="{{ $type }}"@if ($name !== null) name="{{ $name }}"@endif value="{{ $value }}" class="ds-option__input"{!! $checked ? ' checked' : '' !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}><span class="ds-option__label"><strong>{{ $label }}</strong> <span class="font-normal">{{ $description }}</span></span></label>
@endif
