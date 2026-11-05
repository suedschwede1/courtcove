@props(['items'])
<dl class="ds-summary">
    @foreach ($items as $item)
        <div class="ds-summary__item">
            <dt class="ds-summary__label">{{ $item['label'] }}</dt>
            @if (isset($item['bind']))
                <dd class="ds-summary__value" x-text="{{ $item['bind'] }}"></dd>
            @else
                <dd class="ds-summary__value">{{ $item['value'] ?? '' }}</dd>
            @endif
        </div>
    @endforeach
</dl>
