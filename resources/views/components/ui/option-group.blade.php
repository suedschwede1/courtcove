@props(['legend' => null, 'columns' => 2])
@php
    $colsClass = [2 => 'ds-options--cols-2', 3 => 'ds-options--cols-3'][$columns];
@endphp
<fieldset {{ $attributes->merge(['class' => 'ds-options '.$colsClass]) }}>@if ($legend !== null)<legend class="ds-field__label">{{ $legend }}</legend>@endif<div class="ds-options__grid">{{ $slot }}</div></fieldset>
