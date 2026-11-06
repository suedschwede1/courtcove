@php
    // Class component App\View\Components\Ui\Field: $controlId and $fieldName are resolved before the slot renders.
    $errorText = $error ?? (isset($errors) && $fieldName !== null ? $errors->first($fieldName) : null);
    $errorText = $errorText === '' ? null : $errorText;
    // One id for label, control, help and error: the slot control's own id wins.
    $resolvedId = \App\Support\Ui\ControlId::fromMarkup((string) $slot) ?? $controlId;
@endphp
<div {{ $attributes->merge(['class' => 'ds-field']) }}>@if ($label !== null)<label class="ds-field__label" for="{{ $resolvedId }}">{{ $label }}</label>@endif{{ $slot }}@if ($help !== null)<p class="ds-field__help" id="{{ $resolvedId }}-help">{{ $help }}</p>@endif @if ($errorText !== null)<p class="ds-field__error" id="{{ $resolvedId }}-error">{{ $errorText }}</p>@endif</div>
