{{--
    Body of one help section. Needs: $key (section key in info.php), $blocks (list of "p:key", "note:key",
    "lead:key" or "list:key1,key2"); a list item and a lead paragraph start with the bold `<key>_term`.
--}}
@foreach ($blocks as $block)
    @php
        [$type, $keys] = explode(':', $block, 2);
    @endphp
    @if ($type === 'list')
        <ul class="flex max-w-prose list-disc flex-col gap-1 pl-5 text-body text-ink-2">
            @foreach (explode(',', $keys) as $item)
                <li><strong class="text-ink">{{ __('mixed::info.'.$key.'.'.$item.'_term') }}</strong> — {{ __('mixed::info.'.$key.'.'.$item) }}</li>
            @endforeach
        </ul>
    @elseif ($type === 'lead')
        <p class="max-w-prose text-body text-ink-2"><strong class="text-ink">{{ __('mixed::info.'.$key.'.'.$keys.'_term') }}</strong> {{ __('mixed::info.'.$key.'.'.$keys) }}</p>
    @elseif ($type === 'note')
        <p class="max-w-prose text-body-s text-ink-3">{{ __('mixed::info.'.$key.'.'.$keys) }}</p>
    @else
        <p class="max-w-prose text-body text-ink-2">{{ __('mixed::info.'.$key.'.'.$keys) }}</p>
    @endif
@endforeach
