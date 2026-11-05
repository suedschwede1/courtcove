@props([
    'dates',
    'squares',
    'dateLabels',
    'reservationsBySlot',
    'eventBlocks',
    'today',
    'now',
    'isLoggedIn',
    'isAdmin',
    'authUserId',
    'canAdminEvents',
    'date',
])

@php
    $firstHour = 8;
    $lastHour = 19;
    $sids = $squares->pluck('sid')->values()->all();

    // Expand every event block over its courts and hours, so each court column
    // renders its own part per hour (start, middle, end, or single).
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

<div class="cal-board" id="calendar-grid" data-squares="{{ $squares->count() }}">
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
                        @php($eventPart = $eventParts[$dateKey][$square->sid][$h] ?? null)
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
                            <x-calendar.cell
                                :square="$square"
                                :date-key="$dateKey"
                                :hour="$h"
                                :date-label="$dateLabels[$dateKey]['full']"
                                :is-past="$dateKey < $today || ($dateKey === $today && ($h + 1) <= $now->hour)"
                                :reservation="$reservationsBySlot[$dateKey][$square->sid][$h] ?? null"
                                :is-logged-in="$isLoggedIn"
                                :is-admin="$isAdmin"
                                :auth-user-id="$authUserId"
                                :date="$date"
                            />
                        @endif
                    @endforeach
                </div>
            @endfor
        </section>
    @endforeach
</div>
