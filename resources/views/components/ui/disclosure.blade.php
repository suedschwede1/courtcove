@props(['summary', 'open' => false])
<details {{ $attributes->class(["ds-card", "ds-disclosure"]) }}{!! $open ? " open" : "" !!}>
<summary class="ds-disclosure__summary">{{ $summary }}</summary>
<div class="ds-disclosure__body">{{ $slot }}</div>
</details>
