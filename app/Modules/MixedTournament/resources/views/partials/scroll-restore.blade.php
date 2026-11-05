{{--
    One-shot scroll restore after a score post (as in ZF2): the position is saved ONLY when a form marked
    `data-scroll-save` is submitted, restored once on the next page load and then removed, so any other
    navigation starts at the top. Storage may be blocked (private mode), hence try/catch.
    Usage (admin round workspace): @include('mixed::partials.scroll-restore', ['scrollId' => $round->id])
    and mark the score forms with `data-scroll-save` (mixed::partials.match-card does so with $withResults).
    Not for read-only player pages. The key ("mixed-scroll:<route-name>:<id>") is built on the rendering
    GET page, so save and restore use the same key across the POST redirect.
    Needs: $scrollId (round or event id).
--}}
@php
    $scrollKey = 'mixed-scroll:'.(request()->route()?->getName() ?? 'mixed').':'.$scrollId;
@endphp
<div hidden
     data-scroll-restore="{{ $scrollKey }}"
     x-data="{
         key: @js($scrollKey),
         restore() { try { const y = window.sessionStorage.getItem(this.key); if (y !== null) { window.sessionStorage.removeItem(this.key); window.scrollTo(0, parseInt(y, 10) || 0); } } catch (e) {} },
         save() { try { window.sessionStorage.setItem(this.key, String(Math.round(window.scrollY))); } catch (e) {} },
     }"
     x-init="restore(); document.addEventListener('submit', (event) => { if (event.target.closest('[data-scroll-save]')) { save(); } })"></div>
