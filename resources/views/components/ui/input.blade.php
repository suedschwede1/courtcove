@aware(['for' => null, 'help' => null, 'error' => null, 'controlId' => null, 'fieldName' => null])
@props(['name' => null, 'id' => null, 'required' => false, 'type' => 'text', 'variant' => null, 'value' => null])
@php
    $name = $name ?? $fieldName ?? $for;
    // One id per control: an explicit id wins, inside x-ui.field the field's id, otherwise a unique id per page.
    $id = match (true) {
        $id !== null && $id !== '' => \App\Support\Ui\ControlId::reserve($id),
        $controlId !== null => $controlId,
        $name !== null && $name !== '' => \App\Support\Ui\ControlId::claim($name),
        default => $id,
    };
    $errorText = $error ?? (isset($errors) ? $errors->first($name) : null);
    $hasError = $errorText !== null && $errorText !== '';
    $describedBy = trim((($help ?? null) !== null ? $id.'-help' : '').' '.($hasError ? $id.'-error' : ''));
    $aria = ($describedBy !== '' ? ' aria-describedby="'.e($describedBy).'"' : '')
        .($hasError ? ' aria-invalid="true"' : '')
        .($required ? ' required' : '');
    $value = \App\Support\Ui\FormValue::string($value ?? old($name, ''));
    $class = ['score' => 'ds-input ds-input--score', 'default' => 'ds-input'][$variant ?? 'default'];
    $class .= $hasError ? ' ds-input--invalid' : '';
    $aria = ($variant === 'score' ? ' inputmode="numeric"' : '').$aria;
@endphp
<input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="{{ $class }}"{!! $aria !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}>
