@props([
    'dates',
    'squares',
    'dateLabels',
    'reservationsBySlot',
    'reservationsByDate' => [],
    'eventBlocks',
    'today',
    'now',
    'isLoggedIn',
    'isAdmin',
    'authUserId',
    'canAdminEvents',
    'date',
    'playerExchangeOptedIn' => false,
])

@php
    $calendarHours = app(\App\Services\CalendarHours::class);
    $firstHour = $calendarHours->firstHour();
    $lastHour = $calendarHours->lastHour();
    $sids = $squares->pluck('sid')->values()->all();

    // Expand every event block over its courts and hours, so each court column
    // renders its own part per hour (start, middle, end, or single).
    $minimums = $squares->mapWithKeys(fn ($court) => [$court->sid => app(\App\Services\BookingRules::class)->minimumSeconds($court)]);
    $segments = [];
    $rowTemplates = [];
    $segmenter = app(\App\Services\CalendarSegments::class);
    for ($h = $firstHour; $h <= $lastHour; $h++) {
        $maxParts = 1;
        foreach (array_slice($dates, 1, 3, true) as $day) {
            $key = $day->format('Y-m-d');
            foreach ($squares as $court) {
                $parts = $segmenter->forHour($reservationsByDate[$key][$court->sid] ?? collect(), $court, $h, $minimums[$court->sid]);
                $segments[$key][$court->sid][$h] = $parts;
                $maxParts = max($maxParts, count($parts));
            }
        }
        $rowTemplates[] = $maxParts > 1 ? 'minmax('.($maxParts * 72).'px, 1fr)' : 'minmax(var(--cal-cell), 1fr)';
    }
    $eventParts = [];
    foreach ($eventBlocks as $blockDate => $blocksBySid) {
        foreach ($blocksBySid as $startSid => $blocksByHour) {
            $startIndex = array_search($startSid, $sids, true);
            if ($startIndex === false) {
                continue;
            }
            foreach ($blocksByHour as $blockHour => $block) {
                for ($col = 0; $col < $block['cols']; $col++) {
                    $partSid = $sids[$startIndex + $col] ?? null;
                    if ($partSid === null) {
                        continue;
                    }
                    for ($row = 0; $row < $block['rows']; $row++) {
                        $eventParts[$blockDate][$partSid][$blockHour + $row] = [
                            'block' => $block,
                            'part' => $block['rows'] === 1 ? 'single' : match ($row) {
                                0 => 'start',
                                $block['rows'] - 1 => 'end',
                                default => 'middle',
                            },
                        ];
                    }
                }
            }
        }
    }
@endphp

<div class="cal-board" id="calendar-grid" data-hour-rows="{{ implode(' ', $rowTemplates) }}" x-data x-init="$el.style.setProperty('--cal-hour-rows', $el.dataset.hourRows)" data-squares="{{ $squares->count() }}">
    <div class="cal-times" aria-hidden="true">
        <div class="cal-times__spacer cal-times__spacer--head"></div>
        <div class="cal-times__spacer cal-times__spacer--courts"></div>
        @for($h = $firstHour; $h <= $lastHour; $h++)
            <div class="cal-times__row">
                <span class="cal-times__hour">{{ sprintf('%02d:00', $h) }}</span>
                <span class="cal-times__until">{{ __('booking.calendar.until_hour', ['hour' => sprintf('%02d', $h + 1)]) }}</span>
            </div>
        @endfor
    </div>

    @foreach(array_slice($dates, 1, 3, true) as $dayIndex => $d)
        @php
            $dateKey = $d->format('Y-m-d');
            $titleId = 'cal-day-'.$dayIndex.'-title';
        @endphp
        <section @class(['cal-day', 'cal-day--today' => $dateKey === $today, 'cal-extra-day' => $dayIndex > 1]) data-day="{{ $dayIndex }}" aria-labelledby="{{ $titleId }}">
            <div class="cal-day__head">
                <h2 id="{{ $titleId }}" class="cal-day__title">
                    <span class="cal-day__name">{{ $dateLabels[$dateKey]['short'] }}</span>
                    <span class="cal-day__date">{{ $d->isoFormat('D. MMMM') }}</span>
                </h2>
                @if($dateKey === $today)
                    <span class="cal-day__today">{{ __('booking.nav.today') }}</span>
                @endif
            </div>

            <div class="cal-day__courts">
                <span class="cal-day__courts-spacer" aria-hidden="true"></span>
                @foreach($squares as $square)
                    <div class="cal-court">
                        <span class="cal-court__alias">{{ $square->display_name }}</span>
                        @if($square->alias !== null)
                            <span class="cal-court__number">{{ __('booking.calendar.court_number', ['number' => $loop->iteration]) }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            @for($h = $firstHour; $h <= $lastHour; $h++)
                <div class="cal-row" data-hour="{{ $h }}">
                    <span class="cal-row__time">{{ sprintf('%02d:00', $h) }}</span>
                    @foreach($squares as $square)
                        @php $eventPart = $eventParts[$dateKey][$square->sid][$h] ?? null; @endphp
                        @if($eventPart)
                            <x-calendar.event-cell
                                :name="$eventPart['block']['name']"
                                :kind="$eventPart['block']['kind'] ?? 'event'"
                                :event="$eventPart['block']['event']"
                                :part="$eventPart['part']"
                                :can-admin-events="$canAdminEvents"
                                :date-key="$dateKey"
                            />
                        @else
                            @php
                                $parts = $segments[$dateKey][$square->sid][$h];
                                $fragmented = count($parts) > 1 || ($parts[0]['start'] ?? $h * 3600) !== $h * 3600 || ($parts[0]['end'] ?? ($h + 1) * 3600) !== ($h + 1) * 3600;
                            @endphp
                            @if($fragmented)<div class="cal-parts cal-parts--split" data-sid="{{ $square->sid }}">@endif
                                @foreach($parts as $part)
                                    @if($fragmented)<div class="cal-part" data-slot-start="{{ $part['start'] }}" data-slot-end="{{ $part['end'] }}">@endif
                                        @if($fragmented)
                                            <span class="cal-part__time">{{ gmdate('H:i', $part['start']) }}–{{ gmdate('H:i', $part['end']) }}</span>
                                        @endif
                                        <x-calendar.cell
                                            :square="$square" :date-key="$dateKey" :hour="$h"
                                            :date-label="$dateLabels[$dateKey]['full']"
                                            :is-past="$part['closed'] || ($part['tooShort'] && ! $isAdmin) || $dateKey < $today || ($dateKey === $today && $part['end'] <= ($now->hour * 3600 + $now->minute * 60 + $now->second))"
                                            :closed="$part['closed']" :too-short="$part['tooShort'] && ! $isAdmin"
                                            :reservation="$part['reservation']"
                                            :slot-start="$part['start']" :slot-end="$isAdmin ? $part['end'] : $part['bookEnd']"
                                            :is-logged-in="$isLoggedIn" :is-admin="$isAdmin" :auth-user-id="$authUserId"
                                            :date="$date" :player-exchange-opted-in="$playerExchangeOptedIn"
                                        />
                                    @if($fragmented)</div>@endif
                                @endforeach
                            @if($fragmented)</div>@endif
                        @endif
                    @endforeach
                </div>
            @endfor
        </section>
    @endforeach
</div>
