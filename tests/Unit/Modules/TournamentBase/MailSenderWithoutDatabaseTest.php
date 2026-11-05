<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TournamentBase;

use App\Modules\TournamentBase\Support\MailSender;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Without a migrated options table (fresh install, bare tests) the config and defaults apply.
 */
class MailSenderWithoutDatabaseTest extends TestCase
{
    #[Test]
    public function a_missing_options_table_falls_back_to_the_config(): void
    {
        config([
            'mail.tournament.from.address' => 'turnier@example.test',
            'mail.tournament.from.name' => null,
            'mail.from.name' => 'Buchungssystem',
            'mail.tournament.reply_to.address' => '',
            'booking.name' => 'Tennisclub Beispiel',
            'app.url' => 'https://club.example.test',
        ]);
        $sender = $this->app->make(MailSender::class);

        $this->assertSame(['turnier@example.test', 'Buchungssystem'], [$sender->from()?->address, $sender->from()?->name]);
        $this->assertSame([], $sender->replyTo());
        $this->assertSame("\nViele Grüße\nTennisclub Beispiel\nhttps://club.example.test\n", $sender->signature('de'));
    }
}
