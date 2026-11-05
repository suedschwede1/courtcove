@aware(['for' => null, 'help' => null, 'error' => null])
@props(['name' => null, 'id' => null, 'required' => false, 'type' => 'text', 'variant' => null, 'value' => null])
@php
    $name = $name ?? $for;
    $id = $id ?? $for ?? $name;
    $errorText = $error ?? (isset($errors) ? $errors->first($name) : null);
    $hasError = $errorText !== null && $errorText !== '';
    $describedBy = trim((($help ?? null) !== null ? $id.'-help' : '').' '.($hasError ? $id.'-error' : ''));
    $aria = ($describedBy !== '' ? ' aria-describedby="'.e($describedBy).'"' : '')
        .($hasError ? ' aria-invalid="true"' : '')
        .($required ? ' required' : '');
    $value = $value ?? old($name, '');
    $class = ['score' => 'ds-input ds-input--score', 'default' => 'ds-input'][$variant ?? 'default'];
    $class .= $hasError ? ' ds-input--invalid' : '';
    $aria = ($variant === 'score' ? ' inputmode="numeric"' : '').$aria;
@endphp
<input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="{{ $class }}"{!! $aria !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}>
