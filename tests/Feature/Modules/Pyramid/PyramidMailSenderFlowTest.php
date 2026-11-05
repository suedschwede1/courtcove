<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Models\Option;
use App\Models\User;
use App\Modules\Pyramid\Models\PyramidChallenge;
use App\Modules\Pyramid\Models\PyramidLadder;
use App\Modules\Pyramid\Models\PyramidPlayer;
use App\Modules\TournamentBase\Support\MailSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * With a configured tournament sender the Pyramid flow still mails synchronously after
 * its DB::transaction has returned, now with the configured From and Reply-To.
 */
class PyramidMailSenderFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function scheduling_mails_both_sides_after_the_transaction_with_the_configured_sender(): void
    {
        $ladder = PyramidLadder::factory()->asDefault()->withRanking(10)->create();
        $users = [];
        foreach ([8, 5] as $position) {
            $users[$position] = User::factory()->create(['email' => 'spieler'.$position.'@example.test']);
            PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', $position)->update(['uid' => $users[$position]->uid]);
        }
        $challenge = PyramidChallenge::factory()->create([
            'ladder_id' => $ladder->id,
            'challenger_id' => PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 8)->value('id'),
            'opponent_id' => PyramidPlayer::query()->where('ladder_id', $ladder->id)->where('position', 5)->value('id'),
            'status' => 'open',
            'deadline' => now()->addDays(10)->format('Y-m-d'),
        ]);
        Option::query()->create(['key' => MailSender::OPTION_FROM_ADDRESS, 'value' => 'turnier@example.test', 'locale' => null]);
        Option::query()->create(['key' => MailSender::OPTION_FROM_NAME, 'value' => 'Turnierleitung', 'locale' => null]);
        Option::query()->create(['key' => MailSender::OPTION_REPLY_TO_ADDRESS, 'value' => 'sport@example.test', 'locale' => null]);

        Mail::purge('array');
        $baseline = DB::transactionLevel();
        $levels = [];
        Event::listen(MessageSending::class, function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $this->actingAs($users[8])
            ->post('/pyramide/forderung/'.$challenge->id.'/termin', ['scheduled_at' => now()->addDays(3)->setTime(17, 0)->format('Y-m-d\TH:i')])
            ->assertRedirect();

        $this->assertSame([$baseline, $baseline], $levels, 'mails are sent outside the transaction');
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $sent) {
            $email = $sent->getOriginalMessage();
            $this->assertInstanceOf(Email::class, $email);
            $this->assertSame(['"Turnierleitung" <turnier@example.test>'], array_map(fn (Address $a) => $a->toString(), $email->getFrom()));
            $this->assertSame(['sport@example.test'], array_map(fn (Address $a) => $a->toString(), $email->getReplyTo()));
        }
    }
}
