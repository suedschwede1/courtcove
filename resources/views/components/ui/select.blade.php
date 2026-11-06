@aware(['for' => null, 'help' => null, 'error' => null])
@props(['name' => null, 'id' => null, 'required' => false, 'options' => [], 'selected' => null, 'placeholder' => null])
@php
    $name = $name ?? $for;
    $id = $id ?? $for ?? $name;
    $errorText = $error ?? (isset($errors) ? $errors->first($name) : null);
    $hasError = $errorText !== null && $errorText !== '';
    $describedBy = trim((($help ?? null) !== null ? $id.'-help' : '').' '.($hasError ? $id.'-error' : ''));
    $aria = ($describedBy !== '' ? ' aria-describedby="'.e($describedBy).'"' : '')
        .($hasError ? ' aria-invalid="true"' : '')
        .($required ? ' required' : '');
    $current = \App\Support\Ui\FormValue::string($selected ?? old($name, ''));
    $class = $hasError ? 'ds-select ds-select--invalid' : 'ds-select';
@endphp
<select name="{{ $name }}" id="{{ $id }}" class="{{ $class }}"{!! $aria !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}>@if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif @foreach ($options as $optionValue => $optionLabel)<option value="{{ $optionValue }}"{!! (string) $optionValue === $current ? ' selected' : '' !!}>{{ $optionLabel }}</option>@endforeach</select>
