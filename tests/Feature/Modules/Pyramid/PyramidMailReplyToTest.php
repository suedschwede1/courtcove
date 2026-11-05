<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pyramid;

use App\Modules\Pyramid\Mail\PyramidNotificationMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision: replies to Pyramid mails go to the club address from config mail.reply_to, when one is configured.
 */
class PyramidMailReplyToTest extends TestCase
{
    #[Test]
    public function the_reply_to_comes_from_config_with_and_without_a_name(): void
    {
        config(['mail.reply_to.address' => 'club@example.test', 'mail.reply_to.name' => 'Pyramide Club']);
        Mail::fake();

        Mail::to('player@example.test')->send(new PyramidNotificationMail('Betreff', 'Text', 'Anna'));

        Mail::assertSent(PyramidNotificationMail::class, fn (PyramidNotificationMail $mail): bool => $mail->hasReplyTo('club@example.test', 'Pyramide Club'));

        config(['mail.reply_to.name' => null]);
        Mail::fake();
        Mail::to('player@example.test')->send(new PyramidNotificationMail('Betreff', 'Text', 'Anna'));

        Mail::assertSent(PyramidNotificationMail::class, fn (PyramidNotificationMail $mail): bool => $mail->hasReplyTo('club@example.test'));
    }

    #[Test]
    public function without_configuration_no_reply_to_is_set(): void
    {
        foreach ([null, ''] as $unset) {
            config(['mail.reply_to.address' => $unset, 'mail.reply_to.name' => null]);
            Mail::fake();

            Mail::to('player@example.test')->send(new PyramidNotificationMail('Betreff', 'Text', 'Anna'));

            Mail::assertSent(PyramidNotificationMail::class, fn (PyramidNotificationMail $mail): bool => $mail->replyTo === []);
        }
    }

    #[Test]
    public function the_default_configuration_has_no_reply_to(): void
    {
        $this->assertNull(config('mail.reply_to.address'));
    }
}
