{{--
    One match card from MatchPresenter::present() props. Needs: $props. Optional: $mine (the viewer plays this
    match: tinted clay-soft like the own table row, as the old schedule tinted the own cards).
--}}
@if (! empty($mine))
<x-tournament-base::match-card class="bg-clay-soft" data-mine="1" :context="$props['context']" :badge="$props['badge']" :players="$props['players']" :score="$props['score']" :meta="$props['meta']" :action="$props['action']" />
@else
<x-tournament-base::match-card :context="$props['context']" :badge="$props['badge']" :players="$props['players']" :score="$props['score']" :meta="$props['meta']" :action="$props['action']" />
@endif
