{{--
    Keeps the scroll position of a page across reloads and score posts (sessionStorage key
    "mixed-scroll:<route-name>:<id>"). Storage may be blocked (private mode), hence try/catch.
    Needs: $scrollId (event or round id).
--}}
@php
    $scrollKey = 'mixed-scroll:'.(request()->route()?->getName() ?? 'mixed').':'.$scrollId;
@endphp
<div hidden
     x-data="{
         key: @js($scrollKey),
         restore() { try { const y = window.sessionStorage.getItem(this.key); if (y !== null) { window.scrollTo(0, parseInt(y, 10) || 0); } } catch (e) {} },
         save() { try { window.sessionStorage.setItem(this.key, String(Math.round(window.scrollY))); } catch (e) {} },
     }"
     x-init="restore(); window.addEventListener('pagehide', () => save())"></div>
