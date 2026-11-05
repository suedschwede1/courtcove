@aware(['for' => null, 'help' => null, 'error' => null])
@props(['name' => null, 'id' => null, 'required' => false, 'rows' => null, 'value' => null])
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
    $class = $hasError ? 'ds-textarea ds-textarea--invalid' : 'ds-textarea';
    $rowsAttr = $rows !== null ? ' rows="'.e($rows).'"' : '';
@endphp
<textarea name="{{ $name }}" id="{{ $id }}"{!! $rowsAttr !!} class="{{ $class }}"{!! $aria !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}>{{ $value }}</textarea>
