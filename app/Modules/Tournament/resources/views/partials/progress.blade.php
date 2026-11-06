{{--
    Progress bar of played matches, one segment per match (no inline widths). Decorative: the text
    "x von y gespielt" next to it carries the information.
    Needs: $played, $total.
--}}
@if ($total > 0)
<div class="flex gap-1" aria-hidden="true">
    @for ($i = 0; $i < $total; $i++)
        <span @class(['h-1.5 flex-1 rounded-full', 'bg-court' => $i < $played, 'bg-line' => $i >= $played])></span>
    @endfor
</div>
@endif
