<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\PlayerSearchOpened;
use App\Models\PlayerSearch;
use App\Services\BookingService;
use App\Services\PlayerExchangeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the exchange mail of a new player search to every active opted-in member
 * except the booker, in chunks so a large exchange never loads all members at once.
 * Each mail is personal (greeting, unsubscribe link) and queued on its own.
 */
final class SendPlayerSearchMails implements ShouldQueue
{
    use Dispatchable, Queueable;

    public const CHUNK_SIZE = 50;

    public function __construct(
        public readonly int $searchId,
    ) {}

    public function handle(PlayerExchangeService $exchange, BookingService $bookings): void
    {
        if (! $bookings->isMailConfigured()) {
            return;
        }

        $search = PlayerSearch::query()->with('booking.user')->find($this->searchId);
        if ($search === null || $search->status !== PlayerSearch::OPEN || $search->booking === null) {
            return;
        }

        $exchange->recipientsQuery(exclude: $search->booking->user)
            ->chunkById(self::CHUNK_SIZE, function (Collection $recipients) use ($search): void {
                foreach ($recipients as $recipient) {
                    try {
                        Mail::to($recipient->email)->send(new PlayerSearchOpened($search, $recipient));
                    } catch (\Throwable $e) {
                        // One broken address must not stop the mails to the others.
                        report($e);
                    }
                }
            });
    }
}
