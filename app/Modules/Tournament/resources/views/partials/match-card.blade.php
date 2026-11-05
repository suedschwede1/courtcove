{{-- One match card from MatchPresenter::present() props. Needs: $props. --}}
<x-tournament-base::match-card :context="$props['context']" :badge="$props['badge']" :players="$props['players']" :score="$props['score']" :meta="$props['meta']" :action="$props['action']" />
