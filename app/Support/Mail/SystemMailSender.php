<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Illuminate\Mail\Mailables\Address;

/**
 * Reply-To of the booking system's own mails, configurable per scope so the club can
 * route replies without touching code:
 *
 * - booking: booking confirmation and cancellation
 * - account: password reset, account activation and login verification code
 *
 * Precedence: admin option (admin "Texte" page, non-empty) > config mail.system.* (.env)
 * > none. Empty everywhere means no Reply-To header (the default). Laravel's global
 * mail.reply_to stays null on purpose and is NOT a fallback here, so the value only
 * reaches the mails that ask for it in their envelope. The tournament mails keep their
 * own Reply-To (App\Modules\TournamentBase\Support\MailSender).
 */
final class SystemMailSender
{
    use ResolvesMailOptions;

    public const SCOPE_BOOKING = 'booking';

    public const SCOPE_ACCOUNT = 'account';

    public const OPTION_BOOKING_REPLY_TO_ADDRESS = 'service.mail.system.booking.reply_to.address';

    public const OPTION_ACCOUNT_REPLY_TO_ADDRESS = 'service.mail.system.account.reply_to.address';

    private const OPTIONS = [
        self::SCOPE_BOOKING => self::OPTION_BOOKING_REPLY_TO_ADDRESS,
        self::SCOPE_ACCOUNT => self::OPTION_ACCOUNT_REPLY_TO_ADDRESS,
    ];

    /**
     * @param  string  $scope  one of the SCOPE_* constants; an unknown scope gives no Reply-To
     * @return list<Address> empty when no Reply-To address is configured for the scope
     */
    public function replyTo(string $scope): array
    {
        $option = self::OPTIONS[$scope] ?? null;

        if ($option === null) {
            return [];
        }

        $address = $this->value($option, 'mail.system.'.$scope.'.reply_to.address');

        return $address === '' ? [] : [new Address($address)];
    }
}
