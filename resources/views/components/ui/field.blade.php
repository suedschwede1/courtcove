@props(['label' => null, 'for' => null, 'help' => null, 'error' => null, 'required' => false])
@php
    $errorText = $error ?? (isset($errors) ? $errors->first($for) : null);
    $errorText = $errorText === '' ? null : $errorText;
@endphp
<div {{ $attributes->merge(['class' => 'ds-field']) }}>@if ($label !== null)<label class="ds-field__label" for="{{ $for }}">{{ $label }}</label>@endif{{ $slot }}@if ($help !== null)<p class="ds-field__help" id="{{ $for }}-help">{{ $help }}</p>@endif @if ($errorText !== null)<p class="ds-field__error" id="{{ $for }}-error">{{ $errorText }}</p>@endif</div>
