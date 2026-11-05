@props(['items', 'label' => null])
<x-ui.tabs :label="$label ?? __('tournament-base::common.area')" :items="collect($items)->map(fn ($item) => ['label' => $item['label'], 'url' => $item['href'], 'active' => $item['active'] ?? false])->all()" />
