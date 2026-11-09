<?php

declare(strict_types=1);

namespace App\Modules\Members\Models\Concerns;

use App\Modules\Members\Models\Payment;
use App\Modules\Members\Support\MembersFormat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

/**
 * Shared status rules of a due (member due and household due): paid, open, overdue, the displayed
 * status and the outstanding rest. The using model has the columns status, amount, teilbetrag and
 * faellig_am (date cast), keeps STATUSES = ['ausstehend', 'bezahlt', 'ueberfaellig'] and defines
 * payments(): BelongsToMany<Payment>.
 */
trait HasDueStatus
{
    /** @return BelongsToMany<Payment, $this> */
    abstract public function payments(): BelongsToMany;

    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            static::whereKey($this->getKey())->lockForUpdate()->first();
            if ($this->isDirty('amount') && $this->payments()->lockForUpdate()->first() !== null) {
                throw ValidationException::withMessages(['amount' => __('members::admin.dues.payment_locked')]);
            }

            return parent::save($options);
        });
    }

    public function isPaid(): bool
    {
        if ($this->status === 'bezahlt') {
            return true;
        }
        if (! $this->exists) {
            return false;
        }
        if (array_key_exists('payments_exists', $this->getAttributes())) {
            return (bool) $this->getAttribute('payments_exists');
        }

        return $this->relationLoaded('payments') ? $this->payments->isNotEmpty() : $this->payments()->exists();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', 'bezahlt')->orWhereHas('payments');
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'bezahlt')->whereDoesntHave('payments');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $this->scopeOpen($query)->where(function (Builder $query): void {
            $query->where('status', 'ueberfaellig')
                ->orWhere(function (Builder $query): void {
                    $query->where('status', 'ausstehend')
                        ->whereNotNull('faellig_am')
                        ->where('faellig_am', '<', now()->toDateString());
                });
        });
    }

    /** Offener Rest: Betrag minus Teilbetrag, nie negativ; 0 bei Status "bezahlt" oder mit verknüpfter Zahlung. */
    public function outstandingAmount(): string
    {
        // A linked payment always covers the whole due (PaymentController checks the sum).
        $hasPayment = $this->relationLoaded('payments') ? $this->payments->isNotEmpty() : $this->payments()->exists();
        if ($this->status === 'bezahlt' || $hasPayment) {
            return '0.00';
        }

        return number_format(max(0.0, (float) $this->amount - (float) ($this->teilbetrag ?? 0)), 2, '.', '');
    }

    /**
     * Text about the payment linked to this due ("Zahlung vom 01.03.2026 über 120,00 €"), null without one.
     * $key is the lang key: members::admin.dues.linked_payment (row hint) or .reset_payment_hint (after a reset).
     */
    public function linkedPaymentText(string $key = 'members::admin.dues.linked_payment'): ?string
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $payment = $payments->sortBy('paid_at')->first();
        if ($payment === null) {
            return null;
        }

        return __($key, [
            'date' => $payment->paid_at->format('d.m.Y'),
            'amount' => MembersFormat::money($payment->amount),
        ]);
    }

    /** Der angezeigte Status: "ausstehend" mit verstrichenem Fälligkeitsdatum gilt automatisch als "überfällig". */
    public function displayStatus(): string
    {
        if ($this->isPaid()) {
            return 'bezahlt';
        }
        if ($this->status === 'ausstehend' && $this->faellig_am !== null && $this->faellig_am->endOfDay()->isPast()) {
            return 'ueberfaellig';
        }

        return $this->status;
    }
}
