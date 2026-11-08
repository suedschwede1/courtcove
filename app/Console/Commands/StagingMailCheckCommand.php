<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mail\StagingMailGuard;
use App\Support\StagingDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Staging only: shows the state of the mail protection (never the redirect address) and can send one test mail through it. */
class StagingMailCheckCommand extends Command
{
    protected $signature = 'mail:staging-check {--send : Send one test mail (to a fictional recipient, redirected by the protection)}';

    protected $description = 'STAGING ONLY: check the mail redirect protection; --send sends a test mail through it.';

    public function handle(): int
    {
        if (! StagingDatabase::active()) {
            $this->error('Abbruch: Das ist keine Staging-Datenbank (Name endet nicht auf _staging); es wird nichts gesendet.');

            return 1;
        }

        $mailer = (string) config('mail.default');
        $real = StagingMailGuard::isRealMailer($mailer);
        $address = StagingMailGuard::redirectAddress();
        $rawValue = config('mail.staging_redirect');
        $configured = is_string($rawValue) && trim($rawValue) !== '';

        $this->line("Mailer: {$mailer} (".($real ? 'echter Versand' : 'kein Versand (log/array)').')');
        $this->line('MAIL_STAGING_REDIRECT: '.match (true) {
            $address !== null => 'gesetzt',
            $configured => 'gesetzt, aber keine gültige Adresse',
            default => 'nicht gesetzt',
        });

        if ($address === null && $real) {
            $this->warn('Mails werden auf dieser Instanz NICHT gesendet, bis MAIL_STAGING_REDIRECT gesetzt ist.');
        }

        if (! $this->option('send')) {
            return 0;
        }

        $sent = Mail::raw(
            "Testmail des Staging-Mailschutzes.\n",
            fn ($message) => $message->to('nobody@example.test')->subject('Staging-Mailcheck')
        );

        if ($sent === null) {
            $this->error('Testmail nicht gesendet (Mailschutz hat den Versand gestoppt).');

            return 1;
        }

        $this->info($address !== null ? 'Testmail gesendet und an die Redirect-Adresse umgeleitet.' : 'Testmail an den log/array-Mailer übergeben (kein echter Versand).');

        return 0;
    }
}
