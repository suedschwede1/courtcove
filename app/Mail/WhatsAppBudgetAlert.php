<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Notifications\BudgetSnapshot;
use App\Support\Mail\SystemMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Warning to the club admin that the monthly WhatsApp budget reached 80 % or 100 %. */
class WhatsAppBudgetAlert extends Mailable
{
    use Queueable;

    /** @param  int  $level  80 (warning) or 100 (sending stopped) */
    public function __construct(
        public readonly BudgetSnapshot $snapshot,
        public readonly int $level,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('booking.notifications.budget_alert.subject_'.$this->level),
            replyTo: app(SystemMailSender::class)->replyTo(SystemMailSender::SCOPE_BOOKING),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.whatsapp-budget-alert',
            with: [
                'level' => $this->level,
                'percent' => $this->snapshot->percent,
                'messages' => $this->snapshot->messages,
                'spent' => $this->money($this->snapshot->estimatedMicros),
                'budget' => $this->money($this->snapshot->budgetMicros),
                'month' => $this->snapshot->month,
            ],
        );
    }

    private function money(int $micros): string
    {
        $de = app()->getLocale() === 'de';

        return number_format($micros / 1_000_000, 2, $de ? ',' : '.', $de ? '.' : ',');
    }
}
