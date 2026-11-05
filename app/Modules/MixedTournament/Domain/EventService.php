<?php

declare(strict_types=1);

namespace App\Modules\MixedTournament\Domain;

use App\Modules\MixedTournament\Models\MixedEvent;
use App\Modules\MixedTournament\Models\MixedEventGrant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Events, the default event, test mode, court count and per-event grants
 * (old MixedManager::createEvent() ... removeGrant()).
 */
final class EventService
{
    public function create(string $title, string $eventDate, string $mode): MixedEvent
    {
        if (! in_array($mode, ['americano', 'mexicano'], true)) {
            throw new InvalidArgumentException('invalid tournament mode');
        }

        return MixedEvent::query()->create([
            'title' => $title,
            'event_date' => $eventDate,
            'status' => 'draft',
            'is_default' => 0,
            'mode' => $mode,
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Exactly one default event: clear the flag everywhere, then set it, in one transaction. */
    public function setDefault(MixedEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            MixedEvent::query()->where('is_default', 1)->update(['is_default' => 0]);
            MixedEvent::query()->where('id', $event->id)->update(['is_default' => 1]);
        });

        $event->refresh();
    }

    public function defaultEvent(): ?MixedEvent
    {
        return MixedEvent::query()->where('is_default', 1)->orderBy('id')->first();
    }

    public function setTestMode(MixedEvent $event, bool $on): void
    {
        MixedEvent::query()->where('id', $event->id)->update(['is_test' => $on ? 1 : 0]);
        $event->refresh();
    }

    public function setCourtCount(MixedEvent $event, int $courtCount): void
    {
        MixedEvent::query()->where('id', $event->id)->update(['court_count' => max(1, $courtCount)]);
        $event->refresh();
    }

    /** A role change is an UPDATE: there is never a second row for the same (event, user). */
    public function setGrant(MixedEvent $event, int $uid, string $role): void
    {
        if (! in_array($role, ['score', 'admin'], true)) {
            throw new InvalidArgumentException('unknown grant role');
        }

        DB::transaction(function () use ($event, $uid, $role): void {
            $existing = MixedEventGrant::query()
                ->where('event_id', $event->id)
                ->where('uid', $uid)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->update(['role' => $role]);

                return;
            }

            MixedEventGrant::query()->create([
                'event_id' => $event->id,
                'uid' => $uid,
                'role' => $role,
                'created' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function removeGrant(MixedEvent $event, int $uid): void
    {
        MixedEventGrant::query()->where('event_id', $event->id)->where('uid', $uid)->delete();
    }

    public function grant(MixedEvent $event, int $uid): ?MixedEventGrant
    {
        return MixedEventGrant::query()
            ->where('event_id', $event->id)
            ->where('uid', $uid)
            ->orderBy('id')
            ->first();
    }
}
