{{--
    Entry list of the area sidebar (x-layout.admin-sidebar), recursive for sub-groups.
    The current page carries aria-current="page"; an entry holding it (tournament module, parent of the active
    sub-entry) carries aria-current="true". A badge with an icon shows the icon and reads its text to screen readers.
    A sub-group is expanded only below the entry holding the current page (the entry itself links to its first page).
--}}
@props(['items', 'sub' => false])
<ul {{ $attributes->class(['ds-admin-sidebar__list', 'ds-admin-sidebar__list--sub' => $sub]) }}>
    @foreach($items as $item)
        <li><a href="{{ $item->url }}" class="ds-admin-sidebar__link"@if($item->active) aria-current="page"@elseif($item->current) aria-current="true"@endif>{{ $item->label }}@if($item->badge !== null)<span class="ds-admin-sidebar__badge">@if($item->badgeIcon !== null)<x-ui.icon :name="$item->badgeIcon" :size="16" /><span class="sr-only">{{ $item->badge }}</span>@else{{ $item->badge }}@endif</span>@endif</a>@if($item->children !== [] && ($item->current || $item->active))<x-layout.area-nav-list :items="$item->children" :sub="true" />@endif</li>
    @endforeach
</ul>
