@aware(['for' => null, 'help' => null, 'error' => null, 'controlId' => null, 'fieldName' => null])
@props(['name' => null, 'id' => null, 'required' => false, 'options' => [], 'selected' => null, 'placeholder' => null])
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
    $current = \App\Support\Ui\FormValue::string($selected ?? old($name, ''));
    $class = $hasError ? 'ds-select ds-select--invalid' : 'ds-select';
@endphp
<select name="{{ $name }}" id="{{ $id }}" class="{{ $class }}"{!! $aria !!}{!! $attributes->isNotEmpty() ? " ".$attributes : "" !!}>@if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif @foreach ($options as $optionValue => $optionLabel)<option value="{{ $optionValue }}"{!! (string) $optionValue === $current ? ' selected' : '' !!}>{{ $optionLabel }}</option>@endforeach</select>
